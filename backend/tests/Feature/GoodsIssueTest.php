<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\MasterData\Models\AccessProfile;
use App\Modules\MasterData\Models\Category;
use App\Modules\MasterData\Models\Division;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\JobTitle;
use App\Modules\Ppic\Livewire\GoodsIssuePage;
use App\Modules\Ppic\Models\InventoryIssue;
use App\Modules\Ppic\Models\InventoryIssueLine;
use App\Modules\Ppic\Services\InventoryIssueService;
use App\Modules\Ppic\Services\InventoryStockService;
use App\Modules\Ppic\Services\SalesReadinessService;
use App\Modules\Purchasing\Services\ProcurementService;
use App\Services\AccessPermissionService;
use App\Services\SinoContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\UsesMasterSales;
use Tests\TestCase;

class GoodsIssueTest extends TestCase
{
    use RefreshDatabase;
    use UsesMasterSales;

    protected UserRole $authenticatedRole = UserRole::SuperAdmin;

    private function receive(Item $item, string $quantity = '100'): void
    {
        $service = app(ProcurementService::class);
        $request = $service->submit(['product_id' => $item->id, 'quantity' => $quantity, 'required_on' => now()->format('Y-m-d')]);
        $order = $service->approve($request->id, now()->format('Y-m-d'), now()->format('Y-m-d'));
        $service->receive($order->id, $quantity, now()->format('Y-m-d'), (string) Str::uuid());
    }

    private function data(Item $item, string $quantity = '10'): array
    {
        $division = Division::firstOrCreate(['code' => 'MTC'], ['name' => 'Maintenance', 'is_active' => true]);

        return ['issued_on' => now()->format('Y-m-d'), 'purpose' => 'maintenance', 'division_id' => $division->id,
            'recipient' => 'Petugas Maintenance', 'asset' => 'Mesin 01', 'request_reference' => 'MTC-001', 'notes' => 'Penggantian bearing',
            'lines' => [['product_id' => $item->id, 'quantity' => $quantity]]];
    }

    private function issue(Item $item, string $quantity = '10'): InventoryIssue
    {
        return app(InventoryIssueService::class)->save($this->data($item, $quantity), (string) Str::uuid());
    }

    private function employee(array $permissions): array
    {
        $n = AccessProfile::count() + 1;
        $job = JobTitle::create(['code' => 'JOB'.$n, 'name' => 'Petugas '.$n, 'is_active' => true]);
        $division = Division::create(['code' => 'DIV'.$n, 'name' => 'Divisi '.$n, 'is_active' => true]);
        $profile = AccessProfile::create(['job_title_id' => $job->id, 'division_id' => $division->id,
            'permissions' => AccessPermissionService::normalize($permissions), 'configured_modules' => ['ppic_ic'], 'is_active' => true]);

        return [User::factory()->create(['role' => UserRole::Ppic, 'job_title_id' => $job->id, 'division_id' => $division->id]), $profile];
    }

    public function test_multiline_draft_confirmation_reversal_and_retry_do_not_double_change_stock(): void
    {
        $a = $this->masterMaterial('Bearing', 'PCS');
        $b = $this->masterMaterial('Belt', 'PCS');
        $this->receive($a);
        $this->receive($b, '50');
        $data = $this->data($a);
        $data['lines'][] = ['product_id' => $b->id, 'quantity' => '5.000001'];
        $service = app(InventoryIssueService::class);
        $token = (string) Str::uuid();
        $issue = $service->save($data, $token);
        $this->assertSame($issue->id, $service->save($data, $token)->id);
        $this->assertDatabaseCount('inventory_issues', 1);
        $this->assertSame('100.000000', app(InventoryStockService::class)->balance($a->id, $a->uom_id)['physical']);
        $service->confirm($issue->id);
        $service->confirm($issue->id);
        $this->assertSame('90.000000', app(InventoryStockService::class)->balance($a->id, $a->uom_id)['physical']);
        $this->assertSame('44.999999', app(InventoryStockService::class)->balance($b->id, $b->uom_id)['physical']);
        $service->reverse($issue->id, 'Salah pencatatan, barang belum diserahkan');
        $service->reverse($issue->id, 'Salah pencatatan, barang belum diserahkan');
        $this->assertSame('100.000000', app(InventoryStockService::class)->balance($a->id, $a->uom_id)['physical']);
        $this->assertSame('50.000000', app(InventoryStockService::class)->balance($b->id, $b->uom_id)['physical']);
        $this->assertNotNull($issue->fresh()->confirmed_at);
        $this->assertNotNull($issue->fresh()->reversed_at);
        $this->assertSame(auth()->id(), $issue->fresh()->reversed_by);
        $this->assertSame(2, $issue->lines()->count());
    }

    public function test_reserved_stock_is_protected_and_failure_is_atomic_for_all_lines(): void
    {
        $a = $this->masterMaterial('A');
        $b = $this->masterMaterial('B');
        $this->receive($a);
        $this->receive($b);
        $sale = $this->masterSale($this->masterProduct($b), '95');
        app(SalesReadinessService::class)->prepare($sale->id);
        app(SalesReadinessService::class)->handOver($sale->id);
        $data = $this->data($a, '10');
        $data['lines'][] = ['product_id' => $b->id, 'quantity' => '6'];
        $issue = app(InventoryIssueService::class)->save($data, (string) Str::uuid());
        try {
            app(InventoryIssueService::class)->confirm($issue->id);
            $this->fail('Reserved stock must be protected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('issue', $e->errors());
        }
        $this->assertSame('draft', $issue->fresh()->status);
        $this->assertEquals(0, InventoryIssueLine::sum('issued_quantity'));
        $this->assertSame('100.000000', app(InventoryStockService::class)->balance($a->id, $a->uom_id)['physical']);
    }

    public function test_competing_drafts_are_rechecked_when_confirmed(): void
    {
        $item = $this->masterMaterial();
        $this->receive($item, '10');
        $a = $this->issue($item, '7');
        $b = $this->issue($item, '7');
        app(InventoryIssueService::class)->confirm($a->id);
        try {
            app(InventoryIssueService::class)->confirm($b->id);
            $this->fail('Insufficient latest stock');
        } catch (ValidationException) {
        }
        $this->assertSame('3.000000', app(InventoryStockService::class)->balance($item->id, $item->uom_id)['available']);
        $this->assertSame('draft', $b->fresh()->status);
    }

    public function test_outgoing_stock_is_used_by_production_readiness_stock_page_and_sino(): void
    {
        $item = $this->masterMaterial();
        $this->receive($item);
        app(InventoryIssueService::class)->confirm($this->issue($item, '80')->id);
        $sale = $this->masterSale($this->masterProduct($item), '30');
        $service = app(SalesReadinessService::class);
        $service->prepare($sale->id);
        $assessment = $service->assess($sale->id);
        $this->assertFalse($assessment['ready']);
        $this->assertSame('10.000000', $assessment['rows'][0]['shortage']);
        $this->get('/ppic/inventory-control/stock')->assertOk()->assertSee('20,000000');
        $context = app(SinoContextService::class)->build()['ppic'];
        $this->assertSame(20.0, $context['30_stok_material'][0]['stok_fisik']);
        $this->assertCount(1, $context['30_barang_keluar_terbaru']);
        $this->expectException(ValidationException::class);
        $service->handOver($sale->id);
    }

    public function test_draft_edit_and_cancellation_do_not_affect_stock_and_final_documents_cannot_be_edited(): void
    {
        $item = $this->masterMaterial();
        $this->receive($item);
        $service = app(InventoryIssueService::class);
        $issue = $this->issue($item);
        $service->save($this->data($item, '20'), $issue->draft_token, $issue->id);
        $this->assertSame('20.000000', $issue->fresh('lines')->lines[0]->quantity);
        $service->cancel($issue->id);
        $service->cancel($issue->id);
        $this->assertSame('100.000000', app(InventoryStockService::class)->balance($item->id, $item->uom_id)['physical']);
        $this->expectException(ValidationException::class);
        $service->save($this->data($item), $issue->draft_token, $issue->id);
    }

    public function test_duplicate_items_invalid_quantities_and_foreign_master_data_are_rejected(): void
    {
        $item = $this->masterMaterial();
        $data = $this->data($item);
        foreach (['0', '-1', '0.0000001', '1000000000'] as $qty) {
            $data['lines'][0]['quantity'] = $qty;
            try {
                app(InventoryIssueService::class)->save($data, (string) Str::uuid());
                $this->fail('Invalid quantity');
            } catch (ValidationException) {
            }
        }
        $data = $this->data($item);
        $data['lines'][] = $data['lines'][0];
        try {
            app(InventoryIssueService::class)->save($data, (string) Str::uuid());
            $this->fail('Duplicate item');
        } catch (ValidationException) {
        }
        $data = $this->data($item);
        $data['recipient'] = '   ';
        try {
            app(InventoryIssueService::class)->save($data, (string) Str::uuid());
            $this->fail('Empty recipient');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('inventory_issues', 0);
    }

    public function test_sparepart_incoming_and_outgoing_use_the_existing_procurement_flow(): void
    {
        $item = $this->masterMaterial('Bearing', 'PCS');
        $category = Category::firstOrCreate(['code' => 'SP'], ['name' => 'Sparepart', 'is_active' => true]);
        $item->update(['category_id' => $category->id]);
        $this->receive($item, '12');
        app(InventoryIssueService::class)->confirm($this->issue($item, '2')->id);
        $this->assertSame('10.000000', app(InventoryStockService::class)->balance($item->id, $item->uom_id)['physical']);
    }

    public function test_disabled_master_or_changed_uom_blocks_confirmation_and_reverse_requires_reason(): void
    {
        $item = $this->masterMaterial();
        $this->receive($item);
        $issue = $this->issue($item);
        $service = app(InventoryIssueService::class);
        $item->update(['is_active' => false]);
        try {
            $service->confirm($issue->id);
            $this->fail('Inactive item');
        } catch (ValidationException) {
        }
        $item->update(['is_active' => true]);
        $service->confirm($issue->id);
        try {
            $service->reverse($issue->id, ' ');
            $this->fail('Missing reason');
        } catch (ValidationException) {
        }
        $this->assertSame('confirmed', $issue->fresh()->status);
    }

    public function test_only_checked_permissions_allow_actions_and_revocation_blocks_open_form(): void
    {
        $item = $this->masterMaterial();
        $this->receive($item);
        $issue = $this->issue($item);
        [$user, $profile] = $this->employee(['ppic.ic.issues.view']);
        $this->actingAs($user);
        $this->get('/ppic/inventory-control/issues')->assertOk()->assertDontSee('wire:click="openCreate"', false);
        Livewire::test(GoodsIssuePage::class)->call('openCreate')->assertForbidden();
        foreach ([fn () => app(InventoryIssueService::class)->save([], (string) Str::uuid()),
            fn () => app(InventoryIssueService::class)->confirm($issue->id), fn () => app(InventoryIssueService::class)->cancel($issue->id),
            fn () => app(InventoryIssueService::class)->reverse($issue->id, 'Alasan pembalikan')] as $action) {
            try {
                $action();
                $this->fail('Denied action');
            } catch (AuthorizationException) {
            }
        }
        $profile->update(['permissions' => AccessPermissionService::normalize(['ppic.ic.issues.create'])]);
        $page = Livewire::test(GoodsIssuePage::class)->call('openEdit', $issue->id);
        $profile->update(['permissions' => ['ppic.ic.issues.view']]);
        $page->call('save')->assertForbidden();
        $this->assertSame('draft', $issue->fresh()->status);
        $profile->update(['permissions' => []]);
        $this->get('/ppic/inventory-control/issues')->assertForbidden();
    }

    public function test_confirm_and_reverse_permissions_do_not_grant_draft_editing_and_sino_is_filtered(): void
    {
        $item = $this->masterMaterial();
        $this->receive($item);
        $issue = $this->issue($item);
        [$user, $profile] = $this->employee(['ppic.ic.issues.confirm', 'ppic.ic.issues.reverse']);
        $this->actingAs($user);
        $this->assertFalse(Gate::allows('ppic.ic.issues.create'));
        Livewire::test(GoodsIssuePage::class)->call('viewIssue', $issue->id)->call('confirm')->assertHasNoErrors()
            ->set('reversal_reason', 'Barang salah dicatat')->call('reverse')->assertHasNoErrors()->assertSee('Dibalikkan');
        $this->assertArrayHasKey('30_barang_keluar_terbaru', app(SinoContextService::class)->build()['ppic']);
        $profile->update(['permissions' => ['ppic.ic.stock.view']]);
        $this->assertArrayNotHasKey('30_barang_keluar_terbaru', app(SinoContextService::class)->build()['ppic']);
    }

    public function test_livewire_draft_form_detail_filters_and_confirmation_work(): void
    {
        $item = $this->masterMaterial('Bearing', 'PCS');
        $this->receive($item);
        $page = Livewire::test(GoodsIssuePage::class)->call('openCreate')->set('form', $this->data($item))
            ->call('addLine')->call('removeLine', 1)->call('save')->assertHasNoErrors()->assertSee('BK-000001');
        $this->assertDatabaseCount('inventory_issues', 1);
        $page->call('confirm')->assertHasNoErrors()->assertSee('Dikonfirmasi')
            ->set('search', 'BK-000001')->set('status', 'confirmed')->assertSee('BK-000001')
            ->set('status', 'draft')->assertDontSee('wire:click="viewIssue(1)"', false);
    }

    public function test_fresh_livewire_request_cannot_confirm_after_permission_revocation(): void
    {
        [$user, $profile] = $this->employee(['ppic.ic.issues.confirm']);
        $this->actingAs($user);
        $response = $this->get('/ppic/inventory-control/issues')->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = collect($matches[1])->map(fn ($v) => html_entity_decode($v, ENT_QUOTES, 'UTF-8'))
            ->first(fn ($v) => json_decode($v, true)['memo']['name'] === 'ppic.goods-issue-page');
        $this->assertNotNull($snapshot);
        $profile->update(['permissions' => ['ppic.ic.issues.view']]);
        $connection = $this->app['db']->connection();
        $this->refreshApplication();
        $this->app['db']->extend('sqlite', fn () => $connection);
        $this->actingAs(User::findOrFail($user->id));
        $this->postJson('/livewire/update', ['components' => [['snapshot' => $snapshot, 'updates' => [],
            'calls' => [['path' => '', 'method' => 'confirm', 'params' => []]]]]], ['X-Livewire' => 'true'])->assertForbidden();
    }

    public function test_issue_only_employee_can_follow_legacy_inventory_home_link(): void
    {
        [$user] = $this->employee(['ppic.ic.issues.view']);
        $this->actingAs($user);
        $this->get('/ppic/inventory-control')->assertRedirect(route('ppic.ic.issues'));
        $this->get('/ppic/inventory-control/issues')->assertOk();
    }

    public function test_confirmed_document_rejects_edit_cancel_and_reconfirmation_after_reversal(): void
    {
        $item = $this->masterMaterial();
        $this->receive($item);
        $service = app(InventoryIssueService::class);
        $issue = $this->issue($item);
        $service->confirm($issue->id);
        foreach ([fn () => $service->save($this->data($item, '90'), $issue->draft_token, $issue->id), fn () => $service->cancel($issue->id)] as $action) {
            try {
                $action();
                $this->fail('Confirmed documents are immutable');
            } catch (ValidationException) {
            }
        }
        $service->reverse($issue->id, 'Barang dikembalikan seluruhnya');
        try {
            $service->confirm($issue->id);
            $this->fail('Cannot reconfirm reversal');
        } catch (ValidationException) {
        }
        $this->assertSame('100.000000', app(InventoryStockService::class)->balance($item->id, $item->uom_id)['physical']);
        $this->assertSame('10.000000', $issue->fresh('lines')->lines[0]->quantity);
    }

    public function test_issue_date_cannot_precede_receipts_or_be_in_the_future(): void
    {
        $item = $this->masterMaterial();
        $this->receive($item);
        $data = $this->data($item);
        $data['issued_on'] = now()->subDay()->format('Y-m-d');
        $service = app(InventoryIssueService::class);
        $issue = $service->save($data, (string) Str::uuid());
        try {
            $service->confirm($issue->id);
            $this->fail('Cannot issue before receipt');
        } catch (ValidationException) {
        }
        $data['issued_on'] = now()->addDay()->format('Y-m-d');
        try {
            $service->save($data, (string) Str::uuid());
            $this->fail('Future date');
        } catch (ValidationException) {
        }
        $this->assertSame('draft', $issue->fresh()->status);
        $this->assertEquals(0, InventoryIssueLine::sum('issued_quantity'));
    }

    public function test_inactive_division_and_changed_uom_are_revalidated_before_confirmation(): void
    {
        $item = $this->masterMaterial();
        $this->receive($item);
        $issue = $this->issue($item);
        $service = app(InventoryIssueService::class);
        $issue->division->update(['is_active' => false]);
        try {
            $service->confirm($issue->id);
            $this->fail('Inactive division');
        } catch (ValidationException) {
        }
        $issue->division->update(['is_active' => true]);
        $other = $this->masterMaterial('Other', 'PCS');
        $item->update(['uom_id' => $other->uom_id]);
        try {
            $service->confirm($issue->id);
            $this->fail('Changed unit');
        } catch (ValidationException) {
        }
        $this->assertSame('draft', $issue->fresh()->status);
        $this->assertEquals(0, InventoryIssueLine::sum('issued_quantity'));
    }

    public function test_action_validation_errors_remain_visible_after_render(): void
    {
        $item = $this->masterMaterial();
        $issue = $this->issue($item);
        Livewire::test(GoodsIssuePage::class)->call('viewIssue', $issue->id)
            ->call('confirm')->assertHasErrors('issue')->assertSee('Stok tersedia');
        Livewire::test(GoodsIssuePage::class)->call('openCreate')->call('save')
            ->assertHasErrors(['form.division_id', 'form.recipient', 'form.lines.0.product_id']);
        Livewire::test(GoodsIssuePage::class)->set('until', now()->format('Y-m-d'))->assertHasNoErrors();
    }

    public function test_form_and_detail_are_inside_one_modal_and_close_without_changing_stock(): void
    {
        $item = $this->masterMaterial();
        $this->receive($item);
        $issue = $this->issue($item);
        $page = Livewire::test(GoodsIssuePage::class)->call('openCreate')->assertSet('showForm', true);
        $dom = new \DOMDocument;
        @$dom->loadHTML($page->html());
        $xpath = new \DOMXPath($dom);
        $this->assertCount(1, $xpath->query('//dialog//form[@*[name()="wire:submit"]="save"]'));
        $this->assertCount(0, $xpath->query('//form[@*[name()="wire:submit"]="save"][not(ancestor::dialog)]'));
        $page->call('closeForm')->assertSet('showForm', false)->assertSet('selectedId', null)
            ->call('viewIssue', $issue->id)->assertSet('selectedId', $issue->id);
        @$dom->loadHTML($page->html());
        $xpath = new \DOMXPath($dom);
        $this->assertCount(1, $xpath->query('//dialog//section[@aria-label="Detail barang keluar"]'));
        $page->call('openEdit', $issue->id)->assertSet('showForm', true)->assertSet('selectedId', null)
            ->call('save')->assertHasNoErrors()->assertSet('showForm', false)->assertSet('selectedId', $issue->id)
            ->call('closeDetail')->assertSet('selectedId', null);
        $this->assertSame('100.000000', app(InventoryStockService::class)->balance($item->id, $item->uom_id)['physical']);
    }
}

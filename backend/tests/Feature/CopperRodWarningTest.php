<?php

namespace Tests\Feature;

use App\Modules\Produksi\Livewire\BoardPage;
use App\Modules\Produksi\Livewire\CopperBreakPage;
use App\Modules\Produksi\Livewire\CrystallizerPage;
use App\Modules\Produksi\Livewire\OutputPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CopperRodWarningTest extends TestCase
{
    use RefreshDatabase;

    public function test_copper_rod_pages_include_accessible_warning_dialog_and_other_modules_do_not(): void
    {
        foreach (['/', '/crystallizer', '/copper-break', '/output'] as $path) {
            $this->get($path)->assertOk()->assertSee('role="alertdialog"', false)->assertSee('Mengerti');
        }
        $this->get('/ppic')->assertOk()->assertDontSee('role="alertdialog"', false);
    }

    public function test_board_validation_dispatches_all_messages_and_preserves_input(): void
    {
        Livewire::test(BoardPage::class)->call('openBoardForm')->set('lot', 'LOT-DRAFT')
            ->call('save')->assertHasErrors(['take_up', 'cooling'])
            ->assertSet('lot', 'LOT-DRAFT')->assertSet('showBoardForm', true)
            ->assertDispatched('notify', function ($event, $params) {
                $warning = $params[0];
                return $warning['type'] === 'error'
                    && in_array('No. Take Up wajib diisi.', $warning['messages'], true)
                    && in_array('Coiling wajib diisi.', $warning['messages'], true);
            });
        $this->assertDatabaseCount('board_entries', 0);
    }

    public function test_output_page_has_automatic_formula_without_manual_settings(): void
    {
        Livewire::test(OutputPage::class)->assertSee('Output MTD · Kalkulasi')->assertSee('Diameter, kecepatan, dan durasi LOT')
            ->assertDontSee('Atur rumus')->assertDontSee('Pengaturan rumus output')
            ->assertDontSeeHtml('wire:submit="saveFormula"');
        $this->assertDatabaseCount('output_formula_settings', 0);
    }

    public function test_invalid_rod_specifications_dispatch_warning_and_keep_board_input(): void
    {
        Livewire::test(BoardPage::class)->call('openBoardForm')->set('lot', 'LOT-SPEC-DRAFT')
            ->set('take_up_speed', '0')->set('rod_diameter_mm', '10')->call('save')
            ->assertHasErrors(['take_up_speed', 'rod_diameter_mm'])
            ->assertSet('lot', 'LOT-SPEC-DRAFT')->assertSet('showBoardForm', true)
            ->assertDispatched('notify', fn ($event, $params) => $params[0]['type'] === 'error' && count($params[0]['messages']) > 0);
        $this->assertDatabaseCount('board_entries', 0);
    }

    public function test_monitoring_pages_dispatch_warnings_through_shared_notification(): void
    {
        Livewire::test(CrystallizerPage::class)->set('startDate', '2026-10-28')->set('endDate', '2026-10-27')
            ->call('refreshData')->assertDispatched('notify', fn ($event, $params) => $params[0]['type'] === 'error');
        Livewire::test(CopperBreakPage::class)->call('openCopperForm')->call('save')
            ->assertSet('showCopperForm', true)
            ->assertDispatched('notify', fn ($event, $params) => $params[0]['type'] === 'error');
    }
}

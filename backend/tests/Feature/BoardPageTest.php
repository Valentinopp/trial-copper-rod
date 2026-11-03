<?php

namespace Tests\Feature;

use App\Modules\Produksi\Livewire\BoardPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BoardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_displays_wip_board(): void
    {
        $this->get('/')->assertOk()->assertSee('Digital WIP Coiling Board');
    }

    public function test_board_component_can_render(): void
    {
        Livewire::test(BoardPage::class)
            ->set('search', 'LOT-TEST')
            ->assertSee('Input data papan')
            ->assertSee('Riwayat papan');
    }

    public function test_all_module_pages_render_with_shared_navigation(): void
    {
        foreach (['/', '/crystallizer', '/copper-break', '/output', '/ppic', '/ppic/production-plan', '/ppic/inventory-control', '/marketing/sales', '/purchasing'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('Production Plan')->assertSee('aria-controls="ppic-submenu"', false)
                ->assertSee('Penjualan')->assertDontSee('Katalog Produk')->assertSee('aria-controls="purchasing-submenu"', false);
        }
    }

    public function test_other_modules_have_their_own_page_context(): void
    {
        $this->get('/marketing')->assertRedirectToRoute('marketing.sales');
        $this->get('/ppic')->assertOk()->assertSee('Modul PPIC')->assertSee('Tanya <strong>Sino</strong>', false);
        $this->get('/purchasing')->assertOk()->assertSee('Dalam tahap pengembangan.')->assertSee('Tanya <strong>Sino</strong>', false);
        $this->get('/marketing/sales')->assertOk()->assertSee('Penjualan')->assertDontSee('Katalog Produk')->assertSee('Tanya <strong>Sino</strong>', false);
    }

    public function test_module_components_can_update_on_a_separate_http_request(): void
    {
        foreach (['/', '/crystallizer', '/copper-break', '/output', '/ppic', '/ppic/production-plan', '/ppic/inventory-control', '/marketing/sales', '/purchasing'] as $path) {
            $page = $this->get($path)->assertOk();
            preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
            $snapshot = collect($matches[1])->map(fn ($value) => html_entity_decode($value, ENT_QUOTES, 'UTF-8'))
                ->first(fn ($value) => json_decode($value, true)['memo']['name'] !== 'ppic.attention-indicator');
            $this->assertNotNull($snapshot);

            // A fresh application prevents the initial render's component registry
            // from masking missing module registrations on the update request.
            $testDatabase = $this->app['db']->connection()->getPdo();
            $authenticatedUserId = auth()->id();
            $this->refreshApplication();
            $this->app['db']->connection()->setPdo($testDatabase);
            $this->actingAs(\App\Models\User::findOrFail($authenticatedUserId));

            $this->postJson('/livewire/update', [
                'components' => [[
                    'snapshot' => $snapshot,
                    'updates' => [],
                    'calls' => [],
                ]],
            ], ['X-Livewire' => 'true'])->assertOk()->assertJsonStructure(['components']);
        }
    }
}

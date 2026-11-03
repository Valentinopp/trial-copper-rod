<?php

namespace Tests\Feature;

use App\Modules\Produksi\Livewire\CrystallizerPage;
use App\Modules\Produksi\Models\BoardEntry;
use App\Modules\Produksi\Services\CrystallizerAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrystallizerPutusTest extends TestCase
{
    use RefreshDatabase;

    private function cycle(string $crystallizer, string $status, array $values = []): void
    {
        BoardEntry::create(array_replace([
            'board_date' => '2026-10-28', 'crystallizer' => $crystallizer,
            'upcasting_date' => '2026-10-28', 'upcasting_time' => '08:00',
            'actual_pickup_date' => '2026-10-28', 'actual_pickup_time' => '18:00',
            'event_status' => $status,
        ], $values));
    }

    public function test_putus_chart_counts_finished_cycles_filters_dates_and_sorts_independently_from_hours(): void
    {
        $this->cycle('A', 'panen');
        $this->cycle('A', 'panen');
        $this->cycle('A', 'putus');
        $this->cycle('A', 'putus', ['actual_pickup_date' => '', 'actual_pickup_time' => '']);
        $this->cycle('A', 'putus', ['board_date' => '2026-10-29', 'upcasting_date' => '2026-10-29', 'actual_pickup_date' => '2026-10-29']);
        $this->cycle('B', 'putus', ['actual_pickup_time' => '10:00']);
        $this->cycle('B', 'putus', ['actual_pickup_time' => '10:00']);
        $summary = collect(app(CrystallizerAnalyticsService::class)->getSummary('2026-10-28', '2026-10-28')['summary'])->keyBy('crystallizer');
        $this->assertSame(3, $summary['A']['frequency']);
        $this->assertSame(1, $summary['A']['putus_count']);
        $this->assertSame(33.33, $summary['A']['putus_percentage']);
        $this->assertSame(2, $summary['B']['putus_count']);
        $this->assertEquals(100, $summary['B']['putus_percentage']);
        $page = Livewire::test(CrystallizerPage::class)->set('startDate', '2026-10-28')->set('endDate', '2026-10-28')
            ->assertSee('Kejadian Putus')->assertDontSee('Visual Total Jam')
            ->assertViewHas('putusBars', fn ($rows) => $rows[0]['crystallizer'] === 'B')
            ->assertViewHas('totalHoursBars', fn ($rows) => $rows[0]['crystallizer'] === 'A');
        $page->call('exportExcel')->assertDispatched('export-excel', function ($event, $params) {
            $payload = $params[0];
            return count($payload['headers']) === count($payload['rows'][0])
                && $payload['rows'][0][5] === 1 && $payload['rows'][0][6] === 33.33;
        });
        $page->call('exportPNG')->assertDispatched('export-png', fn ($event, $params) => count($params[0]['headers']) === count($params[0]['rows'][0]));
    }

    public function test_empty_chart_and_cycles_without_putus_render_without_division_by_zero(): void
    {
        Livewire::test(CrystallizerPage::class)->assertOk()->assertViewHas('putusBars', []);
        $this->cycle('A', 'panen');
        Livewire::test(CrystallizerPage::class)->assertOk()
            ->assertViewHas('putusBars', fn ($rows) => $rows[0]['putus_count'] === 0 && (float) $rows[0]['putus_percentage'] === 0.0);
    }
}

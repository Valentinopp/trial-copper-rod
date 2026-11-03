<?php

namespace App\Modules\Produksi\Livewire;

use App\Modules\Produksi\Services\CrystallizerAnalyticsService;
use App\Modules\Produksi\Services\WipFormatter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Monitoring Crystallizer — WIP Board')]
class CrystallizerPage extends Component
{
    public string $startDate = '';
    public string $endDate = '';

    public function refreshData(): void
    {
        if ($this->startDate !== '' && $this->endDate !== '' && $this->endDate < $this->startDate) {
            $this->dispatch('notify', [
                'message' => 'Tanggal akhir tidak boleh sebelum tanggal awal',
                'type'    => 'error',
            ]);
        }
    }

    protected function getData(): array
    {
        if ($this->startDate !== '' && $this->endDate !== '' && $this->endDate < $this->startDate) {
            return ['items' => [], 'summary' => [], 'total_crystallizers' => 0, 'total_operating_hours' => 0];
        }

        $service = app(CrystallizerAnalyticsService::class);
        return $service->getSummary(
            $this->startDate !== '' ? $this->startDate : null,
            $this->endDate !== '' ? $this->endDate : null
        );
    }

    public function exportExcel(): void
    {
        $data = $this->getData();
        $summary = $data['summary'] ?? [];
        if (empty($summary)) {
            return;
        }

        $headers = ['No Crystallizer', 'Total Jam Lifetime', 'Frek. Pasang*', 'Rata-rata Lifetime (jam)', 'Jumlah Baris Papan', 'Kejadian Putus', 'Putus dari Siklus Lengkap (%)'];
        $rows = array_map(fn($item) => [
            $item['crystallizer'],
            $item['total_hours'],
            $item['frequency'],
            $item['average_lifetime_hours'] ?? '',
            $item['board_rows'],
            $item['putus_count'],
            $item['putus_percentage'],
        ], $summary);

        $s = $this->startDate ?: 'awal';
        $e = $this->endDate ?: 'terbaru';

        $this->dispatch('export-excel', [
            'filename'  => "rekap-crystallizer-{$s}-{$e}.xlsx",
            'sheetName' => 'Crystallizer',
            'headers'   => $headers,
            'rows'      => $rows,
        ]);
    }

    public function exportPNG(): void
    {
        $data = $this->getData();
        $summary = $data['summary'] ?? [];
        if (empty($summary)) {
            return;
        }

        $headers = ['No. Crystallizer', 'Total Jam Lifetime', 'Frekuensi Pasang', 'Rata-rata Lifetime (jam)', 'Jumlah Baris Papan', 'Kejadian Putus', 'Putus dari Siklus Lengkap (%)'];
        $rows = array_map(fn($item) => [
            $item['crystallizer'],
            WipFormatter::number($item['total_hours'], 2),
            WipFormatter::number($item['frequency'], 0),
            $item['average_lifetime_hours'] === null ? '—' : WipFormatter::number($item['average_lifetime_hours'], 2),
            WipFormatter::number($item['board_rows'], 0),
            WipFormatter::number($item['putus_count'], 0),
            WipFormatter::number($item['putus_percentage'], 2).'%',
        ], $summary);

        $s = $this->startDate ?: 'awal';
        $e = $this->endDate ?: 'terbaru';
        $period = ($this->startDate ? WipFormatter::prettyDate($this->startDate) : 'Awal data') . ' – ' . ($this->endDate ? WipFormatter::prettyDate($this->endDate) : 'Terbaru');

        $this->dispatch('export-png', [
            'filename' => "rekap-crystallizer-{$s}-{$e}.png",
            'title'    => 'Monitoring Crystallizer',
            'period'   => $period,
            'headers'  => $headers,
            'rows'     => $rows,
        ]);
    }

    public function render()
    {
        $data = $this->getData();
        $summary = $data['summary'] ?? [];

        // Sort untuk masing-masing bar chart seperti CrystalMetricBars di App.jsx
        $sortByKey = function (array $items, string $key): array {
            usort($items, function ($a, $b) use ($key) {
                $av = (float) ($a[$key] ?? -1);
                $bv = (float) ($b[$key] ?? -1);
                if ($bv !== $av) {
                    return $bv <=> $av;
                }
                return strnatcasecmp((string) $a['crystallizer'], (string) $b['crystallizer']);
            });
            return $items;
        };

        $totalHoursBars = $sortByKey($summary, 'total_hours');
        $frequencyBars = $sortByKey($summary, 'frequency');
        $avgBars = $sortByKey(array_values(array_filter($summary, fn($i) => $i['average_lifetime_hours'] !== null)), 'average_lifetime_hours');

        return view('modules.produksi.livewire.crystallizer-page', [
            'items'          => $data['items'] ?? [],
            'summary'        => $summary,
            'totalHoursBars' => $totalHoursBars,
            'frequencyBars'  => $frequencyBars,
            'avgBars'        => $avgBars,
            'putusBars'      => $sortByKey($summary, 'putus_count'),
        ]);
    }
}

<?php

namespace App\Modules\Produksi\Livewire;

use App\Modules\Produksi\Models\BoardEntry;
use App\Modules\Produksi\Services\OutputSummaryService;
use App\Modules\Produksi\Services\OutputFormulaService;
use App\Modules\Produksi\Services\ServoInferenceService;
use App\Modules\Produksi\Services\ProductionRuntimeService;
use App\Modules\Produksi\Services\ProductionPlanIntegrationService;
use App\Modules\Produksi\Services\MonthlyOutputReportService;
use App\Modules\Produksi\Services\StandaloneOutputService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Rekap Output per Shift — WIP Board')]
class OutputPage extends Component
{
    public string $month = '';
    public string $target = '';

    public function mount(): void
    {
        $this->target = (string) ServoInferenceService::DEFAULT_DAILY_TARGET_MT;

        // Default ke bulan dari catatan papan terbaru agar data historis langsung terlihat (sesuai App.jsx)
        $latestBoardDate = BoardEntry::orderByDesc('board_date')->orderByDesc('id')->value('board_date');
        if (config('copper_rod.standalone')) {
            $lastPickup = BoardEntry::whereNotNull('production_actual_kg')->orderByDesc('actual_pickup_date')
                ->orderByDesc('actual_pickup_time')->first();
            $pickup = $lastPickup ? ProductionRuntimeService::timestamp($lastPickup->actual_pickup_date, $lastPickup->actual_pickup_time) : null;
            if ($pickup) $latestBoardDate = max($latestBoardDate ?? '', ProductionRuntimeService::productionSlot($pickup)['date']);
        }
        $hasRunningLot = BoardEntry::where('upcasting_date', '<>', '')
            ->where('upcasting_time', '<>', '')
            ->where('actual_pickup_date', '')->where('actual_pickup_time', '')
            ->whereNotIn('event_status', [...ProductionRuntimeService::END_STATUSES, 'panen_stop', 'panen_continue', 'putus_tarik_lot_baru'])->exists();
        $productionDate = ProductionRuntimeService::productionSlot(Carbon::now(ProductionRuntimeService::TIMEZONE))['date'];
        if ($hasRunningLot) {
            $this->month = substr($productionDate, 0, 7);
        } elseif ($latestBoardDate && strlen($latestBoardDate) >= 7) {
            $this->month = substr($latestBoardDate, 0, 7);
        } else {
            $this->month = Carbon::now('Asia/Jakarta')->format('Y-m');
        }
    }

    public function recalculate(): void
    {
    }

    public function moveMonth(int $direction): void
    {
        $valid = \Illuminate\Support\Facades\Validator::make(['month' => $this->month], ['month' => 'required|date_format:Y-m'])->passes();
        $date = $valid ? Carbon::createFromFormat('!Y-m', $this->month) : Carbon::now(ProductionRuntimeService::TIMEZONE)->startOfMonth();
        $this->month = $date->addMonths($direction < 0 ? -1 : 1)->format('Y-m');
    }

    protected function getSummaryData(): array
    {
        if (!\Illuminate\Support\Facades\Validator::make(['month' => $this->month], ['month' => 'required|date_format:Y-m'])->passes()) {
            return ['days' => []];
        }

        $startDate = "{$this->month}-01";
        $endDate = Carbon::createFromFormat('Y-m-d', $startDate)->endOfMonth()->format('Y-m-d');
        $dailyTarget = is_numeric($this->target) && (float) $this->target >= 0
            ? (float) $this->target
            : ServoInferenceService::DEFAULT_DAILY_TARGET_MT;

        $data = app(OutputSummaryService::class)->getSummary($startDate, $endDate, $dailyTarget);
        $comparison = config('copper_rod.standalone')
            ? app(StandaloneOutputService::class)->dailyActual($startDate, $endDate)
            : app(ProductionPlanIntegrationService::class)->dailyOutputComparison($startDate, $endDate);
        $data['days'] = array_map(fn ($day) => $day + $comparison->get($day['date'], [
            'plan_count' => 0, 'unconverted_plans' => 0, 'plan_mt' => null, 'actual_mt' => config('copper_rod.standalone') ? '0' : null,
            'difference_mt' => null, 'plan_achievement_pct' => null,
        ]), $data['days']);
        $data['days'] = app(MonthlyOutputReportService::class)->days($data['days']);

        return $data;
    }

    public function exportExcel(): void
    {
        $this->exportReport(false);
    }

    public function exportPNG(): void
    {
        $this->exportReport(true);
    }

    protected function exportReport(bool $formatted): void
    {
        $days = $this->getSummaryData()['days'] ?? [];
        if (!$days) {
            return;
        }
        $label = Carbon::parse($days[0]['date'])->locale('id')->translatedFormat('F Y');
        $reportRows = app(MonthlyOutputReportService::class)->rows($days);
        $headers = ['SHIFT', 'INDIKATOR — '.$label, ...array_map(fn ($day) => (int) substr($day['date'], 8, 2), $days)];
        $rows = array_map(fn ($row) => [$row['shift'], $row['label'], ...array_map(
            fn ($value) => $formatted ? MonthlyOutputReportService::format($value, $row['kind'] === 'percentage')
                : ($value === null ? null : (float) $value), $row['values'])], $reportRows);

        $payload = ['filename' => 'rekap-output-wip-'.$this->month.($formatted ? '.png' : '.xlsx'),
            'headers' => $headers, 'rows' => $rows];
        if ($formatted) {
            $payload += ['title' => 'Rekap Output Bulanan', 'period' => $label];
        } else {
            $payload['sheetName'] = 'Rekap Output';
            $payload['format'] = 'monthly-output';
            $payload['monthLabel'] = $label;
            $payload['rowKinds'] = array_column($reportRows, 'kind');
            $payload['todayColumn'] = array_search(ProductionRuntimeService::productionSlot(Carbon::now(ProductionRuntimeService::TIMEZONE))['date'], array_column($days, 'date'), true);
        }
        $this->dispatch($formatted ? 'export-png' : 'export-excel', $payload);
    }

    public function render()
    {
        $data = $this->getSummaryData();
        $days = $data['days'] ?? [];

        $totals = [
            'output_day_mt' => 0.0,
            'output_mtd_mt' => 0.0,
            'target_mtd_mt' => 0.0,
            'S1'            => 0.0,
            'S2'            => 0.0,
            'S3'            => 0.0,
        ];

        foreach ($days as $day) {
            $totals['output_day_mt'] += (float) ($day['output_day_mt'] ?? 0);
            $totals['output_mtd_mt'] = (float) ($day['output_mtd_mt'] ?? 0);
            $totals['target_mtd_mt'] = (float) ($day['target_mtd_mt'] ?? 0);
            foreach (ServoInferenceService::SHIFTS as $s) {
                $totals[$s] += (float) ($day['shifts'][$s]['output_mt'] ?? 0);
            }
        }

        $todayIso = ProductionRuntimeService::productionSlot(Carbon::now(ProductionRuntimeService::TIMEZONE))['date'];
        $currentMonth = substr($todayIso, 0, 7);

        $asOfDay = null;
        if (!empty($days)) {
            if ($this->month === $currentMonth) {
                $asOfDay = collect($days)->firstWhere('date', $todayIso) ?? end($days);
            } else {
                $asOfDay = end($days);
            }
        }

        return view('modules.produksi.livewire.output-page', [
            'standalone' => (bool) config('copper_rod.standalone'),
            'reportRows' => app(MonthlyOutputReportService::class)->rows($days),
            'monthLabel' => $days ? Carbon::parse($days[0]['date'])->locale('id')->translatedFormat('F Y') : 'Pilih bulan laporan',
            'formula' => $data['formula'] ?? OutputFormulaService::formula(),
            'days'     => $days,
            'totals'   => $totals,
            'asOfDay'  => $asOfDay,
            'todayIso' => $todayIso,
            'shifts'   => ServoInferenceService::SHIFTS,
            'hasLegacySpecifications' => ($data['legacy_specification_rows'] ?? 0) > 0,
        ]);
    }
}

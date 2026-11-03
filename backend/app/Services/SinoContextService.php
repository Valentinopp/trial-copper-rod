<?php

namespace App\Services;

use App\Modules\MasterData\Models\Item;
use App\Modules\Marketing\Models\SalesOrder;
use App\Modules\Ppic\Models\ProductionHandoff;
use App\Modules\Ppic\Models\ProductionSchedule;
use App\Modules\Ppic\Services\ProductionPlanningService;
use Brick\Math\BigDecimal;
use App\Modules\Ppic\Models\PurchaseRequest;
use App\Modules\Produksi\Enums\EventStatus;
use App\Modules\Produksi\Models\BoardEntry;
use App\Modules\Produksi\Services\CopperBreakService;
use App\Modules\Produksi\Services\CrystallizerAnalyticsService;
use App\Modules\Produksi\Services\LotSequenceService;
use App\Modules\Produksi\Services\OutputFormulaService;
use App\Modules\Purchasing\Models\PurchaseOrder;

class SinoContextService
{
    public function build(): array
    {
        $scope = app(SinoScopeService::class)->current()['key'];
        return match ($scope) {
            'ppic' => ['ppic' => $this->ppic()],
            'marketing' => ['marketing' => $this->marketing()],
            'purchasing' => ['purchasing' => $this->purchasing()],
            'erp' => ['produksi' => $this->production(), 'ppic' => $this->ppic()]
                + (\Illuminate\Support\Facades\Gate::allows('marketing.sales.view') ? ['marketing' => $this->marketing()] : [])
                + (\Illuminate\Support\Facades\Gate::allows('access-purchasing') ? ['purchasing' => $this->purchasing()] : []),
            default => abort(403),
        };
    }

    private function production(): array
    {
        $rows = BoardEntry::latest('board_date')->latest('id')->limit(30)->get();
        $recent = app(LotSequenceService::class)->decorate($rows)->map(fn ($row) => [
            'tanggal' => $row->board_date, 'shift' => $row->shift, 'lot' => $row->lot_sequence,
            'lot_ke' => $row->lot_position, 'take_up' => $row->take_up, 'coiling' => $row->cooling,
            'speed_mm_per_menit' => $row->take_up_speed, 'diameter_mm' => $row->rod_diameter_mm,
            'servo' => $row->effective_servo, 'crystallizer' => $row->crystallizer,
            'jam_operasi' => $row->operating_hours, 'berat_kg' => $row->weight_kg,
            'status' => EventStatus::label($row->event_status),
        ])->all();
        $breaks = app(CopperBreakService::class)->listEntries(null, null, '', 20);
        return [
            'total_catatan' => BoardEntry::count(), '30_catatan_terbaru' => $recent,
            'ringkasan_crystallizer' => array_slice(app(CrystallizerAnalyticsService::class)->getSummary()['summary'], 0, 15),
            'total_copper_putus' => $breaks['total'], '20_copper_putus_terbaru' => $breaks['items'],
            'rumus_output' => OutputFormulaService::formula(),
        ];
    }

    private function ppic(): array
    {
        $result = [];
        if (\Illuminate\Support\Facades\Gate::allows('ppic.ic.stock.view')) {
            $materials = app(\App\Modules\Ppic\Services\InventoryStockService::class)->withBalances(app(\App\Modules\Purchasing\Services\ProcurementService::class)->materials())
                ->orderBy('name')->limit(30)->get()->map(fn ($row) => [
                    'material' => $row->name, 'satuan' => $row->unit->code,
                    'diterima' => (float) $row->receipts_sum_quantity,
                    'dikeluarkan_bersih' => (float) $row->issue_lines_sum_quantity,
                    'stok_fisik' => (float) app(\App\Modules\Ppic\Services\InventoryStockService::class)->physical($row),
                    'direservasi' => (float) $row->reservations_sum_quantity,
                    'tersedia' => (float) app(\App\Modules\Ppic\Services\InventoryStockService::class)->available($row),
                ])->all();
            $result += ['total_material' => app(\App\Modules\Purchasing\Services\ProcurementService::class)->materials()->count(), '30_stok_material' => $materials];
        }
        // Production Plan remains governed by its existing role until its own integration.
        if (\Illuminate\Support\Facades\Gate::allows('access-ppic')) {
            $plans = ProductionHandoff::with('sale')->withSum(['schedules as scheduled_quantity' => fn ($q) => $q->where('status', 'scheduled')], 'quantity')->latest('id')->limit(30)->get()->map(fn ($row) => [
                'referensi' => $row->reference, 'referensi_penjualan' => $row->sale->reference,
                'produk' => $row->sale->product_snapshot['name'], 'jumlah' => $row->sale->quantity, 'satuan' => $row->sale->unit_snapshot['code'],
                'batas_pengiriman' => $row->sale->ship_by->format('Y-m-d'), 'diserahkan_pada' => $row->handed_over_at->toDateTimeString(),
                'jumlah_terjadwal' => $row->scheduled_quantity ?? '0', 'sisa_belum_dijadwalkan' => $row->remaining_quantity,
            ])->all();
            $result += ['total_production_plan' => ProductionHandoff::count(), '30_production_plan_terbaru' => $plans];
            $today = now(ProductionPlanningService::TIMEZONE)->format('Y-m-d');
            $schedules = ProductionSchedule::with('handoff.sale')->where('status', 'scheduled')->latest('planned_on')->latest('id')->limit(30)->get();
            $daily = ProductionSchedule::with('handoff.sale')->where('status', 'scheduled')->where('planned_on', $today)->get();
            $result += ['tanggal_rencana_hari_ini' => $today,
                'rencana_produksi_hari_ini' => $daily->groupBy(fn ($row) => $row->handoff->sale->uom_id)->map(fn ($rows) => [
                    'satuan' => $rows->first()->handoff->sale->unit_snapshot['code'],
                    'jumlah' => (string) $rows->reduce(fn (BigDecimal $sum, $row) => $sum->plus($row->quantity), BigDecimal::of(0)),
                ])->values()->all(),
                '30_jadwal_produksi_terbaru' => $schedules->map(fn ($row) => [
                    'jadwal' => $row->reference, 'penjualan' => $row->handoff->sale->reference,
                    'produk' => $row->handoff->sale->product_snapshot['name'], 'tanggal_produksi' => $row->planned_on->format('Y-m-d'),
                    'jumlah' => $row->quantity, 'satuan' => $row->handoff->sale->unit_snapshot['code'],
                ])->all(),
            ];
        }
        if (\Illuminate\Support\Facades\Gate::allows('ppic.ic.sales.view')) {
            $pendingSales = SalesOrder::whereDoesntHave('productionHandoff')->with('materialRequirements')->latest('id')->limit(30)->get()->map(fn ($row) => [
                'referensi' => $row->reference, 'produk' => $row->product_snapshot['name'], 'jumlah' => $row->quantity, 'satuan' => $row->unit_snapshot['code'],
                'batas_pengiriman' => $row->ship_by->format('Y-m-d'),
                'kebutuhan_material' => $row->materialRequirements->map(fn ($material) => ['material' => $material->material_name, 'satuan' => $material->uom, 'jumlah' => $material->quantity])->all(),
            ])->all();
            $result += ['total_penjualan_belum_diserahkan' => SalesOrder::whereDoesntHave('productionHandoff')->count(), '30_kesiapan_penjualan_untuk_produksi' => $pendingSales];
        }
        if (\Illuminate\Support\Facades\Gate::allows('ppic.ic.requests.view')) {
            $result['30_pengajuan_barang_terbaru'] = $this->requests(false);
        }
        if (\Illuminate\Support\Facades\Gate::allows('ppic.ic.issues.view')) {
            $result['30_barang_keluar_terbaru'] = \App\Modules\Ppic\Models\InventoryIssue::with('lines')->latest('id')->limit(30)->get()
                ->map(fn ($issue) => ['referensi' => $issue->reference, 'tanggal' => $issue->issued_on->format('Y-m-d'),
                    'status' => $issue->status_label, 'keperluan' => $issue->purpose, 'divisi' => $issue->division_name,
                    'penerima' => $issue->recipient, 'referensi_permintaan' => $issue->request_reference,
                    'barang' => $issue->lines->map(fn ($line) => ['nama' => $line->product_name, 'jumlah' => $line->quantity, 'satuan' => $line->uom_code])->all()])->all();
        }
        return $result;
    }
    private function marketing(): array
    {
        $products = $this->products()->with(['unit', 'boms.lines.material.unit'])->latest('id')->limit(30)->get()->map(fn ($row) => [
            'referensi' => $row->code, 'nama' => $row->name, 'satuan' => $row->unit->code, 'deskripsi' => $row->description,
            'bom' => $row->boms->map(fn ($bom) => [
                'kode' => $bom->code, 'nama' => $bom->name, 'aktif' => $bom->is_active, 'hasil_acuan' => $bom->output_quantity,
                'material' => $bom->lines->map(fn ($line) => ['material' => $line->material->name,
                    'jumlah' => $line->quantity, 'satuan' => $line->material->unit->code])->all(),
            ])->all(),
        ])->all();
        $sales = SalesOrder::latest('id')->limit(30)->get()->map(fn ($row) => [
            'referensi' => $row->reference, 'pelanggan' => $row->customer_snapshot['name'], 'produk' => $row->product_snapshot['name'],
            'jumlah' => $row->quantity, 'satuan' => $row->unit_snapshot['code'], 'tanggal_penjualan' => $row->sold_on->format('Y-m-d'),
            'batas_pengiriman' => $row->ship_by->format('Y-m-d'),
        ])->all();
        return [
            'total_produk' => $this->products()->count(), '30_produk_terbaru' => $products,
            'total_penjualan' => SalesOrder::count(),
            'total_penjualan_per_satuan' => SalesOrder::selectRaw('uom_id, SUM(quantity) as total')->with('unit')->groupBy('uom_id')->get()
                ->map(fn ($row) => ['satuan' => $row->unit?->code, 'jumlah' => $row->total])->all(),
            '30_penjualan_terbaru' => $sales,
            '30_master_material' => $this->materials()->with('unit')->orderBy('name')->limit(30)->get()
                ->map(fn ($row) => ['name' => $row->name, 'uom' => $row->unit->code])->all(),
        ];
    }

    private function purchasing(): array
    {
        $context = [];
        if (\Illuminate\Support\Facades\Gate::allows('purchasing.requests.view')) {
            $context['total_pengajuan_belum_dipesan'] = PurchaseRequest::whereDoesntHave('order')->count();
            $context['30_pengajuan_belum_dipesan'] = $this->requests(true);
        }
        if (\Illuminate\Support\Facades\Gate::allows('purchasing.orders.view')) {
            $context['total_pesanan_pembelian'] = PurchaseOrder::count();
            $context['30_pesanan_terbaru'] = PurchaseOrder::with(['request.product', 'request.unit'])->withSum('receipts', 'quantity')->latest('id')->limit(30)->get()
                ->map(fn ($row) => [
                    'referensi' => $row->reference, 'referensi_pengajuan' => $row->request->reference,
                    'material' => $row->request->product->name, 'satuan' => $row->request->unit->code,
                    'jumlah' => $row->request->quantity, 'diterima' => (float) $row->receipts_sum_quantity,
                    'sisa_pesanan' => $row->remaining, 'status' => $row->status,
                    'dipesan_pada' => $row->ordered_on->format('Y-m-d'), 'estimasi_datang' => $row->expected_on->format('Y-m-d'),
                ])->all();
        }
        return $context;
    }
    private function requests(bool $pendingOnly): array
    {
        $query = PurchaseRequest::with('product', 'unit', 'order');
        if ($pendingOnly) {
            $query->whereDoesntHave('order');
        }
        return $query->latest('id')->limit(30)->get()->map(fn ($row) => [
            'referensi' => $row->reference, 'material' => $row->product->name, 'satuan' => $row->unit->code,
            'jumlah' => $row->quantity, 'dibutuhkan_pada' => $row->required_on->format('Y-m-d'),
            'referensi_pesanan' => $row->order?->reference,
        ])->all();
    }

    private function products(): \Illuminate\Database\Eloquent\Builder
    {
        return Item::whereHas('category', fn ($q) => $q->whereRaw('LOWER(name) = ?', ['produk']));
    }

    private function materials(): \Illuminate\Database\Eloquent\Builder
    {
        return Item::whereHas('category', fn ($q) => $q->whereRaw('LOWER(name) = ?', ['material'])
            ->orWhereHas('parent', fn ($parent) => $parent->whereRaw('LOWER(name) = ?', ['material'])));
    }
}

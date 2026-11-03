<?php

namespace App\Services;

use Illuminate\Support\Facades\Gate;

class SinoScopeService
{
    public function current(): array
    {
        Gate::authorize('use-sino');
        $scope = auth()->user()->role->sinoScope();
        return match ($scope) {
            'ppic' => [
                'key' => $scope, 'label' => 'PPIC',
                'topics' => 'Production Plan, Inventory Control, stok, reservasi, kebutuhan material, kesiapan penjualan untuk produksi, pengajuan, penerimaan, dan pengeluaran barang Maintenance/Operasional di IC',
                'suggestions' => ['Ringkas stok material yang tersedia', 'Ringkas Production Plan terbaru', 'Apa pengajuan barang yang masih diproses?'],
            ],
            'marketing' => [
                'key' => $scope, 'label' => 'Marketing',
                'topics' => 'katalog produk, spesifikasi Cu Rod, BoM, katalog material, penjualan, pelanggan, kuantitas dan jadwal pengiriman penjualan',
                'suggestions' => ['Ringkas penjualan terbaru', 'Apa saja produk dalam katalog?', 'Ringkas jadwal pengiriman penjualan'],
            ],
            'purchasing' => [
                'key' => $scope, 'label' => 'Purchasing',
                'topics' => implode(', ', array_filter([
                    Gate::allows('purchasing.requests.view') ? 'pengajuan pembelian yang belum dipesan' : null,
                    Gate::allows('purchasing.orders.view') ? 'purchase order, tanggal pemesanan, estimasi kedatangan, jumlah barang diterima dan sisa pesanan pembelian' : null,
                ])),
                'suggestions' => array_merge(
                    Gate::allows('purchasing.requests.view') ? ['Apa pengajuan pembelian yang belum dipesan?'] : [],
                    Gate::allows('purchasing.orders.view') ? ['Ringkas pesanan pembelian terbaru', 'Pesanan mana yang belum diterima lengkap?'] : [],
                ),
            ],
            'erp' => [
                'key' => 'erp', 'label' => 'Seluruh modul ERP',
                'topics' => 'Produksi Copper Rod, PPIC, Marketing, Purchasing, dan keterkaitan alur antarmodul ERP',
                'suggestions' => ['Ringkas kondisi modul Produksi', 'Ringkas stok dan Production Plan', 'Ringkas penjualan dan pesanan pembelian'],
            ],
            default => abort(403),
        };
    }

    public function outsideScope(string $question): bool
    {
        $scope = $this->current()['key'];
        if ($scope === 'erp') {
            return false;
        }
        // Explicit requests for another module are rejected before any ERP context is built.
        if (preg_match_all('/\bmodul\s+(produksi|ppic|marketing|purchasing)\b/iu', $question, $matches)) {
            foreach ($matches[1] as $module) {
                if (mb_strtolower($module) !== $scope) {
                    return true;
                }
            }
        }
        if (preg_match('/\b(crystallizer|kristalizer|upcasting|servo|coiling|wip)\b|copper\s+putus|take\s*up|papan\s+digital|output\s+(?:per\s+)?shift/iu', $question)) {
            return true;
        }
        $pattern = match ($scope) {
            'marketing' => '/\b(ppic|purchasing|inventory|inventori|persediaan|stok|stock|reservasi|pembelian|pengadaan|supplier|pemasok)\b/iu',
            'purchasing' => '/\b(marketing|penjualan|pelanggan|customer|persediaan|stok|stock|reservasi)\b|production\s+plan|rencana\s+produksi/iu',
            'ppic' => '/\b(marketing|purchasing|supplier|pemasok)\b|katalog\s+produk/iu',
        };
        return (bool) preg_match($pattern, $question);
    }
}

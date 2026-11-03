<?php
namespace App\Modules\Ppic\Services;
use App\Modules\Marketing\Models\SalesOrder;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Facades\Gate;

class PpicAttentionService
{
    public function for(string $kind): array
    {
        $sales = $kind !== 'incoming' && Gate::allows('ppic.ic.sales.view')
            ? SalesOrder::whereDoesntHave('productionHandoff')->count() : 0;
        $incoming = $kind !== 'sales' && Gate::allows('ppic.ic.receipts.view')
            ? PurchaseOrder::join('purchase_requests', 'purchase_requests.id', '=', 'purchase_orders.purchase_request_id')
                ->whereRaw('purchase_requests.quantity > COALESCE((SELECT SUM(inventory_receipts.quantity) FROM inventory_receipts WHERE inventory_receipts.purchase_order_id = purchase_orders.id), 0)')->count() : 0;
        return match ($kind) {
            'sales' => ['count' => $sales, 'label' => $sales.' penjualan perlu ditindaklanjuti IC sebelum diserahkan ke Production Plan'],
            'incoming' => ['count' => $incoming, 'label' => $incoming.' pesanan belum diterima lengkap'],
            default => ['count' => $sales + $incoming, 'label' => $sales.' penjualan perlu ditindaklanjuti dan '.$incoming.' pesanan belum diterima lengkap'],
        };
    }
}
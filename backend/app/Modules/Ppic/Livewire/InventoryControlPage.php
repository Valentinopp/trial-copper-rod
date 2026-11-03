<?php
namespace App\Modules\Ppic\Livewire;
use App\Modules\Marketing\Models\SalesOrder;
use App\Modules\MasterData\Models\Bom;
use App\Modules\MasterData\Models\Item;
use App\Modules\Ppic\Models\InventoryReceipt;
use App\Modules\Ppic\Models\PurchaseRequest;
use App\Modules\Ppic\Services\SalesReadinessService;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Services\ProcurementService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Inventory Control — irris.next')]
class InventoryControlPage extends Component
{
    use WithPagination;
    #[Locked] public string $tab = 'requests';
    public string $product_id = '';
    public string $quantity = '';
    public string $required_on = '';
    public string $received_quantity = '';
    public string $received_on = '';
    public string $materials_required_on = '';
    public array $bomSelections = [];
    #[Locked] public ?int $selectedSaleId = null;
    #[Locked] public ?int $selectedOrderId = null;
    #[Locked] public string $receiptToken = '';

    #[Locked] public ?string $pageTab = null;

    public function mount(): void
    {
        $this->pageTab = match (request()->route()?->getName()) {
            'ppic.ic.sales' => 'sales',
            'ppic.ic.requests' => 'requests',
            'ppic.ic.receipts' => 'in',
            'ppic.ic.stock' => 'stock',
            default => null,
        };
        if ($this->pageTab !== null) {
            Gate::authorize($this->tabPermissions()[$this->pageTab]);
            $this->tab = $this->pageTab;
        }
    }

    public function boot(): void { Gate::authorize('access-ic'); }

    protected function tabPermissions(): array
    {
        return ['requests' => 'ppic.ic.requests.view', 'sales' => 'ppic.ic.sales.view', 'in' => 'ppic.ic.receipts.view', 'stock' => 'ppic.ic.stock.view'];
    }

    public function setTab(string $tab): void
    {
        if (! isset($this->tabPermissions()[$tab])) return;
        Gate::authorize($this->tabPermissions()[$tab]);
        if ($this->pageTab !== null && $tab !== $this->pageTab) {
            $slug = $tab === 'in' ? 'receipts' : $tab;
            $this->redirectRoute('ppic.ic.'.$slug, navigate: true);
            return;
        }
        $this->tab = $tab;
        $this->resetErrorBag();
    }

    public function viewSale(int $id): void
    {
        Gate::authorize('ppic.ic.sales.view');
        $sale = SalesOrder::findOrFail($id);
        if (! $sale->materialRequirements()->exists()) {
            throw ValidationException::withMessages(['readiness' => 'Kebutuhan bahan belum diperiksa. Minta pengguna berwenang melakukan pemeriksaan terlebih dahulu.']);
        }
        $this->selectedSaleId = $id;
        $this->tab = 'sales';
        $this->materials_required_on = max(now()->format('Y-m-d'), $sale->ship_by->format('Y-m-d'));
        $this->resetErrorBag();
        $this->dispatch('scroll-to-element', ['id' => 'sales-readiness']);
    }

    public function checkSale(int $id, SalesReadinessService $service): void
    {
        Gate::authorize('ppic.ic.sales.check');
        $this->resetErrorBag();
        $this->selectedSaleId = null;
        $service->prepare($id, ! empty($this->bomSelections[$id]) ? (int) $this->bomSelections[$id] : null);
        $this->viewSale($id);
    }

    public function requestShortages(SalesReadinessService $service): void
    {
        Gate::authorize('ppic.ic.sales.request');
        if (! $this->selectedSaleId) return;
        $count = $service->requestShortages($this->selectedSaleId, $this->materials_required_on);
        $this->resetErrorBag();
        $this->resetPage('requestsPage');
        $this->dispatch('notify', ['type' => 'success', 'message' => $count ? $count.' pengajuan kekurangan bahan dikirim ke Purchasing.' : 'Tidak ada kekurangan baru yang perlu diajukan.']);
    }

    public function handOverSale(SalesReadinessService $service): void
    {
        Gate::authorize('ppic.ic.sales.handover');
        if (! $this->selectedSaleId) return;
        $handoff = $service->handOver($this->selectedSaleId);
        $this->resetErrorBag();
        $this->dispatch('ppic-attention-updated');
        $this->dispatch('notify', ['type' => 'success', 'message' => $handoff->reference.' diserahkan ke Production Plan. Bahan sudah dicadangkan.']);
    }

    public function submitRequest(ProcurementService $service): void
    {
        Gate::authorize('ppic.ic.requests.create');
        $request = $service->submit($this->only(['product_id', 'quantity', 'required_on']));
        $this->reset('product_id', 'quantity', 'required_on');
        $this->resetErrorBag();
        $this->resetPage('requestsPage');
        $this->dispatch('notify', ['type' => 'success', 'message' => $request->reference.' dikirim ke Purchasing.']);
    }

    public function selectReceipt(int $id): void
    {
        Gate::authorize('ppic.ic.receipts.create');
        $order = PurchaseOrder::with('request')->findOrFail($id);
        $this->selectedOrderId = $order->id;
        $this->received_quantity = (string) max(0, $order->remaining);
        $this->received_on = now()->format('Y-m-d');
        $this->receiptToken = (string) Str::uuid();
        $this->resetErrorBag();
        $this->dispatch('scroll-to-element', ['id' => 'receipt-form']);
    }

    public function cancelReceipt(): void
    {
        $this->reset('selectedOrderId', 'received_quantity', 'received_on', 'receiptToken');
        $this->resetErrorBag();
    }

    public function receiveGoods(ProcurementService $service): void
    {
        Gate::authorize('ppic.ic.receipts.create');
        if (! $this->selectedOrderId) return;
        $service->receive($this->selectedOrderId, $this->received_quantity, $this->received_on, $this->receiptToken);
        $this->cancelReceipt();
        $this->resetPage('receiptsPage');
        $this->dispatch('ppic-attention-updated');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Penerimaan dicatat dan stok bertambah sesuai jumlah diterima.']);
    }

    public function render()
    {
        if ($this->pageTab !== null) {
            Gate::authorize($this->tabPermissions()[$this->pageTab]);
            $this->tab = $this->pageTab;
        }
        $allowedTabs = array_filter($this->tabPermissions(), fn ($permission) => Gate::allows($permission));
        if ($allowedTabs === [] && Gate::allows('ppic.ic.issues.view')) {
            $this->redirectRoute('ppic.ic.issues', navigate: true);
            return view('modules.ppic.livewire.inventory-redirect');
        }
        abort_if($allowedTabs === [], 403);
        if (! isset($allowedTabs[$this->tab])) {
            $this->tab = array_key_first($allowedTabs);
            $this->selectedSaleId = null;
            $this->cancelReceipt();
        }
        $productOptions = $this->tab === 'requests' && Gate::allows('ppic.ic.requests.create') ? app(ProcurementService::class)->materials()->get() : collect();
        // Only query the data belonging to the authorized, visible tab.
        return view('modules.ppic.livewire.inventory-control-page', [
            'allowedTabs' => $allowedTabs,
            'productOptions' => $productOptions,
            'selectedRequestProduct' => $productOptions->firstWhere('id', (int) $this->product_id),
            'requests' => $this->tab === 'requests' ? PurchaseRequest::with(['product', 'unit', 'salesOrder', 'order' => fn ($q) => $q->with('request')->withSum('receipts', 'quantity')])->latest('id')->paginate(15, ['*'], 'requestsPage') : collect(),
            'orders' => $this->tab === 'in' ? PurchaseOrder::with(['request.product', 'request.unit'])->withSum('receipts', 'quantity')->latest('id')->paginate(15, ['*'], 'ordersPage') : collect(),
            'receipts' => $this->tab === 'in' ? InventoryReceipt::with(['product', 'unit', 'order'])->latest('id')->paginate(15, ['*'], 'receiptsPage') : collect(),
            'products' => $this->tab === 'stock' ? app(\App\Modules\Ppic\Services\InventoryStockService::class)->withBalances(Item::with('unit'))->orderBy('code')->paginate(15, ['*'], 'stockPage') : collect(),
            'selectedOrder' => $this->tab === 'in' && $this->selectedOrderId && Gate::allows('ppic.ic.receipts.create') ? PurchaseOrder::with(['request.product', 'request.unit'])->find($this->selectedOrderId) : null,
            'sales' => $this->tab === 'sales' ? SalesOrder::with('productionHandoff')->withCount('materialRequirements')->latest('id')->paginate(15, ['*'], 'readinessPage') : collect(),
            'boms' => $this->tab === 'sales' && Gate::allows('ppic.ic.sales.check') ? Bom::where('is_active', true)->orderBy('code')->get()->groupBy('product_id') : collect(),
            'readiness' => $this->tab === 'sales' && $this->selectedSaleId ? app(SalesReadinessService::class)->assess($this->selectedSaleId) : null,
        ]);
    }
}
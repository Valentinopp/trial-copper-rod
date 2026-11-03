<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registrasi service khusus aplikasi dapat ditambahkan di sini.
    }

    public function boot(): void
    {
        Gate::define('access-erp', fn (User $user) => $user->is_active);
        Gate::define('access-all-modules', fn (User $user) => $user->is_active && $user->role->hasFullAccess());
        Gate::define('access-master-data', fn (User $user) => $user->is_active && $user->role === \App\Enums\UserRole::SuperAdmin);
        Gate::define('use-sino', fn (User $user) => $user->is_active && $user->role->sinoScope() !== null
            && ($user->role !== \App\Enums\UserRole::Purchasing || Gate::forUser($user)->allows('access-purchasing'))
            && ($user->role !== \App\Enums\UserRole::Marketing || Gate::forUser($user)->allows('marketing.sales.view')));
        foreach (\App\Services\AccessPermissionService::keys() as $permission) {
            Gate::define($permission, fn (User $user) => app(\App\Services\AccessPermissionService::class)->allows($user, $permission));
        }
        foreach (['produksi', 'ppic'] as $module) {
            Gate::define('access-'.$module, fn (User $user) => $user->is_active && $user->role->canAccessModule($module));
        }
        Gate::define('access-purchasing', fn (User $user) => Gate::forUser($user)->allows('purchasing.requests.view') || Gate::forUser($user)->allows('purchasing.orders.view'));
        Gate::define('access-marketing', fn (User $user) => Gate::forUser($user)->allows('marketing.sales.view'));
        Gate::define('access-ic', fn (User $user) => collect(['ppic.ic.sales.view', 'ppic.ic.requests.view', 'ppic.ic.receipts.view', 'ppic.ic.issues.view', 'ppic.ic.stock.view'])
            ->contains(fn ($permission) => Gate::forUser($user)->allows($permission)));
        Gate::define('access-ppic-navigation', fn (User $user) => Gate::forUser($user)->allows('access-ppic') || Gate::forUser($user)->allows('access-ic'));
        // Module components live outside Livewire's default App\Livewire namespace.
        // Register aliases on every request, including POST /livewire/update.
        foreach ([
            'master-data.master-data-page' => \App\Modules\MasterData\Livewire\MasterDataPage::class,
            'master-data.supplier-page' => \App\Modules\MasterData\Livewire\SupplierPage::class,
            'master-data.customer-page' => \App\Modules\MasterData\Livewire\CustomerPage::class,
            'master-data.access-profile-page' => \App\Modules\MasterData\Livewire\AccessProfilePage::class,
            'master-data.personnel-page' => \App\Modules\MasterData\Livewire\PersonnelPage::class,
            'master-data.production-reference-page' => \App\Modules\MasterData\Livewire\ProductionReferencePage::class,
            'master-data.bom-page' => \App\Modules\MasterData\Livewire\BomPage::class,
            'produksi.board-page' => \App\Modules\Produksi\Livewire\BoardPage::class,
            'produksi.crystallizer-page' => \App\Modules\Produksi\Livewire\CrystallizerPage::class,
            'produksi.copper-break-page' => \App\Modules\Produksi\Livewire\CopperBreakPage::class,
            'produksi.output-page' => \App\Modules\Produksi\Livewire\OutputPage::class,
            'produksi.sino-chat' => \App\Modules\Produksi\Livewire\SinoChat::class,
            'ppic.dashboard-page' => \App\Modules\Ppic\Livewire\DashboardPage::class,
            'ppic.production-calendar-page' => \App\Modules\Ppic\Livewire\ProductionCalendarPage::class,
            'ppic.production-plan-page' => \App\Modules\Ppic\Livewire\ProductionPlanPage::class,
            'ppic.goods-issue-page' => \App\Modules\Ppic\Livewire\GoodsIssuePage::class,
            'ppic.inventory-control-page' => \App\Modules\Ppic\Livewire\InventoryControlPage::class,
            'ppic.attention-indicator' => \App\Modules\Ppic\Livewire\AttentionIndicator::class,
            'marketing.sales-page' => \App\Modules\Marketing\Livewire\SalesPage::class,
            'purchasing.attention-indicator' => \App\Modules\Purchasing\Livewire\AttentionIndicator::class,
            'purchasing.pengajuan-page' => \App\Modules\Purchasing\Livewire\PengajuanPage::class,
            'purchasing.pembelian-page' => \App\Modules\Purchasing\Livewire\PembelianPage::class,
            'purchasing.dashboard-page' => \App\Modules\Purchasing\Livewire\DashboardPage::class,
        ] as $name => $component) {
            \Livewire\Livewire::component($name, $component);
        }
    }
}

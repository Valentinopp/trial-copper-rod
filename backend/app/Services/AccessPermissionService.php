<?php
namespace App\Services;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\MasterData\Models\AccessProfile;

class AccessPermissionService
{
    public function allows(User $user, string $permission): bool
    {
        $module = self::moduleFor($permission);
        if (! $module) return false;
        $current = User::find($user->id);
        if (! $current?->is_active) return false;
        if ($current->role === UserRole::SuperAdmin) return true;
        $profile = $current->job_title_id && $current->division_id
            ? AccessProfile::with(['jobTitle', 'division'])->where('job_title_id', $current->job_title_id)
                ->where('division_id', $current->division_id)->first() : null;
        if ($profile && (! $profile->is_active || ! $profile->jobTitle?->is_active || ! $profile->division?->is_active)) return false;
        // Existing Marketing profiles are authoritative. IC stays on legacy roles
        // until its checklist is explicitly enabled, including an empty checklist.
        $configured = $profile && ($module === 'marketing' || in_array($module, $profile->configured_modules ?? [], true));
        if (! $configured) return $current->role->canAccessModule(config('access_permissions.'.$module.'.legacy_module'));
        $permissions = $profile->permissions ?? [];
        return in_array($permission, $permissions, true)
            && collect(self::dependencies()[$permission] ?? [])->every(fn ($required) => in_array($required, $permissions, true));
    }

    public static function moduleFor(string $permission): ?string
    {
        foreach (config('access_permissions', []) as $key => $module) {
            foreach ($module['features'] as $feature) if (array_key_exists($permission, $feature['permissions'])) return $key;
        }
        return null;
    }

    public static function keys(): array
    {
        $keys = [];
        foreach (config('access_permissions', []) as $module) foreach ($module['features'] as $feature) array_push($keys, ...array_keys($feature['permissions']));
        return $keys;
    }

    public static function dependencies(): array
    {
        return [
            'purchasing.orders.create' => ['purchasing.requests.view', 'purchasing.orders.view'],
            'marketing.sales.create' => ['marketing.sales.view'],
            'ppic.ic.sales.check' => ['ppic.ic.sales.view'],
            'ppic.ic.sales.request' => ['ppic.ic.sales.view'],
            'ppic.ic.sales.handover' => ['ppic.ic.sales.view'],
            'ppic.ic.requests.create' => ['ppic.ic.requests.view'],
            'ppic.ic.receipts.create' => ['ppic.ic.receipts.view'],
            'ppic.ic.issues.create' => ['ppic.ic.issues.view'],
            'ppic.ic.issues.confirm' => ['ppic.ic.issues.view'],
            'ppic.ic.issues.reverse' => ['ppic.ic.issues.view'],
        ];
    }

    public static function normalize(array $permissions): array
    {
        foreach (self::dependencies() as $action => $requires) if (in_array($action, $permissions, true)) array_push($permissions, ...$requires);
        return array_values(array_unique($permissions));
    }

    public static function requiredBySelected(string $permission, array $selected): bool
    {
        foreach (self::dependencies() as $action => $requires) if (in_array($action, $selected, true) && in_array($permission, $requires, true)) return true;
        return false;
    }
}
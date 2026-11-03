<?php

namespace App\Enums;

enum UserRole: string
{
    case OperatorProduksi = 'operator_produksi';
    case AsistenManager = 'asisten_manager';
    case Manager = 'manager';
    case Ppic = 'ppic';
    case Marketing = 'marketing';
    case Purchasing = 'purchasing';
    case SuperAdmin = 'it_super_admin';

    public function label(): string
    {
        return match ($this) {
            self::OperatorProduksi => 'Operator Produksi',
            self::AsistenManager => 'Asisten Manager',
            self::Manager => 'Manager',
            self::Ppic => 'Staff PPIC',
            self::Marketing => 'Staff Marketing',
            self::Purchasing => 'Staff Purchasing',
            self::SuperAdmin => 'IT Super Admin',
        };
    }

    public function hasFullAccess(): bool
    {
        return in_array($this, [self::SuperAdmin, self::AsistenManager, self::Manager], true);
    }

    public function canAccessModule(string $module): bool
    {
        return $this->hasFullAccess() || match ($this) {
            self::OperatorProduksi => $module === 'produksi',
            self::Ppic => $module === 'ppic',
            self::Marketing => $module === 'marketing',
            self::Purchasing => $module === 'purchasing',
            default => false,
        };
    }

    public function homeRoute(): string
    {
        return match ($this) {
            self::Ppic => 'ppic.production-plan',
            self::Marketing => 'marketing.sales',
            self::Purchasing => 'purchasing.dashboard',
            default => 'board',
        };
    }

    public function sinoScope(): ?string
    {
        if ($this->hasFullAccess()) {
            return 'erp';
        }
        return match ($this) {
            self::Ppic => 'ppic',
            self::Marketing => 'marketing',
            self::Purchasing => 'purchasing',
            default => null,
        };
    }
}

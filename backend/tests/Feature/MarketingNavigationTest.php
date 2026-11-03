<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected UserRole $authenticatedRole = UserRole::Marketing;

    public function test_marketing_and_legacy_catalog_urls_lead_to_sales(): void
    {
        $this->get('/marketing')->assertRedirectToRoute('marketing.sales');
        $this->get('/marketing/products')->assertRedirectToRoute('marketing.sales');
        $this->get('/marketing/sales')->assertOk()->assertSee('Penjualan')->assertDontSee('Katalog Produk');
        $this->assertSame('marketing.sales', UserRole::Marketing->homeRoute());
    }
}
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Enums\UserRole;
use App\Models\User;

abstract class TestCase extends BaseTestCase
{
    protected bool $authenticateByDefault = true;
    protected UserRole $authenticatedRole = UserRole::Manager;

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->authenticateByDefault) {
            $this->actingAs(User::factory()->create(['role' => $this->authenticatedRole, 'job_title' => $this->authenticatedRole->label()]));
        }
    }
}

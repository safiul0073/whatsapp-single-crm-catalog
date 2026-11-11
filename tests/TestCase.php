<?php

namespace Tests;

use App\Modules\PlansSubscriptions\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedUnlimitedTestPlan();
    }

    /**
     * Subscriptions are enforced, so every workspace a test creates gets this unlimited free plan
     * automatically (see PlansSubscriptionsServiceProvider). Tests that exercise plans themselves
     * opt out with the WithoutDefaultTestPlan trait.
     */
    protected function seedUnlimitedTestPlan(): void
    {
        $traits = class_uses_recursive(static::class);

        if (! isset($traits[RefreshDatabase::class]) || isset($traits[WithoutDefaultTestPlan::class]) || ! Schema::hasTable('plans')) {
            return;
        }

        Plan::query()->firstOrCreate(['slug' => 'test-unlimited'], [
            'name' => 'Test Unlimited',
            'price' => 0,
            'interval' => Plan::INTERVAL_MONTH,
            'is_free' => true,
            'limits' => ['automation_ai_builder' => true, 'campaign_ai_doctor' => true],
            'features' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }
}

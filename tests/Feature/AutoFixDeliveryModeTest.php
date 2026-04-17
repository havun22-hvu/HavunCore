<?php

namespace Tests\Feature;

use App\Models\AutofixProposal;
use App\Services\AIProxyService;
use App\Services\AutoFixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoFixDeliveryModeTest extends TestCase
{
    use RefreshDatabase;

    private AutoFixService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'autofix.branch_model' => true,
            'autofix.branch_prefix' => 'hotfix/autofix-',
            'autofix.dry_run_on_risk' => ['medium', 'high'],
            'autofix.snapshot_enabled' => true,
        ]);

        $proxy = $this->createMock(AIProxyService::class);
        $this->service = new class($proxy) extends AutoFixService
        {
            public function callResolveDeliveryMode(string $risk): string
            {
                return $this->resolveDeliveryMode($risk);
            }
        };
    }

    public function test_low_risk_resolves_to_branch_pr(): void
    {
        $this->assertSame('branch_pr', $this->service->callResolveDeliveryMode('low'));
    }

    public function test_medium_risk_resolves_to_dry_run(): void
    {
        $this->assertSame('dry_run', $this->service->callResolveDeliveryMode('medium'));
    }

    public function test_high_risk_resolves_to_dry_run(): void
    {
        $this->assertSame('dry_run', $this->service->callResolveDeliveryMode('high'));
    }

    public function test_branch_model_disabled_falls_back_to_direct(): void
    {
        config(['autofix.branch_model' => false]);

        $this->assertSame('direct', $this->service->callResolveDeliveryMode('low'));
        $this->assertSame('direct', $this->service->callResolveDeliveryMode('high'));
    }

    public function test_direct_push_to_main_is_disabled_by_default_config(): void
    {
        $this->assertTrue(config('autofix.branch_model'));
        $this->assertContains('medium', config('autofix.dry_run_on_risk'));
        $this->assertContains('high', config('autofix.dry_run_on_risk'));
    }

    public function test_fallback_default_when_config_missing_still_blocks_medium(): void
    {
        // Simuleer ontbrekende config-key — moet vallen op hard-coded default
        // ['medium', 'high'] in resolveDeliveryMode(). Mutation guard voor
        // ArrayItemRemoval op die default-literal.
        $all = config('autofix');
        unset($all['dry_run_on_risk']);
        config()->set('autofix', $all);

        $this->assertFalse(array_key_exists('dry_run_on_risk', config('autofix')));
        $this->assertSame('dry_run', $this->service->callResolveDeliveryMode('medium'));
        $this->assertSame('dry_run', $this->service->callResolveDeliveryMode('high'));
        $this->assertSame('branch_pr', $this->service->callResolveDeliveryMode('low'));
    }

    public function test_string_config_value_is_normalised_to_array(): void
    {
        // Iemand zet per ongeluk AUTOFIX_DRY_RUN_ON_RISK=high (string) in .env
        // via een non-array env parser. (array)-cast moet dat opvangen.
        config()->set('autofix.dry_run_on_risk', 'high');

        $this->assertSame('dry_run', $this->service->callResolveDeliveryMode('high'));
        $this->assertSame('branch_pr', $this->service->callResolveDeliveryMode('medium'));
    }
}

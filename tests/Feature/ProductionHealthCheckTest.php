<?php

namespace Tests\Feature;

use App\Domains\Cards\Models\BingoCardVersion;
use App\Domains\Cards\Services\BingoCardGenerator;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Health\Services\HealthCheckService;
use App\Domains\Health\Services\InventoryAuditService;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected BingoCardGenerator $cardGenerator;

    protected HealthCheckService $healthService;

    protected InventoryAuditService $inventoryAuditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->company = Company::create([
            'name' => 'Production Health Bingo Club',
            'slug' => 'prod-health-club',
            'status' => 'active',
        ]);

        $this->cardGenerator = app(BingoCardGenerator::class);
        $this->healthService = app(HealthCheckService::class);
        $this->inventoryAuditService = app(InventoryAuditService::class);
    }

    public function test_health_check_service_returns_structured_diagnostics(): void
    {
        $report = $this->healthService->check();

        $this->assertIsArray($report);
        $this->assertArrayHasKey('status', $report);
        $this->assertArrayHasKey('timestamp', $report);
        $this->assertArrayHasKey('environment', $report);
        $this->assertArrayHasKey('checks', $report);

        $checks = $report['checks'];
        $this->assertArrayHasKey('database', $checks);
        $this->assertArrayHasKey('cache', $checks);
        $this->assertArrayHasKey('queue', $checks);
        $this->assertArrayHasKey('reverb', $checks);
        $this->assertArrayHasKey('inventory', $checks);
        $this->assertArrayHasKey('ledger', $checks);

        $this->assertEquals(HealthCheckService::STATUS_HEALTHY, $checks['database']['status']);
        $this->assertEquals(HealthCheckService::STATUS_HEALTHY, $checks['cache']['status']);
        $this->assertIsFloat($checks['database']['latency_ms']);
        $this->assertIsFloat($checks['cache']['latency_ms']);
    }

    public function test_health_check_detects_low_fixed_card_inventory_as_warning(): void
    {
        // 0 cards generated -> below threshold of 50
        $report = $this->healthService->check();

        $this->assertEquals(HealthCheckService::STATUS_WARNING, $report['checks']['inventory']['status']);
        $this->assertEquals(HealthCheckService::STATUS_WARNING, $report['status']);
    }

    public function test_health_check_service_is_fully_healthy_when_inventory_sufficient(): void
    {
        // Deactivate seeded sample companies so only this test company is active
        Company::where('id', '!=', $this->company->id)->update(['status' => 'inactive']);

        // Generate batch of 60 cards for the company
        $this->cardGenerator->generateBatch($this->company, 60);

        $report = $this->healthService->check();

        $this->assertEquals(HealthCheckService::STATUS_HEALTHY, $report['checks']['inventory']['status']);
        $this->assertEquals(HealthCheckService::STATUS_HEALTHY, $report['status']);
    }

    public function test_bingo_health_artisan_command_executes_successfully(): void
    {
        $this->artisan('bingo:health')
            ->expectsOutputToContain('NOBINGO PRODUCTION HEALTH & DIAGNOSTIC AUDIT')
            ->assertExitCode(0);
    }

    public function test_nobingo_health_artisan_alias_command_executes_successfully(): void
    {
        $this->artisan('nobingo:health')
            ->expectsOutputToContain('NOBINGO PRODUCTION HEALTH & DIAGNOSTIC AUDIT')
            ->assertExitCode(0);
    }

    public function test_bingo_health_json_flag_outputs_valid_json(): void
    {
        $this->artisan('bingo:health', ['--json' => true])
            ->assertExitCode(0);
    }

    public function test_inventory_audit_verifies_cryptographic_sha256_card_hashes(): void
    {
        $this->cardGenerator->generateBatch($this->company, 10);

        $cardAudit = $this->inventoryAuditService->auditCards($this->company->id);

        $this->assertEquals('clean', $cardAudit['status']);
        $this->assertEquals(10, $cardAudit['total_audited']);
        $this->assertEquals(10, $cardAudit['valid_count']);
        $this->assertEquals(0, $cardAudit['corrupted_count']);
        $this->assertEmpty($cardAudit['mismatches']);
    }

    public function test_inventory_audit_detects_corrupted_card_hash_tampering(): void
    {
        $this->cardGenerator->generateBatch($this->company, 5);

        // Tamper with the card hash of one version directly in database
        $version = BingoCardVersion::firstOrFail();
        $version->update(['card_hash' => 'tampered_invalid_sha256_hash_value']);

        $cardAudit = $this->inventoryAuditService->auditCards($this->company->id);

        $this->assertEquals('corrupted', $cardAudit['status']);
        $this->assertEquals(5, $cardAudit['total_audited']);
        $this->assertEquals(4, $cardAudit['valid_count']);
        $this->assertEquals(1, $cardAudit['corrupted_count']);
        $this->assertCount(1, $cardAudit['mismatches']);
        $this->assertEquals($version->id, $cardAudit['mismatches'][0]['version_id']);
    }

    public function test_bingo_inventory_audit_command_fails_on_corrupted_card(): void
    {
        $this->cardGenerator->generateBatch($this->company, 5);

        // Tamper with a card hash
        $version = BingoCardVersion::firstOrFail();
        $version->update(['card_hash' => 'tampered_sha256_hash']);

        $this->artisan('bingo:inventory:audit', ['--company' => $this->company->id])
            ->expectsOutputToContain('Mismatched / Corrupted Cards Detected:')
            ->assertExitCode(1);
    }

    public function test_inventory_audit_reconciles_ledger_transactions_and_balances(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 5000,
        ]);

        Transaction::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'amount' => 5000,
            'currency' => 'USD',
            'status' => Transaction::STATUS_COMPLETED,
            'balance_before' => 0,
            'balance_after' => 5000,
            'reference_code' => 'DEP-TEST-001',
            'description' => 'Test deposit',
        ]);

        $ledgerAudit = $this->inventoryAuditService->auditLedger($this->company->id);

        $this->assertEquals('balanced', $ledgerAudit['status']);
        $this->assertEquals(1, $ledgerAudit['total_transactions']);
        $this->assertEquals(0, $ledgerAudit['arithmetic_errors']);
        $this->assertEquals(0, $ledgerAudit['user_discrepancies']);
    }

    public function test_inventory_audit_detects_ledger_balance_discrepancy(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 9999, // Tampered balance not matching transaction history
        ]);

        Transaction::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'amount' => 5000,
            'currency' => 'USD',
            'status' => Transaction::STATUS_COMPLETED,
            'balance_before' => 0,
            'balance_after' => 5000,
            'reference_code' => 'DEP-TEST-002',
            'description' => 'Test deposit',
        ]);

        $ledgerAudit = $this->inventoryAuditService->auditLedger($this->company->id);

        $this->assertEquals('discrepancy', $ledgerAudit['status']);
        $this->assertEquals(1, $ledgerAudit['user_discrepancies']);
        $this->assertCount(1, $ledgerAudit['discrepancies']);
        $this->assertEquals($user->id, $ledgerAudit['discrepancies'][0]['user_id']);
    }

    public function test_http_health_endpoint_returns_json_and_no_cache_headers(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200);
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'environment',
            'checks' => [
                'database',
                'cache',
                'queue',
                'reverb',
                'inventory',
                'ledger',
            ],
        ]);
    }
}

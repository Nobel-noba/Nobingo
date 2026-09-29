<?php

namespace App\Domains\Health\Services;

use App\Domains\Cards\Models\BingoCard;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HealthCheckService
{
    public const STATUS_HEALTHY = 'healthy';

    public const STATUS_WARNING = 'warning';

    public const STATUS_CRITICAL = 'critical';

    public const INVENTORY_WARNING_THRESHOLD = 50;

    /**
     * Run all system health and diagnostic checks.
     *
     * @return array{
     *     status: string,
     *     timestamp: string,
     *     environment: string,
     *     checks: array{
     *         database: array{status: string, latency_ms: float|null, connection: string, error?: string},
     *         cache: array{status: string, latency_ms: float|null, store: string, error?: string},
     *         queue: array{status: string, driver: string, failed_jobs: int, pending_jobs: int|null, note?: string},
     *         reverb: array{status: string, broadcast_driver: string, server_host: string, server_port: int, configured: bool},
     *         inventory: array{status: string, total_cards: int, available_cards: int, low_inventory_companies: list<string>, details: array<string, mixed>},
     *         ledger: array{status: string, audited_transactions: int, arithmetic_errors: int, user_discrepancies: int}
     *     }
     * }
     */
    public function check(): array
    {
        $dbCheck = $this->checkDatabase();
        $cacheCheck = $this->checkCache();
        $queueCheck = $this->checkQueue();
        $reverbCheck = $this->checkReverb();
        $inventoryCheck = $this->checkInventory();
        $ledgerCheck = $this->checkLedger();

        $overallStatus = self::STATUS_HEALTHY;

        $criticalComponents = [$dbCheck['status'], $cacheCheck['status']];
        if (in_array(self::STATUS_CRITICAL, $criticalComponents, true)) {
            $overallStatus = self::STATUS_CRITICAL;
        } elseif (
            $dbCheck['status'] === self::STATUS_WARNING
            || $cacheCheck['status'] === self::STATUS_WARNING
            || $queueCheck['status'] === self::STATUS_WARNING
            || $reverbCheck['status'] === self::STATUS_WARNING
            || $inventoryCheck['status'] === self::STATUS_WARNING
            || $ledgerCheck['status'] === self::STATUS_WARNING
        ) {
            $overallStatus = self::STATUS_WARNING;
        }

        return [
            'status' => $overallStatus,
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env', 'production'),
            'checks' => [
                'database' => $dbCheck,
                'cache' => $cacheCheck,
                'queue' => $queueCheck,
                'reverb' => $reverbCheck,
                'inventory' => $inventoryCheck,
                'ledger' => $ledgerCheck,
            ],
        ];
    }

    /**
     * Check Database connectivity and measure latency.
     *
     * @return array{status: string, latency_ms: float|null, connection: string, error?: string}
     */
    public function checkDatabase(): array
    {
        $connectionName = config('database.default', 'mysql');
        $startTime = microtime(true);

        try {
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            $status = $latency > 250.0 ? self::STATUS_WARNING : self::STATUS_HEALTHY;

            return [
                'status' => $status,
                'latency_ms' => $latency,
                'connection' => $connectionName,
            ];
        } catch (Exception $e) {
            return [
                'status' => self::STATUS_CRITICAL,
                'latency_ms' => null,
                'connection' => $connectionName,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Cache read/write cycles and measure latency.
     *
     * @return array{status: string, latency_ms: float|null, store: string, error?: string}
     */
    public function checkCache(): array
    {
        $storeName = config('cache.default', 'file');
        $startTime = microtime(true);
        $testKey = 'health_probe_'.bin2hex(random_bytes(4));
        $testValue = 'nobingo_probe_ok';

        try {
            Cache::put($testKey, $testValue, 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            if ($retrieved !== $testValue) {
                return [
                    'status' => self::STATUS_CRITICAL,
                    'latency_ms' => null,
                    'store' => $storeName,
                    'error' => 'Cache read verification failed: value mismatch.',
                ];
            }

            $latency = round((microtime(true) - $startTime) * 1000, 2);
            $status = $latency > 150.0 ? self::STATUS_WARNING : self::STATUS_HEALTHY;

            return [
                'status' => $status,
                'latency_ms' => $latency,
                'store' => $storeName,
            ];
        } catch (Exception $e) {
            return [
                'status' => self::STATUS_CRITICAL,
                'latency_ms' => null,
                'store' => $storeName,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Queue status and failed jobs count.
     *
     * @return array{status: string, driver: string, failed_jobs: int, pending_jobs: int|null, note?: string}
     */
    public function checkQueue(): array
    {
        $driver = config('queue.default', 'database');
        $failedJobs = 0;
        $pendingJobs = null;

        try {
            if (Schema::hasTable('failed_jobs')) {
                $failedJobs = DB::table('failed_jobs')->count();
            }

            if ($driver === 'database' && Schema::hasTable('jobs')) {
                $pendingJobs = DB::table('jobs')->count();
            }

            $status = self::STATUS_HEALTHY;
            if ($failedJobs > 25) {
                $status = self::STATUS_WARNING;
            }

            return [
                'status' => $status,
                'driver' => $driver,
                'failed_jobs' => $failedJobs,
                'pending_jobs' => $pendingJobs,
            ];
        } catch (Exception $e) {
            return [
                'status' => self::STATUS_WARNING,
                'driver' => $driver,
                'failed_jobs' => $failedJobs,
                'pending_jobs' => null,
                'note' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Reverb WebSocket broadcast configuration.
     *
     * @return array{status: string, broadcast_driver: string, server_host: string, server_port: int, configured: bool}
     */
    public function checkReverb(): array
    {
        $driver = config('broadcasting.default', 'log');
        $host = config('reverb.servers.reverb.host', '0.0.0.0');
        $port = (int) config('reverb.servers.reverb.port', 8080);
        $appKey = config('reverb.apps.apps.0.key');

        $isConfigured = ! empty($appKey);
        $status = self::STATUS_HEALTHY;

        if ($driver === 'reverb' && ! $isConfigured) {
            $status = self::STATUS_WARNING;
        }

        return [
            'status' => $status,
            'broadcast_driver' => $driver,
            'server_host' => $host,
            'server_port' => $port,
            'configured' => $isConfigured,
        ];
    }

    /**
     * Audit card inventory levels across active companies.
     *
     * @return array{status: string, total_cards: int, available_cards: int, low_inventory_companies: list<string>, details: array<string, mixed>}
     */
    public function checkInventory(): array
    {
        $companies = Company::where('status', 'active')->get();
        $totalCards = 0;
        $totalAvailable = 0;
        $lowInventoryCompanies = [];
        $details = [];

        foreach ($companies as $company) {
            $total = BingoCard::withoutGlobalScopes()->where('company_id', $company->id)->count();
            $available = BingoCard::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('status', BingoCard::STATUS_AVAILABLE)
                ->count();

            $totalCards += $total;
            $totalAvailable += $available;

            $details[$company->slug] = [
                'total' => $total,
                'available' => $available,
                'assigned' => $total - $available,
            ];

            if ($available < self::INVENTORY_WARNING_THRESHOLD) {
                $lowInventoryCompanies[] = $company->name;
            }
        }

        $status = count($lowInventoryCompanies) > 0 ? self::STATUS_WARNING : self::STATUS_HEALTHY;

        return [
            'status' => $status,
            'total_cards' => $totalCards,
            'available_cards' => $totalAvailable,
            'low_inventory_companies' => $lowInventoryCompanies,
            'details' => $details,
        ];
    }

    /**
     * Check financial ledger balance invariant across transactions.
     *
     * @return array{status: string, audited_transactions: int, arithmetic_errors: int, user_discrepancies: int}
     */
    public function checkLedger(): array
    {
        $arithmeticErrors = 0;
        $userDiscrepancies = 0;

        // Sample the most recent transactions to ensure fast health checks
        $transactions = Transaction::latest('id')->take(200)->get();

        foreach ($transactions as $tx) {
            $expectedAfter = $tx->isCredit()
                ? $tx->balance_before + $tx->amount
                : $tx->balance_before - $tx->amount;

            if ($tx->balance_after !== $expectedAfter) {
                $arithmeticErrors++;
            }
        }

        // Check if any user balance differs from their most recent transaction balance_after
        $recentUserIds = $transactions->pluck('user_id')->unique()->filter();
        if ($recentUserIds->isNotEmpty()) {
            $users = User::whereIn('id', $recentUserIds)->get();
            foreach ($users as $user) {
                $latestTx = Transaction::where('user_id', $user->id)
                    ->where('status', Transaction::STATUS_COMPLETED)
                    ->latest('id')
                    ->first();

                if ($latestTx && $latestTx->balance_after !== $user->balance) {
                    $userDiscrepancies++;
                }
            }
        }

        $status = ($arithmeticErrors > 0 || $userDiscrepancies > 0)
            ? self::STATUS_WARNING
            : self::STATUS_HEALTHY;

        return [
            'status' => $status,
            'audited_transactions' => $transactions->count(),
            'arithmetic_errors' => $arithmeticErrors,
            'user_discrepancies' => $userDiscrepancies,
        ];
    }
}

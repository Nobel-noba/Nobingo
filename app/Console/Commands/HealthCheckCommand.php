<?php

namespace App\Console\Commands;

use App\Domains\Health\Services\HealthCheckService;
use Illuminate\Console\Command;

class HealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bingo:health {--json : Output results as raw JSON for monitoring systems}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform comprehensive system health checks across Database, Cache, Queue, Reverb, Card Inventory, and Ledger';

    /**
     * Execute the console command.
     */
    public function handle(HealthCheckService $healthService): int
    {
        $report = $healthService->check();

        if ($this->option('json')) {
            $this->output->writeln((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['status'] === HealthCheckService::STATUS_CRITICAL ? Command::FAILURE : Command::SUCCESS;
        }

        $this->newLine();
        $this->line(' <fg=white;bg=blue;options=bold> NOBINGO PRODUCTION HEALTH & DIAGNOSTIC AUDIT </>');
        $this->line(" <fg=gray>Timestamp: {$report['timestamp']} | Environment: {$report['environment']}</>");
        $this->newLine();

        $rows = [];

        // Database
        $db = $report['checks']['database'];
        $rows[] = [
            'Database',
            $this->formatStatusBadge($db['status']),
            "Connection: {$db['connection']}".(isset($db['error']) ? " (Error: {$db['error']})" : ''),
            $db['latency_ms'] !== null ? "{$db['latency_ms']} ms" : 'N/A',
        ];

        // Cache
        $cache = $report['checks']['cache'];
        $rows[] = [
            'Cache Store',
            $this->formatStatusBadge($cache['status']),
            "Store: {$cache['store']}".(isset($cache['error']) ? " (Error: {$cache['error']})" : ''),
            $cache['latency_ms'] !== null ? "{$cache['latency_ms']} ms" : 'N/A',
        ];

        // Queue
        $queue = $report['checks']['queue'];
        $queueDetails = "Driver: {$queue['driver']} | Failed: {$queue['failed_jobs']}";
        if ($queue['pending_jobs'] !== null) {
            $queueDetails .= " | Pending: {$queue['pending_jobs']}";
        }
        $rows[] = [
            'Queue Engine',
            $this->formatStatusBadge($queue['status']),
            $queueDetails,
            "{$queue['failed_jobs']} failed",
        ];

        // Reverb
        $reverb = $report['checks']['reverb'];
        $rows[] = [
            'Reverb WebSockets',
            $this->formatStatusBadge($reverb['status']),
            "Driver: {$reverb['broadcast_driver']} | Host: {$reverb['server_host']}:{$reverb['server_port']}",
            $reverb['configured'] ? 'Configured' : 'Unconfigured',
        ];

        // Card Inventory
        $inv = $report['checks']['inventory'];
        $invDetails = "Total Cards: {$inv['total_cards']} | Available: {$inv['available_cards']}";
        if (! empty($inv['low_inventory_companies'])) {
            $invDetails .= ' | Low: '.implode(', ', $inv['low_inventory_companies']);
        }
        $rows[] = [
            'Card Inventory',
            $this->formatStatusBadge($inv['status']),
            $invDetails,
            "{$inv['available_cards']} available",
        ];

        // Ledger Invariants
        $ledger = $report['checks']['ledger'];
        $ledgerDetails = "Audited: {$ledger['audited_transactions']} txs | Errors: {$ledger['arithmetic_errors']}";
        $rows[] = [
            'Ledger Invariant',
            $this->formatStatusBadge($ledger['status']),
            $ledgerDetails,
            "{$ledger['user_discrepancies']} discrepancies",
        ];

        $this->table(['Subsystem', 'Status', 'Diagnostic Details', 'Metric'], $rows);

        $this->newLine();

        if ($report['status'] === HealthCheckService::STATUS_HEALTHY) {
            $this->info(' ✔ SYSTEM STATUS: ALL SUBSYSTEMS HEALTHY & NOMINAL');

            return Command::SUCCESS;
        }

        if ($report['status'] === HealthCheckService::STATUS_WARNING) {
            $this->warn(' ⚠ SYSTEM STATUS: WARNINGS DETECTED - ATTENTION RECOMMENDED');

            return Command::SUCCESS;
        }

        $this->error(' ✖ SYSTEM STATUS: CRITICAL FAILURE DETECTED - IMMEDIATE ACTION REQUIRED');

        return Command::FAILURE;
    }

    private function formatStatusBadge(string $status): string
    {
        return match ($status) {
            HealthCheckService::STATUS_HEALTHY => '<fg=black;bg=green;options=bold> OK </>',
            HealthCheckService::STATUS_WARNING => '<fg=black;bg=yellow;options=bold> WARN </>',
            HealthCheckService::STATUS_CRITICAL => '<fg=white;bg=red;options=bold> FAIL </>',
            default => $status,
        };
    }
}

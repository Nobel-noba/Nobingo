<?php

namespace App\Console\Commands;

use App\Domains\Health\Services\InventoryAuditService;
use App\Domains\Tenancy\Models\Company;
use Illuminate\Console\Command;

class AuditInventoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bingo:inventory:audit
                            {--company= : Audit only a specific company by slug or ID}
                            {--skip-ledger : Skip ledger transaction audit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cryptographically audit fixed card SHA-256 hashes and double-entry ledger invariants';

    /**
     * Execute the console command.
     */
    public function handle(InventoryAuditService $auditService): int
    {
        $companyInput = $this->option('company');
        $companyId = null;

        if ($companyInput !== null) {
            $company = is_numeric($companyInput)
                ? Company::find($companyInput)
                : Company::where('slug', $companyInput)->first();

            if (! $company) {
                $this->error("Company not found for identifier: {$companyInput}");

                return Command::FAILURE;
            }

            $companyId = $company->id;
            $this->info("Auditing inventory specifically for company: {$company->name} (#{$company->id})");
        }

        $this->newLine();
        $this->line(' <fg=white;bg=blue;options=bold> NOBINGO FIXED-CARD & LEDGER CRYPTOGRAPHIC AUDIT </>');
        $this->newLine();

        // 1. Audit Card SHA-256 hashes
        $this->comment('Auditing fixed-card SHA-256 hashes against raw 5x5 grids...');
        $cardAudit = $auditService->auditCards($companyId);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Cards Audited', $cardAudit['total_audited']],
                ['Cryptographically Valid Hashes', "<fg=green>{$cardAudit['valid_count']}</>"],
                ['Corrupted / Mismatched Cards', $cardAudit['corrupted_count'] > 0 ? "<fg=red>{$cardAudit['corrupted_count']}</>" : '<fg=green>0</>'],
                ['Hash Audit Status', $cardAudit['status'] === 'clean' ? '<fg=green;options=bold>CLEAN (SHA-256 Verified)</>' : '<fg=red;options=bold>CORRUPTED</>'],
            ]
        );

        if (! empty($cardAudit['mismatches'])) {
            $this->newLine();
            $this->error('Mismatched / Corrupted Cards Detected:');
            $mismatchRows = [];
            foreach ($cardAudit['mismatches'] as $mismatch) {
                $mismatchRows[] = [
                    $mismatch['card_id'],
                    $mismatch['version_id'],
                    substr($mismatch['stored_hash'], 0, 16).'...',
                    substr($mismatch['computed_hash'], 0, 16).'...',
                    $mismatch['reason'],
                ];
            }
            $this->table(['Card ID', 'Version ID', 'Stored Hash', 'Computed Hash', 'Reason'], $mismatchRows);
        }

        // 2. Audit Ledger Transactions
        $ledgerAuditPassed = true;
        if (! $this->option('skip-ledger')) {
            $this->newLine();
            $this->comment('Auditing double-entry ledger arithmetic and balance invariants...');
            $ledgerAudit = $auditService->auditLedger($companyId);

            $this->table(
                ['Ledger Metric', 'Value'],
                [
                    ['Total Transactions Audited', $ledgerAudit['total_transactions']],
                    ['Arithmetic Errors (Credit/Debit != Balance Delta)', $ledgerAudit['arithmetic_errors'] > 0 ? "<fg=red>{$ledgerAudit['arithmetic_errors']}</>" : '<fg=green>0</>'],
                    ['User Balance Discrepancies', $ledgerAudit['user_discrepancies'] > 0 ? "<fg=red>{$ledgerAudit['user_discrepancies']}</>" : '<fg=green>0</>'],
                    ['Ledger Audit Status', $ledgerAudit['status'] === 'balanced' ? '<fg=green;options=bold>BALANCED</>' : '<fg=red;options=bold>DISCREPANCY</>'],
                ]
            );

            if (! empty($ledgerAudit['discrepancies'])) {
                $this->newLine();
                $this->error('User Balance Discrepancies Detected:');
                $userRows = [];
                foreach ($ledgerAudit['discrepancies'] as $disc) {
                    $userRows[] = [
                        $disc['user_id'],
                        $disc['user_email'],
                        '$'.number_format($disc['user_balance'] / 100, 2),
                        '$'.number_format($disc['expected_balance'] / 100, 2),
                    ];
                }
                $this->table(['User ID', 'Email', 'Current Balance', 'Expected (Ledger) Balance'], $userRows);
            }

            if ($ledgerAudit['status'] !== 'balanced') {
                $ledgerAuditPassed = false;
            }
        }

        $this->newLine();

        if ($cardAudit['status'] === 'clean' && $ledgerAuditPassed) {
            $this->info(' ✔ AUDIT COMPLETE: ALL CARD ASSETS & LEDGER ENTRIES VERIFIED SUCCESSFULLY');

            return Command::SUCCESS;
        }

        $this->error(' ✖ AUDIT COMPLETE: INTEGRITY VIOLATIONS DETECTED');

        return Command::FAILURE;
    }
}

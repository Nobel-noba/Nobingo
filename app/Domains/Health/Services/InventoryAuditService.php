<?php

namespace App\Domains\Health\Services;

use App\Domains\Cards\Models\BingoCardVersion;
use App\Domains\Cards\Services\BingoCardValidator;
use App\Domains\Financial\Models\Transaction;
use App\Models\User;
use Exception;

class InventoryAuditService
{
    public function __construct(
        public BingoCardValidator $validator,
    ) {}

    /**
     * Audit cryptographic SHA-256 card hash integrity across all card versions.
     *
     * @return array{
     *     status: string,
     *     total_audited: int,
     *     valid_count: int,
     *     corrupted_count: int,
     *     mismatches: list<array{card_id: int, version_id: int, stored_hash: string, computed_hash: string, reason: string}>
     * }
     */
    public function auditCards(?int $companyId = null): array
    {
        $query = BingoCardVersion::with('card');

        if ($companyId !== null) {
            $query->whereHas('card', function ($q) use ($companyId): void {
                $q->withoutGlobalScopes()->where('company_id', $companyId);
            });
        }

        $versions = $query->get();

        $totalAudited = 0;
        $validCount = 0;
        $corruptedCount = 0;
        $mismatches = [];

        foreach ($versions as $version) {
            $totalAudited++;
            $grid = $version->grid;

            if (! is_array($grid) || count($grid) !== 5) {
                $corruptedCount++;
                $mismatches[] = [
                    'card_id' => $version->bingo_card_id,
                    'version_id' => $version->id,
                    'stored_hash' => (string) $version->card_hash,
                    'computed_hash' => '',
                    'reason' => 'Invalid grid structure: expected 5x5 matrix array.',
                ];

                continue;
            }

            try {
                $computedHash = $this->validator->computeHash($grid);

                if ($computedHash !== $version->card_hash) {
                    $corruptedCount++;
                    $mismatches[] = [
                        'card_id' => $version->bingo_card_id,
                        'version_id' => $version->id,
                        'stored_hash' => (string) $version->card_hash,
                        'computed_hash' => $computedHash,
                        'reason' => 'Cryptographic SHA-256 hash mismatch between grid and stored hash.',
                    ];
                } else {
                    $validCount++;
                }
            } catch (Exception $e) {
                $corruptedCount++;
                $mismatches[] = [
                    'card_id' => $version->bingo_card_id,
                    'version_id' => $version->id,
                    'stored_hash' => (string) $version->card_hash,
                    'computed_hash' => '',
                    'reason' => 'Validation error during hash computation: '.$e->getMessage(),
                ];
            }
        }

        return [
            'status' => $corruptedCount === 0 ? 'clean' : 'corrupted',
            'total_audited' => $totalAudited,
            'valid_count' => $validCount,
            'corrupted_count' => $corruptedCount,
            'mismatches' => $mismatches,
        ];
    }

    /**
     * Audit double-entry ledger transactions and user balance invariants.
     *
     * @return array{
     *     status: string,
     *     total_transactions: int,
     *     arithmetic_errors: int,
     *     user_discrepancies: int,
     *     discrepancies: list<array{user_id: int, user_email: string, user_balance: int, expected_balance: int}>
     * }
     */
    public function auditLedger(?int $companyId = null): array
    {
        $txQuery = Transaction::query();
        if ($companyId !== null) {
            $txQuery->where('company_id', $companyId);
        }

        $transactions = $txQuery->orderBy('id', 'asc')->get();

        $arithmeticErrors = 0;
        foreach ($transactions as $tx) {
            $expectedAfter = $tx->isCredit()
                ? $tx->balance_before + $tx->amount
                : $tx->balance_before - $tx->amount;

            if ($tx->balance_after !== $expectedAfter) {
                $arithmeticErrors++;
            }
        }

        // Audit user balances against their latest transaction
        $userQuery = User::query();
        if ($companyId !== null) {
            $userQuery->where('company_id', $companyId);
        }

        $users = $userQuery->get();
        $userDiscrepancies = 0;
        $discrepancies = [];

        foreach ($users as $user) {
            $latestTx = Transaction::where('user_id', $user->id)
                ->where('status', Transaction::STATUS_COMPLETED)
                ->latest('id')
                ->first();

            if ($latestTx !== null && $latestTx->balance_after !== $user->balance) {
                $userDiscrepancies++;
                $discrepancies[] = [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'user_balance' => $user->balance,
                    'expected_balance' => $latestTx->balance_after,
                ];
            }
        }

        return [
            'status' => ($arithmeticErrors === 0 && $userDiscrepancies === 0) ? 'balanced' : 'discrepancy',
            'total_transactions' => $transactions->count(),
            'arithmetic_errors' => $arithmeticErrors,
            'user_discrepancies' => $userDiscrepancies,
            'discrepancies' => $discrepancies,
        ];
    }
}

<?php

namespace App\Domains\Financial\Services;

use App\Domains\Financial\Models\DepositRequest;
use App\Domains\Financial\Models\PaymentAccount;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DepositRequestService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Create a new company platform credit purchase request.
     */
    public function createCompanyCreditRequest(
        Company $company,
        int $amount,
        ?PaymentAccount $paymentAccount = null,
        ?string $receiptPath = null,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): DepositRequest {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Deposit amount must be greater than zero.');
        }

        return DepositRequest::create([
            'company_id' => $company->id,
            'user_id' => null,
            'payment_account_id' => $paymentAccount?->id,
            'type' => DepositRequest::TYPE_COMPANY_CREDIT,
            'amount' => $amount,
            'currency' => 'USD',
            'status' => DepositRequest::STATUS_PENDING,
            'receipt_path' => $receiptPath,
            'reference_number' => $referenceNumber,
            'notes' => $notes,
        ]);
    }

    /**
     * Create a player wallet deposit request with payment receipt.
     */
    public function createPlayerDepositRequest(
        User $user,
        int $amount,
        ?PaymentAccount $paymentAccount = null,
        ?string $receiptPath = null,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): DepositRequest {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Deposit amount must be greater than zero.');
        }

        return DepositRequest::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'payment_account_id' => $paymentAccount?->id,
            'type' => DepositRequest::TYPE_PLAYER_DEPOSIT,
            'amount' => $amount,
            'currency' => 'USD',
            'status' => DepositRequest::STATUS_PENDING,
            'receipt_path' => $receiptPath,
            'reference_number' => $referenceNumber,
            'notes' => $notes,
        ]);
    }

    /**
     * Approve a deposit request.
     * For company credit: increments company credit balance & logs Transaction::TYPE_CREDIT_PURCHASE.
     * For player deposit: increments player wallet balance & logs Transaction::TYPE_DEPOSIT via Ledger.
     */
    public function approve(DepositRequest $depositRequest, User $reviewer, ?string $notes = null): DepositRequest
    {
        return DB::transaction(function () use ($depositRequest, $reviewer, $notes) {
            /** @var DepositRequest $locked */
            $locked = DepositRequest::where('id', $depositRequest->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new DomainException("Deposit request #{$locked->id} is already {$locked->status}.");
            }

            if ($locked->isCompanyCredit()) {
                $company = Company::where('id', $locked->company_id)->lockForUpdate()->firstOrFail();
                $balanceBefore = (int) $company->credit_balance;
                $balanceAfter = $balanceBefore + $locked->amount;
                $company->update(['credit_balance' => $balanceAfter]);

                Transaction::create([
                    'company_id' => $company->id,
                    'user_id' => null,
                    'type' => Transaction::TYPE_CREDIT_PURCHASE,
                    'amount' => $locked->amount,
                    'currency' => $locked->currency ?? 'USD',
                    'status' => Transaction::STATUS_COMPLETED,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'reference_type' => DepositRequest::class,
                    'reference_id' => $locked->id,
                    'reference_code' => "CREQ-{$locked->id}",
                    'description' => 'Platform credit deposit approved (Ref: '.($locked->reference_number ?? 'N/A').')',
                ]);
            } else {
                $user = User::where('id', $locked->user_id)->lockForUpdate()->firstOrFail();
                $this->ledgerService->recordDeposit(
                    $user,
                    $locked->amount,
                    referenceCode: "DEP-REQ-{$locked->id}",
                    description: 'Deposit approved by '.($reviewer->isGameManager() ? 'manager' : 'admin').' (Ref: '.($locked->reference_number ?? 'N/A').')',
                    managerId: $reviewer->isGameManager() ? $reviewer->id : null
                );
            }

            $locked->update([
                'status' => DepositRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'reviewer_notes' => $notes,
            ]);

            return $locked->fresh(['user', 'company', 'paymentAccount', 'reviewer']);
        });
    }

    /**
     * Reject a deposit request with an optional reason.
     */
    public function reject(DepositRequest $depositRequest, User $reviewer, ?string $rejectionReason = null): DepositRequest
    {
        return DB::transaction(function () use ($depositRequest, $reviewer, $rejectionReason) {
            /** @var DepositRequest $locked */
            $locked = DepositRequest::where('id', $depositRequest->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new DomainException("Deposit request #{$locked->id} is already {$locked->status}.");
            }

            $locked->update([
                'status' => DepositRequest::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'reviewer_notes' => $rejectionReason,
            ]);

            return $locked->fresh(['user', 'company', 'paymentAccount', 'reviewer']);
        });
    }
}

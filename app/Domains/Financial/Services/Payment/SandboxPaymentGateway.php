<?php

namespace App\Domains\Financial\Services\Payment;

use App\Domains\Financial\Contracts\PaymentGatewayInterface;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Financial\Services\LedgerService;
use App\Models\User;

class SandboxPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Process a wallet deposit for a user in sandbox mode.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function deposit(User $user, int $amountInCents, array $metadata = []): Transaction
    {
        $referenceCode = $metadata['reference_code'] ?? ('SBX-DEP-'.bin2hex(random_bytes(6)));
        $description = $metadata['description'] ?? 'Instant Sandbox Deposit';

        return $this->ledgerService->recordDeposit($user, $amountInCents, $referenceCode, $description);
    }

    /**
     * Process a wallet withdrawal for a user in sandbox mode.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withdraw(User $user, int $amountInCents, array $metadata = []): Transaction
    {
        $referenceCode = $metadata['reference_code'] ?? ('SBX-WTH-'.bin2hex(random_bytes(6)));
        $description = $metadata['description'] ?? 'Instant Sandbox Withdrawal';

        return $this->ledgerService->recordWithdrawal($user, $amountInCents, $referenceCode, $description);
    }

    /**
     * Get the identifier name of this payment gateway.
     */
    public function getName(): string
    {
        return 'sandbox';
    }
}

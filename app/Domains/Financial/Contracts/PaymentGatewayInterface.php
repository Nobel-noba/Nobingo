<?php

namespace App\Domains\Financial\Contracts;

use App\Domains\Financial\Models\Transaction;
use App\Models\User;

interface PaymentGatewayInterface
{
    /**
     * Process a wallet deposit for a user.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function deposit(User $user, int $amountInCents, array $metadata = []): Transaction;

    /**
     * Process a wallet withdrawal for a user.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withdraw(User $user, int $amountInCents, array $metadata = []): Transaction;

    /**
     * Get the identifier name of this payment gateway.
     */
    public function getName(): string;
}

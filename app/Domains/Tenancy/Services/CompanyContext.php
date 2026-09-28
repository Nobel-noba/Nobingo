<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Models\Company;

class CompanyContext
{
    protected ?Company $currentCompany = null;

    /**
     * Set the current active company context.
     */
    public function setCompany(?Company $company): void
    {
        $this->currentCompany = $company;
    }

    /**
     * Get the current active company.
     */
    public function getCompany(): ?Company
    {
        return $this->currentCompany;
    }

    /**
     * Get the ID of the current active company.
     */
    public function getId(): ?int
    {
        return $this->currentCompany?->id;
    }

    /**
     * Check if a tenant context is currently set.
     */
    public function hasCompany(): bool
    {
        return $this->currentCompany !== null;
    }

    /**
     * Clear the company context.
     */
    public function clear(): void
    {
        $this->currentCompany = null;
    }
}

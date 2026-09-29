<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Record an immutable audit log entry.
     *
     * @param  array<string, mixed>  $details
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        ?string $description = null,
        array $details = [],
        ?User $actor = null,
        ?int $companyId = null
    ): AuditLog {
        $actor = $actor ?? (auth()->check() ? auth()->user() : null);

        if ($companyId === null) {
            $companyId = $auditable?->company_id ?? $actor?->company_id;
        }

        if (! isset($details['ip_address']) && request()) {
            $details['ip_address'] = request()->ip();
            $details['user_agent'] = request()->userAgent();
        }

        return AuditLog::create([
            'company_id' => $companyId,
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'description' => $description,
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}

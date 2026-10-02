<?php

namespace App\Http\Controllers\Company;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display a paginated log of immutable audit events for this tenant.
     */
    public function index(Request $request, Company $company): Response
    {
        $action = $request->query('action');
        $search = $request->query('search');

        $user = $request->user();
        $isGameManagerOnly = $user->isGameManager() && ! $user->isCompanyAdmin() && ! $user->isPlatformOwner();

        $query = AuditLog::where('company_id', $company->id)
            ->with('user:id,name,email')
            ->latest('id');

        if ($isGameManagerOnly) {
            $query->where('user_id', $user->id);
        }

        if ($action && $action !== 'ALL') {
            $query->where('action', $action);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $logs = $query->paginate(25)->withQueryString()->through(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'description' => $log->description,
            'auditable_type' => class_basename($log->auditable_type ?? ''),
            'auditable_id' => $log->auditable_id,
            'details' => $log->details,
            'user' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'email' => $log->user->email,
            ] : null,
            'created_at' => $log->created_at->format('M d, Y H:i:s'),
        ]);

        return Inertia::render('Company/AuditLogs/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'logs' => $logs,
            'filters' => [
                'action' => $action ?? 'ALL',
                'search' => $search ?? '',
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Company;

use App\Domains\Auth\Models\Role;
use App\Domains\Reports\Services\ReportingService;
use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        protected ReportingService $reportingService
    ) {}

    /**
     * Display executive analytics and performance reporting for this tenant.
     */
    public function index(Request $request, Company $company): Response
    {
        $user = $request->user();
        $managerId = $request->filled('manager_id') ? (int) $request->query('manager_id') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $report = $this->reportingService->getCompanyReport(
            company: $company,
            managerId: $managerId,
            startDate: $startDate,
            endDate: $endDate,
            currentUser: $user
        );

        $managers = [];
        $isCompanyAdmin = $user && ($user->isCompanyAdmin() || $user->isPlatformOwner());
        if ($isCompanyAdmin) {
            $managers = User::where('company_id', $company->id)
                ->whereHas('roles', fn ($q) => $q->where('slug', Role::GAME_MANAGER))
                ->orderBy('name')
                ->select(['id', 'name', 'email'])
                ->get();
        }

        return Inertia::render('Company/Reports/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'report' => $report,
            'managers' => $managers,
        ]);
    }
}

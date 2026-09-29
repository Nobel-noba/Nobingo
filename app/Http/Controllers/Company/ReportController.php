<?php

namespace App\Http\Controllers\Company;

use App\Domains\Reports\Services\ReportingService;
use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
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
    public function index(Company $company): Response
    {
        $report = $this->reportingService->getCompanyReport($company);

        return Inertia::render('Company/Reports/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'report' => $report,
        ]);
    }
}

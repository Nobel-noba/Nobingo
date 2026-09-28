<?php

namespace App\Http\Middleware;

use App\Domains\Tenancy\Models\Company;
use App\Domains\Tenancy\Services\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(
        protected CompanyContext $companyContext
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $companyParam = $request->route('company');
        $company = null;

        if ($companyParam instanceof Company) {
            $company = $companyParam;
        } elseif (is_string($companyParam) || is_numeric($companyParam)) {
            $company = Company::where('slug', $companyParam)
                ->orWhere('id', $companyParam)
                ->first();

            if (! $company) {
                abort(404, 'Company not found.');
            }
        } elseif ($request->user() && $request->user()->company_id) {
            $company = $request->user()->company;
        }

        if ($company) {
            if (! $company->isActive() && ! ($request->user()?->isPlatformOwner())) {
                abort(403, 'This company account is currently suspended.');
            }

            $user = $request->user();
            if ($user && ! $user->isPlatformOwner()) {
                if ($user->company_id !== null && $user->company_id !== $company->id) {
                    abort(403, 'Unauthorized access to this company.');
                }
            }

            $this->companyContext->setCompany($company);
        }

        return $next($request);
    }
}

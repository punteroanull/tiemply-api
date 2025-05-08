<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkLog;
use App\Models\Absence;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\Role;
use App\Policies\UserPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\WorkLogPolicy;
use App\Policies\AbsencePolicy;
use App\Policies\AbsenceRequestPolicy;
use App\Policies\AbsenceTypePolicy;
use App\Policies\RolePolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Company::class => CompanyPolicy::class,
        Employee::class => EmployeePolicy::class,
        WorkLog::class => WorkLogPolicy::class,
        Absence::class => AbsencePolicy::class,
        AbsenceRequest::class => AbsenceRequestPolicy::class,
        AbsenceType::class => AbsenceTypePolicy::class,
        Role::class => RolePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Super Admin bypass
        Gate::before(function (User $user, $ability) {
            if ($user->isAdmin() && $user->role?->access_level === 5) {
                return true;
            }
            
            return null; // null means fall through to policy
        });
    }
}
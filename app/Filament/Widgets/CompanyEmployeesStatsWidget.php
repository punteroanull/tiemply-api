<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Employee;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CompanyEmployeesStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = Auth::user();
        $stats = [];

        if ($user->hasRole("Administrator")) {
            $totalCompanies = Company::count();
            $totalEmployees = Employee::where("active", true)->count();

            $stats[] = Stat::make("Total Companies", $totalCompanies)
                ->description("Active companies in system")
                ->color("info");

            $stats[] = Stat::make("Total Active Employees", $totalEmployees)
                ->description("Across all companies")
                ->color("success");

        } elseif ($user->hasRole("Manager")) {
            $companies = $user->companiesEloquent;
            $totalEmployees = Employee::whereIn("company_id", $companies->pluck("id"))
                ->where("active", true)
                ->count();

            $stats[] = Stat::make("My Companies", $companies->count())
                ->description("Companies you manage")
                ->color("info");

            $stats[] = Stat::make("Total Employees", $totalEmployees)
                ->description("Active employees in your companies")
                ->color("success");
        }

        return $stats;
    }
}
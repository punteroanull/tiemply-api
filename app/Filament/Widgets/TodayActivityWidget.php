<?php

namespace App\Filament\Widgets;

use App\Models\WorkLog;
use App\Models\Employee;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TodayActivityWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $today = Carbon::today();
        $user = Auth::user();

        if ($user->hasRole("Administrator")) {
            $employeeIds = Employee::where("active", true)->pluck("id");
        } elseif ($user->hasRole("Manager")) {
            $companyIds = $user->companiesEloquent->pluck("id");
            $employeeIds = Employee::where("active", true)
                ->whereIn("company_id", $companyIds)
                ->pluck("id");
        } else {
            $employeeIds = $user->employeeRecords()
                ->where("active", true)
                ->pluck("id");
        }

        $todayCheckIns = WorkLog::whereIn("employee_id", $employeeIds)
            ->whereDate("date", $today)
            ->where("type", "check_in")
            ->count();

        $todayCheckOuts = WorkLog::whereIn("employee_id", $employeeIds)
            ->whereDate("date", $today)
            ->where("type", "check_out")
            ->count();

        return [
            Stat::make("Check-ins Today", $todayCheckIns)
                ->description("Employees who started work")
                ->color("success"),
            Stat::make("Check-outs Today", $todayCheckOuts)
                ->description("Employees who finished work")
                ->color("warning"),
        ];
    }
}
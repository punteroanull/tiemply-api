<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PendingAbsenceRequestsWidget;
use App\Filament\Widgets\CompanyEmployeesStatsWidget;
use App\Filament\Widgets\MyCompaniesWidget;
use App\Filament\Widgets\ApplicationStatsWidget;
use App\Filament\Widgets\TodayActivityWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = "heroicon-o-home";
    
    protected static string $view = "filament.pages.dashboard";

    public function getWidgets(): array
    {
        $user = Auth::user();
        $widgets = [];

        $widgets[] = TodayActivityWidget::class;
        $widgets[] = CompanyEmployeesStatsWidget::class;

        if ($user && ($user->hasRole("Administrator") || $user->hasRole("Manager"))) {
            $widgets[] = PendingAbsenceRequestsWidget::class;
        }

        $widgets[] = MyCompaniesWidget::class;
        $widgets[] = ApplicationStatsWidget::class;

        return $widgets;
    }

    public function getTitle(): string
    {
        $user = Auth::user();
        $greeting = $this->getGreeting();
        
        if ($user) {
            return "{$greeting}, {$user->name}!";
        }
        
        return $greeting;
    }

    protected function getGreeting(): string
    {
        $hour = now()->hour;
        
        if ($hour < 12) {
            return "Good morning";
        } elseif ($hour < 17) {
            return "Good afternoon";
        } else {
            return "Good evening";
        }
    }
}
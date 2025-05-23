<?php

namespace App\Filament\Widgets;

use App\Models\AbsenceRequest;
use App\Models\WorkLog;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class ApplicationStatsWidget extends ChartWidget
{
    protected static ?string $heading = "Activity Overview (Last 30 Days)";
    
    protected static ?int $sort = 4;
    
    protected int | string | array $columnSpan = "full";

    protected function getData(): array
    {
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $dates = [];
        $absenceRequestsData = [];
        $workLogsData = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->format("Y-m-d");
            $dates[] = $date->format("M j");

            $absenceRequestsData[] = AbsenceRequest::whereDate("created_at", $dateString)->count();
            $workLogsData[] = WorkLog::whereDate("date", $dateString)->count();
        }

        return [
            "datasets" => [
                [
                    "label" => "Absence Requests",
                    "data" => $absenceRequestsData,
                    "borderColor" => "#f59e0b",
                    "backgroundColor" => "rgba(245, 158, 11, 0.1)",
                ],
                [
                    "label" => "Work Logs",
                    "data" => $workLogsData,
                    "borderColor" => "#10b981",
                    "backgroundColor" => "rgba(16, 185, 129, 0.1)",
                ],
            ],
            "labels" => $dates,
        ];
    }

    protected function getType(): string
    {
        return "line";
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallDashboardCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiemply:install-dashboard {--force : Overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the custom Tiemply dashboard and widgets for FilamentPHP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Installing Tiemply Dashboard...');
        $this->newLine();

        $force = $this->option('force');

        // Create directories
        $this->createDirectories();

        // Install widgets
        $this->installWidgets($force);

        // Install dashboard page
        $this->installDashboard($force);

        // Install views
        $this->installViews($force);

        // Update AdminPanelProvider if needed
        $this->updateAdminPanelProvider();

        $this->newLine();
        $this->info('✅ Tiemply Dashboard installed successfully!');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('1. Clear your application cache: php artisan cache:clear');
        $this->line('2. Visit /admin to see your new dashboard');
        $this->newLine();
        $this->warn('Note: Make sure your AdminPanelProvider uses the custom Dashboard class.');
    }

    protected function createDirectories()
    {
        $directories = [
            app_path('Filament/Widgets'),
            app_path('Filament/Pages'),
            resource_path('views/filament/pages'),
        ];

        foreach ($directories as $directory) {
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
                $this->line("Created directory: {$directory}");
            }
        }
    }

    protected function installWidgets($force)
    {
        $widgets = [
            'PendingAbsenceRequestsWidget',
            'CompanyEmployeesStatsWidget',
            'MyCompaniesWidget',
            'ApplicationStatsWidget',
            'TodayActivityWidget',
        ];

        foreach ($widgets as $widget) {
            $this->installWidget($widget, $force);
        }
    }

    protected function installWidget($widgetName, $force)
    {
        $path = app_path("Filament/Widgets/{$widgetName}.php");
        
        if (File::exists($path) && !$force) {
            $this->warn("Widget {$widgetName} already exists. Use --force to overwrite.");
            return;
        }

        $content = $this->getWidgetContent($widgetName);
        
        if ($content) {
            File::put($path, $content);
            $this->line("✅ Installed widget: {$widgetName}");
        } else {
            $this->error("❌ Failed to install widget: {$widgetName}");
        }
    }

    protected function installDashboard($force)
    {
        $path = app_path('Filament/Pages/Dashboard.php');
        
        if (File::exists($path) && !$force) {
            $this->warn("Dashboard already exists. Use --force to overwrite.");
            return;
        }

        $content = $this->getDashboardContent();
        File::put($path, $content);
        $this->line("✅ Installed custom Dashboard");
    }

    protected function installViews($force)
    {
        $path = resource_path('views/filament/pages/dashboard.blade.php');
        
        if (File::exists($path) && !$force) {
            $this->warn("Dashboard view already exists. Use --force to overwrite.");
            return;
        }

        $content = $this->getDashboardViewContent();
        File::put($path, $content);
        $this->line("✅ Installed dashboard view");
    }

    protected function updateAdminPanelProvider()
    {
        $path = app_path('Providers/Filament/AdminPanelProvider.php');
        
        if (!File::exists($path)) {
            $this->warn("AdminPanelProvider not found. Please update it manually.");
            return;
        }

        $content = File::get($path);
        
        // Check if already using custom dashboard
        if (str_contains($content, 'App\Filament\Pages\Dashboard')) {
            $this->line("✅ AdminPanelProvider already configured");
            return;
        }

        $this->warn("⚠️  Please update your AdminPanelProvider manually:");
        $this->line("Replace Pages\Dashboard::class with App\Filament\Pages\Dashboard::class in the pages() method.");
    }

    protected function getWidgetContent($widgetName)
    {
        // This would contain the actual widget content
        // For brevity, returning a template here
        
        $widgets = [
            'PendingAbsenceRequestsWidget' => $this->getPendingAbsenceRequestsWidgetContent(),
            'CompanyEmployeesStatsWidget' => $this->getCompanyEmployeesStatsWidgetContent(),
            'MyCompaniesWidget' => $this->getMyCompaniesWidgetContent(),
            'ApplicationStatsWidget' => $this->getApplicationStatsWidgetContent(),
            'TodayActivityWidget' => $this->getTodayActivityWidgetContent(),
        ];

        return $widgets[$widgetName] ?? null;
    }

    protected function getPendingAbsenceRequestsWidgetContent()
    {
        return '<?php

namespace App\Filament\Widgets;

use App\Models\AbsenceRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Actions\ActionGroup;
use Filament\Forms;
use Illuminate\Http\Request;

class PendingAbsenceRequestsWidget extends BaseWidget
{
    protected static ?string $heading = "Pending Absence Requests";
    
    protected int | string | array $columnSpan = "full";
    
    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->defaultSort("created_at", "desc")
            ->columns([
                Tables\Columns\TextColumn::make("employee.user.name")
                    ->label("Employee")
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make("absenceType.name")
                    ->label("Type")
                    ->badge(),
                Tables\Columns\TextColumn::make("start_date")
                    ->label("Start Date")
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make("end_date")
                    ->label("End Date")
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make("days_count")
                    ->label("Days")
                    ->alignCenter(),
                Tables\Columns\TextColumn::make("created_at")
                    ->label("Requested")
                    ->since()
                    ->sortable(),
            ])
            ->emptyStateHeading("No pending requests")
            ->emptyStateDescription("All absence requests have been processed.");
    }

    protected function getTableQuery(): Builder
    {
        $query = AbsenceRequest::query()
            ->where("status", "pending")
            ->with(["employee.user", "absenceType"]);

        if (Auth::user()->hasRole("Administrator")) {
            return $query;
        }

        if (Auth::user()->hasRole("Manager")) {
            $companyIds = Auth::user()->companiesEloquent->pluck("id");
            return $query->whereHas("employee", function (Builder $query) use ($companyIds) {
                $query->whereIn("company_id", $companyIds);
            });
        }

        return $query->whereRaw("1 = 0");
    }
}';
    }

    protected function getCompanyEmployeesStatsWidgetContent()
    {
        return '<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Employee;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CompanyEmployeesStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

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
}';
    }

    protected function getMyCompaniesWidgetContent()
    {
        return '<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MyCompaniesWidget extends BaseWidget
{
    protected static ?string $heading = "My Companies";
    
    protected int | string | array $columnSpan = "full";
    
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make("name")
                    ->label("Company Name")
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make("employees_count")
                    ->label("Active Employees")
                    ->alignCenter()
                    ->badge()
                    ->color("success"),
                Tables\Columns\TextColumn::make("vacation_type")
                    ->label("Vacation Type")
                    ->badge(),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $user = Auth::user();

        if ($user->hasRole("Administrator")) {
            return Company::withCount(["employees" => function ($query) {
                $query->where("active", true);
            }]);
        } elseif ($user->hasRole("Manager")) {
            $companyIds = $user->companiesEloquent->pluck("id");
            return Company::whereIn("id", $companyIds)
                ->withCount(["employees" => function ($query) {
                    $query->where("active", true);
                }]);
        }

        $companyIds = $user->employeeRecords()
            ->where("active", true)
            ->pluck("company_id");
        return Company::whereIn("id", $companyIds)
            ->withCount(["employees" => function ($query) {
                $query->where("active", true);
            }]);
    }
}';
    }

    protected function getApplicationStatsWidgetContent()
    {
        return '<?php

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
}';
    }

    protected function getTodayActivityWidgetContent()
    {
        return '<?php

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
}';
    }

    protected function getDashboardContent()
    {
        return '<?php

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
}';
    }

    protected function getDashboardViewContent()
    {
        return '<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Welcome to Tiemply
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Your comprehensive employee time management system
                </p>
            </div>
        </div>

        <x-filament-widgets::widgets
            :widgets="$this->getVisibleWidgets()"
            :columns="$this->getColumns()"
        />
    </div>
</x-filament-panels::page>';
    }
}
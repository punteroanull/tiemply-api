<?php

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
}
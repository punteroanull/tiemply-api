<?php

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
            ->emptyStateDescription("All absence requests have been processed.")
            ->filters([
                Tables\Filters\SelectFilter::make('absence_type_id')
                    ->label('Absence Type')
                    ->options(fn () => \App\Models\AbsenceType::pluck('name', 'id')->toArray()),
    
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        if ($record->status !== 'pending') {
                            throw new \Exception('Only pending requests can be approved.');
                        }
                        $request = new Request([
                            'request_id' => $record->id,
                        ]);
                        app(\App\Http\Controllers\AbsenceRequestController::class)->approve($request);
                }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        if ($record->status !== 'pending') {
                            throw new \Exception('Only pending requests can be rejected.');
                        }

                        $request = new Request([
                            'request_id' => $record->id,
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        app(\App\Http\Controllers\AbsenceRequestController::class)->reject($request);
                }),
                Tables\Actions\EditAction::make(),
                ])
                ->button()
                ->label('Actions')
            ])
            ->defaultPaginationPageOption(5);
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
}
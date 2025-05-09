<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbsenceRequestResource\Pages;
use App\Filament\Resources\AbsenceRequestResource\RelationManagers;
use App\Models\AbsenceRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Http\Request;
class AbsenceRequestResource extends Resource
{
    protected static ?string $model = AbsenceRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationGroup = 'Absences';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([                
                Forms\Components\Select::make('employee_id')
                ->label('Employee')
                ->options(fn () => \App\Models\Employee::query()
                    ->whereHas('company', fn ($query) => $query->whereIn('id', auth()->user()->companiesEloquent->pluck('id')))
                    ->with('user') // Carga la relación con User
                    ->get()
                    ->pluck('user.name', 'id') // Obtén los nombres de los usuarios y los IDs de los empleados
                    ->toArray()
                )
                ->searchable() // Permite buscar por nombre
                ->required(),
                Forms\Components\Select::make('absence_type_id')
                    ->relationship('absenceType', 'name')
                    ->label('Absence Type')
                    ->required(),
                Forms\Components\DatePicker::make('start_date')
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->required(),
                Forms\Components\Toggle::make('is_partial')
                    ->required(),
                Forms\Components\TimePicker::make('start_time')
                    ->label('Start Time')
                    ->time('H:i'),
                Forms\Components\TimePicker::make('end_time')
                    ->label('End Time')
                    ->time('H:i'),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('employee_id')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('employee.user.name')
                    ->label('Employee Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('absenceType.name')
                    ->label('Absence Type')
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->absenceType?->name ?? 'N/A'),
                Tables\Columns\TextColumn::make('days_count')
                    ->label('Days')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_partial')
                    ->boolean(),
                Tables\Columns\TextColumn::make('start_time')
                    ->time('H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('end_time')
                    ->time('H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('reviewer.name')
                    ->label('Reviewed By')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('rejection_reason')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAbsenceRequests::route('/'),
            'create' => Pages\CreateAbsenceRequest::route('/create'),
            'edit' => Pages\EditAbsenceRequest::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Si el usuario es Administrador, puede ver todos los empleados
        if (Auth::user()->hasRole('Administrator')) {
            return $query;
        }

        // Si el usuario es Manager, filtra los empleados de las empresas a las que pertenece
        if (Auth::user()->hasRole('Manager')) {
            $companyIds = Auth::user()->companies()->pluck('id'); 
            return $query->whereHas('employee', function (Builder $query) use ($companyIds) {
                $query->whereIn('company_id', $companyIds);
            });
        }

        // Por defecto, no mostrar nada si no tiene permisos
        return $query->whereRaw('1 = 0');
    }
}

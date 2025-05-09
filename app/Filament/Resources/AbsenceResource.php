<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbsenceResource\Pages;
use App\Filament\Resources\AbsenceResource\RelationManagers;
use App\Models\Absence;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
class AbsenceResource extends Resource
{
    protected static ?string $model = Absence::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationGroup = 'Absences';
    protected static ?int $navigationSort = 1;

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
                Forms\Components\TextInput::make('request_id')
                    ->hidden(),
                Forms\Components\DatePicker::make('date')
                    ->required(),
                Forms\Components\Toggle::make('is_partial')
                    ->required(),
                Forms\Components\TimePicker::make('start_time')
                    ->label('Start Time')
                    ->time('H:i'),
                Forms\Components\TimePicker::make('end_time')
                    ->label('End Time')
                    ->time('H:i'),
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
                    ->label('Employee ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('employee.user.name')
                    ->label('Employee Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('absenceType.name')
                    ->label('Absence Type')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('request_id')
                    ->label('Request ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_partial')
                    ->boolean(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start Time')
                    ->time('H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('end_time')
                    ->label('Start Time')
                    ->time('H:i')
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
                //
                Tables\Filters\SelectFilter::make('Absence Type')
                ->relationship('absenceType', 'name')
                ->options(fn () => WorkLog::query()->distinct()->pluck('type', 'type')->toArray())
                ->label('Type'),
                Tables\Filters\Filter::make('date_range')
                ->label('Date Range')
                ->form([
                    Forms\Components\DatePicker::make('date_from')
                        ->label('From'),
                    Forms\Components\DatePicker::make('date_until')
                        ->label('Until'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['date_from'],
                            fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                        )
                        ->when(
                            $data['date_until'],
                            fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                        );
                }),])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListAbsences::route('/'),
            'create' => Pages\CreateAbsence::route('/create'),
            'edit' => Pages\EditAbsence::route('/{record}/edit'),
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

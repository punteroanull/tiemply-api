<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkLogResource\Pages;
use App\Filament\Resources\WorkLogResource\RelationManagers;
use App\Models\WorkLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class WorkLogResource extends Resource
{
    protected static ?string $model = WorkLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-date-range';
    protected static ?string $navigationGroup = 'Journal';
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
                Forms\Components\DatePicker::make('date')
                    ->required(),
                Forms\Components\TextInput::make('time')
                    ->required(),
                Forms\Components\TextInput::make('type')
                    ->required(),
                Forms\Components\TextInput::make('category'),
                Forms\Components\TextInput::make('paired_log_id'),
                Forms\Components\TextInput::make('ip_address')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('location')
                    ->maxLength(255)
                    ->default(null),
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('employee_id')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('employee.user.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('time')
                    ->dateTime('H:i'),
                Tables\Columns\TextColumn::make('type')
                    ->sortable(),
                Tables\Columns\TextColumn::make('category'),
                Tables\Columns\TextColumn::make('paired_log_id')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ip_address')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('location')
                    ->searchable()
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
                Tables\Filters\SelectFilter::make('category')
                    ->options(fn () => WorkLog::query()->distinct()->pluck('category', 'category')->toArray())
                    ->label('Category'),
                Tables\Filters\SelectFilter::make('type')
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
            'index' => Pages\ListWorkLogs::route('/'),
            'create' => Pages\CreateWorkLog::route('/create'),
            'edit' => Pages\EditWorkLog::route('/{record}/edit'),
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

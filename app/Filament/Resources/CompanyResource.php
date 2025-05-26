<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Filament\Resources\CompanyResource\RelationManagers;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Forms\Components\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Companies';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('tax_id')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('contact_email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('contact_person')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('address')
                    ->required()
                    ->maxLength(255)
                    ->suffixAction(
                        Action::make('Buscar coordenadas')
                            ->icon('heroicon-o-map-pin')
                            ->action(function ($state, $livewire) {
                                // Llama a un método Livewire para buscar coordenadas
                                $livewire->getCoordinatesFromAddress($state);
                            })
                ),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('vacation_type')
                    ->required(),
                Forms\Components\TextInput::make('max_vacation_days')
                    ->required()
                    ->numeric()
                    ->default(22),
                // NUEVOS CAMPOS PARA GEOLOCALIZACIÓN
                Forms\Components\Toggle::make('geolocation_enabled')
                    ->label('Geolocation Enabled'),
                Forms\Components\Toggle::make('geolocation_required')
                    ->label('Geolocation Required'),

                Forms\Components\TextInput::make('geolocation_radius')
                    ->numeric()
                    ->step(1)
                    ->minValue(0)
                    ->label('Geolocation Radius (meters)')
                    ->default(100),

                Forms\Components\TextInput::make('office_latitude')
                    ->numeric()
                    ->step(0.0000001)
                    ->label('Office Latitude'),

                Forms\Components\TextInput::make('office_longitude')
                    ->numeric()
                    ->step(0.0000001)
                    ->label('Office Longitude'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tax_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contact_email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contact_person')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('geolocation_enabled')
                    ->boolean()
                    ->name('Geolocation Enabled'),
                Tables\Columns\IconColumn::make('geolocation_required')
                    ->boolean()
                    ->name('Geolocation Required'),
                Tables\Columns\TextColumn::make('address')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('vacation_type'),
                Tables\Columns\TextColumn::make('max_vacation_days')
                    ->numeric()
                    ->sortable(),
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
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
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
            RelationManagers\EmployeesRelationManager::class, 

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
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
            return $query->whereIn('id', $companyIds);
        }

        // Por defecto, no mostrar nada si no tiene permisos
        return $query->whereRaw('1 = 0');
    }
}

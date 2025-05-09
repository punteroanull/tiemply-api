<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\DateTimePicker;

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';
    protected static ?string $recordTitleAttribute = 'user.name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([      
                // Campos relacionados con la tabla User
                Forms\Components\Fieldset::make('User Information')
                    ->relationship('user') // Define la relación con el modelo User
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('identification_number')
                            ->label('DNI')
                            ->required()
                            ->maxLength(14),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                ]),
                Forms\Components\TimePicker::make('contract_start_time')
                    ->format('H:i')
                    ->required(),
                Forms\Components\TimePicker::make('contract_end_time')
                    ->format('H:i')
                    ->required(),
                Forms\Components\TextInput::make('remaining_vacation_days')
                    ->label('Remaining Vacation Days')
                    ->numeric()
                    ->required()
                    ->default(22),
                Forms\Components\Toggle::make('active')
                    ->label('Active')
                    ->default(true),               
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->columns([                
                Tables\Columns\TextColumn::make('user.identification_number')
                    ->label('DNI')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contract_start_time')
                    ->label('Contract Start')
                    ->dateTime('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('contract_end_time')
                    ->label('Contract End')
                    ->dateTime('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining_vacation_days')
                    ->label('Vacation Days')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('New Employee')
                    ->label('New Employee')
                    ->modalHeading('Assign User to Company')
                    ->form([
                        Forms\Components\TextInput::make('search')
                            ->label('Search by DNI or Email')
                            ->placeholder('Enter DNI or Email')
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('filtered_users', $this->getFilteredUsers($state))),
                        Forms\Components\Select::make('user_id')
                            ->label('Select User')
                            ->options(fn (callable $get) => $get('filtered_users') ?? [])
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $this->getRelationship()->create([
                            'user_id' => $data['user_id'],
                            'contract_start_time' => now(), // Default values
                            'contract_end_time' => now()->addYear(),
                            'remaining_vacation_days' => 22,
                            'active' => true,
                        ]);
                    }),
            ])
            ->actions([                
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function getFilteredUsers(string $search): array
    {
        return User::where(function ($query) use ($search) {
            $query->where('email', 'like', "%{$search}%")
                  ->orWhere('identification_number', 'like', "%{$search}%");
        })
        ->whereDoesntHave('employeeRecords', function ($query) {
            $query->where('company_id', $this->ownerRecord->id);
        })
        ->pluck('name', 'id')
        ->toArray();
    }
}

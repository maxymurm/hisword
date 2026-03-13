<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReadingPlanResource\Pages;
use App\Models\ReadingPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReadingPlanResource extends Resource
{
    protected static ?string $model = ReadingPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Plan Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('duration_days')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1095)
                            ->suffix('days'),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_system')
                            ->default(false)
                            ->helperText('System plans are available to all users and cannot be deleted by them'),
                    ])->columns(2),

                Forms\Components\Section::make('Plan Data')
                    ->schema([
                        Forms\Components\KeyValue::make('plan_data')
                            ->label('Daily Readings')
                            ->keyLabel('Day')
                            ->valueLabel('Reading Reference')
                            ->helperText('Define daily reading assignments as key-value pairs (day number → reference)')
                            ->columnSpanFull(),
                    ])->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration_days')
                    ->numeric()
                    ->suffix(' days')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_system')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('progress_count')
                    ->counts('progress')
                    ->label('Active Users')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_system')
                    ->label('System Plan'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReadingPlans::route('/'),
            'create' => Pages\CreateReadingPlan::route('/create'),
            'edit' => Pages\EditReadingPlan::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description'];
    }
}

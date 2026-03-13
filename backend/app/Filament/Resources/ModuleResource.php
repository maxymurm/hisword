<?php

namespace App\Filament\Resources;

use App\Enums\ModuleType;
use App\Filament\Resources\ModuleResource\Pages;
use App\Models\Module;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ModuleResource extends Resource
{
    protected static ?string $model = Module::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Module Information')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->helperText('Unique module identifier (e.g., KJV, ESV)'),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->options(ModuleType::class)
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('language')
                            ->maxLength(10)
                            ->helperText('ISO language code (e.g., en, ar, zh)'),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\TextInput::make('version')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('source_url')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('file_size')
                            ->numeric()
                            ->suffix('bytes')
                            ->helperText('File size in bytes'),
                        Forms\Components\Toggle::make('is_installed')
                            ->default(false),
                        Forms\Components\Toggle::make('is_bundled')
                            ->default(false)
                            ->helperText('Included with the app by default'),
                        Forms\Components\KeyValue::make('features')
                            ->columnSpanFull(),
                    ])->columns(2)->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('language')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_installed')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_bundled')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('version')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('file_size')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024 / 1024, 2) . ' MB' : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(ModuleType::class),
                Tables\Filters\TernaryFilter::make('is_installed')
                    ->label('Installed'),
                Tables\Filters\TernaryFilter::make('is_bundled')
                    ->label('Bundled'),
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
            ->defaultSort('key');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModules::route('/'),
            'create' => Pages\CreateModule::route('/create'),
            'edit' => Pages\EditModule::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['key', 'name', 'description'];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Users';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create'),
                        Forms\Components\Toggle::make('is_admin')
                            ->label('Administrator')
                            ->helperText('Grant admin panel access'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Profile')
                    ->schema([
                        Forms\Components\TextInput::make('timezone')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('locale')
                            ->maxLength(10),
                        Forms\Components\TextInput::make('avatar_url')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(3)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_admin')
                    ->boolean()
                    ->label('Admin'),
                Tables\Columns\TextColumn::make('bookmarks_count')
                    ->counts('bookmarks')
                    ->label('Bookmarks')
                    ->sortable(),
                Tables\Columns\TextColumn::make('notes_count')
                    ->counts('notes')
                    ->label('Notes')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Verified')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_admin')
                    ->label('Admins only'),
                Tables\Filters\Filter::make('verified')
                    ->query(fn ($query) => $query->whereNotNull('email_verified_at'))
                    ->label('Email verified'),
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }
}

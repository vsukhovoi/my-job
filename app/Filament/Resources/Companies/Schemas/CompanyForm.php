<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Models\City;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Назва')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (?string $state, callable $set) =>
                                        $set('slug', \Illuminate\Support\Str::slug($state ?? ''))
                                    ),
                                Select::make('user_id')
                                    ->label('Власник')
                                    ->relationship('user', 'name')
                                    ->required(),
                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->unique(ignoreRecord: true),
                            ]),
                        FileUpload::make('logo')
                            ->label('Логотип')
                            ->image()
                            ->disk('public')
                            ->directory('logos')
                            ->imagePreviewHeight('150')
                            ->panelAspectRatio('1:1')
                            ->panelLayout('integrated')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                            ->maxSize(2048),
                    ]),
                Textarea::make('description')
                    ->label('Опис')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('edrpou')
                    ->label('ЄДРПОУ')
                    ->maxLength(8)
                    ->placeholder('12345678'),
                TextInput::make('website')
                    ->label('Вебсайт')
                    ->prefix('https://')
                    ->placeholder('example.com')
                    ->formatStateUsing(fn (?string $state): string =>
                        $state ? preg_replace('#^https?://#', '', $state) : ''
                    )
                    ->dehydrateStateUsing(fn (?string $state): ?string =>
                        $state ? 'https://' . ltrim(preg_replace('#^https?://#', '', $state), '/') : null
                    ),
                Select::make('city_id')
                    ->label('Місто')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (?string $state, callable $set) =>
                        $set('location', $state ? (City::find($state)?->name ?? '') : '')
                    ),

                Placeholder::make('verification_status')
                    ->label('Статус верифікації')
                    ->content(fn($record) => $record?->verification_status?->label() ?? '—'),

                Placeholder::make('verified_name')
                    ->label('Назва в реєстрі')
                    ->content(fn($record) => $record?->verified_name ?? '—'),

                Placeholder::make('verified_at')
                    ->label('Дата верифікації')
                    ->content(fn($record) => $record?->verified_at?->format('d.m.Y H:i') ?? '—'),

                Placeholder::make('verified_by')
                    ->label('Перевірив')
                    ->content(fn($record) => $record?->verifiedBy?->name ?? '—'),
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AddonType;
use App\Models\AddonSetting;
use Filament\Forms\Components\TextInput;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AddonSettingsWidget extends BaseWidget
{
    protected static ?string $heading = 'Додаткові послуги';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 10;
    protected static bool $isDiscoverable = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(AddonSetting::query()->orderByRaw("FIELD(addon_type, 'hot','top','anonymous_publication','cv_access')"))
            ->columns([
                TextColumn::make('addon_type')
                    ->label('Послуга')
                    ->formatStateUsing(fn (string $state): string =>
                        AddonType::tryFrom($state)?->label() ?? $state
                    ),

                TextColumn::make('addon_type')
                    ->label('Тип')
                    ->name('addon_type_code')
                    ->fontFamily('mono')
                    ->color('gray'),

                TextColumn::make('price')
                    ->label('Ціна (₴)')
                    ->suffix(' ₴')
                    ->sortable(),

                TextColumn::make('addon_type')
                    ->label('Тривалість')
                    ->name('duration')
                    ->formatStateUsing(fn (string $state): string => match (AddonType::tryFrom($state)?->durationDays()) {
                        0       => 'Весь термін вакансії',
                        7       => '7 днів',
                        30      => '30 днів',
                        default => '—',
                    }),

                TextColumn::make('addon_type')
                    ->label('Статус')
                    ->name('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string =>
                        $state === 'cv_access' ? 'В розробці' : 'Активна'
                    )
                    ->color(fn (string $state): string =>
                        $state === 'cv_access' ? 'gray' : 'success'
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Редагувати')
                    ->form([
                        TextInput::make('price')
                            ->label('Ціна (₴)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->suffix('₴'),
                    ])
                    ,
            ])
            ->paginated(false);
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\Vacancies\Tables;

use App\Enums\VacancyPublicationType;
use App\Enums\VacancyStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VacanciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('title')
                    ->label('Назва')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('company.name')
                    ->label('Компанія')
                    ->searchable(),

                TextColumn::make('category.name')
                    ->label('Категорія')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (VacancyStatus $state) => $state->color())
                    ->formatStateUsing(fn (VacancyStatus $state) => $state->label()),

                TextColumn::make('published_at')
                    ->label('Опубліковано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('expires_at')
                    ->label('Завершення')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn ($record) =>
                        $record->is_active && $record->hours_left !== null && $record->hours_left < 72
                            ? 'warning' : null
                    ),

                TextColumn::make('countdown_label')
                    ->label('Залишок')
                    ->placeholder('—'),

                TextColumn::make('publication_type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn ($state) => $state === VacancyPublicationType::Anonymous ? 'warning' : 'gray')
                    ->formatStateUsing(fn ($state) => $state instanceof VacancyPublicationType ? $state->label() : $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('anonymous_name')
                    ->label('Псевдонім')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('auto_refresh')
                    ->label('Авто-оновлення')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('auto_refresh_until')
                    ->label('Авто-оновлення до')
                    ->dateTime('d.m.Y')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('salary_from')
                    ->label('Зарплата від')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('salary_to')
                    ->label('Зарплата до')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Legacy active')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Оновлено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(VacancyStatus::options()),

                Filter::make('expiring_soon')
                    ->label('Завершуються найближчі 3 дні')
                    ->query(fn (Builder $q) => $q
                        ->where('status', VacancyStatus::Active)
                        ->whereNotNull('expires_at')
                        ->whereBetween('expires_at', [now(), now()->addHours(72)])
                    ),
                SelectFilter::make('city_id')
                    ->label('Місто')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()->label('Редагувати'),

                Action::make('extend_30')
                    ->label('Продовжити +30')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn ($record) =>
                        in_array($record->status, [VacancyStatus::Active, VacancyStatus::Expired])
                    )
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->extend(30);
                        Notification::make()
                            ->success()
                            ->title('Вакансію продовжено на 30 днів')
                            ->send();
                    }),

                Action::make('archive')
                    ->label('Архівувати')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status !== VacancyStatus::Archived)
                    ->requiresConfirmation()
                    ->modalDescription('Архівована вакансія повертає 404 за прямим URL. Цю дію можна скасувати лише вручну через зміну статусу.')
                    ->action(function ($record) {
                        $record->archive();
                        Notification::make()
                            ->success()
                            ->title('Вакансію архівовано')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('extend_30_bulk')
                        ->label('Продовжити вибрані на 30 днів')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each(fn ($r) =>
                            in_array($r->status, [VacancyStatus::Active, VacancyStatus::Expired])
                                ? $r->extend(30) : null
                        ))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('archive_bulk')
                        ->label('Архівувати вибрані')
                        ->icon('heroicon-o-archive-box')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each(fn ($r) =>
                            $r->status !== VacancyStatus::Archived ? $r->archive() : null
                        ))
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make()->label('Видалити'),
                ]),
            ])
            ->defaultSort('expires_at', 'asc');
    }
}

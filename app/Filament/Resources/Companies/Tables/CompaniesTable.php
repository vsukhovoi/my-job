<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\Tables;

use App\Enums\CompanyVerificationStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Назва')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Контактна особа')
                    ->searchable()
                    ->toggleable(),
                BadgeColumn::make('verification_status')
                    ->label('Верифікація')
                    ->formatStateUsing(fn($state) => $state instanceof CompanyVerificationStatus ? $state->label() : $state)
                    ->colors([
                        'gray'    => CompanyVerificationStatus::Unverified->value,
                        'success' => CompanyVerificationStatus::Verified->value,
                        'danger'  => CompanyVerificationStatus::Rejected->value,
                    ])
                    ->toggleable(),
                TextColumn::make('city.name')
                    ->label('Місто')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('user.phone')
                    ->label('Телефон')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('user.email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('website')
                    ->label('Вебсайт')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Дата реєстрації')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([])
            ->recordActions([
                Action::make('verify')
                    ->label('Верифікувати')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn($record) => $record->verification_status !== CompanyVerificationStatus::Verified)
                    ->form([
                        TextInput::make('verified_name')
                            ->label('Офіційна назва (з реєстру)')
                            ->default(fn($record) => $record->name)
                            ->required(),
                    ])
                    ->action(function ($record, array $data): void {
                        $record->update([
                            'verification_status' => CompanyVerificationStatus::Verified,
                            'verified_name'       => $data['verified_name'],
                            'verified_at'         => now(),
                            'verified_by'         => Auth::id(),
                        ]);

                        Notification::make()
                            ->title('Компанію верифіковано')
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Відхилити')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Відхилити верифікацію?')
                    ->modalDescription('Статус компанії буде змінено на «Відхилено».')
                    ->visible(fn($record) => $record->verification_status !== CompanyVerificationStatus::Rejected)
                    ->action(function ($record): void {
                        $record->update([
                            'verification_status' => CompanyVerificationStatus::Rejected,
                            'verified_name'       => null,
                            'verified_at'         => null,
                            'verified_by'         => null,
                        ]);

                        Notification::make()
                            ->title('Верифікацію відхилено')
                            ->danger()
                            ->send();
                    }),

                Action::make('reset_verification')
                    ->label('Скинути')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn($record) => $record->verification_status !== CompanyVerificationStatus::Unverified)
                    ->action(function ($record): void {
                        $record->update([
                            'verification_status' => CompanyVerificationStatus::Unverified,
                            'verified_name'       => null,
                            'verified_at'         => null,
                            'verified_by'         => null,
                        ]);
                    }),

                EditAction::make()->label('Редагувати'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Видалити'),
                ]),
            ]);
    }
}

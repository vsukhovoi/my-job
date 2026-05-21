<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    private static function deleteUserCascade(User $user): void
    {
        // candidate-side
        $user->savedVacancies()->detach();
        \DB::table('telegram_sessions')->where('user_id', $user->id)->delete();
        \DB::table('candidate_messages')->where('sender_id', $user->id)->delete();
        \DB::table('application_notes')->where('author_id', $user->id)->delete();
        \DB::table('application_status_history')->where('changed_by', $user->id)->delete();
        \DB::table('interviews')->where('created_by', $user->id)->delete();
        $user->resumes()->delete();

        $applicationIds = $user->applications()->pluck('id');
        if ($applicationIds->isNotEmpty()) {
            \DB::table('application_status_history')->whereIn('application_id', $applicationIds)->delete();
            \DB::table('application_notes')->whereIn('application_id', $applicationIds)->delete();
        }
        $user->applications()->delete();

        \DB::table('employer_subscriptions')->where('user_id', $user->id)->delete();
        \DB::table('orders')->where('user_id', $user->id)->delete();

        // employer-side: cascade through company → vacancies → applications/saved
        $company = $user->company()->withTrashed()->first();
        if ($company) {
            $vacancyIds = \DB::table('vacancies')->where('company_id', $company->id)->pluck('id');
            if ($vacancyIds->isNotEmpty()) {
                $companyApplicationIds = \DB::table('applications')->whereIn('vacancy_id', $vacancyIds)->pluck('id');
                if ($companyApplicationIds->isNotEmpty()) {
                    \DB::table('application_status_history')->whereIn('application_id', $companyApplicationIds)->delete();
                    \DB::table('application_notes')->whereIn('application_id', $companyApplicationIds)->delete();
                }
                \DB::table('saved_vacancies')->whereIn('vacancy_id', $vacancyIds)->delete();
                \DB::table('applications')->whereIn('vacancy_id', $vacancyIds)->delete();
                \DB::table('vacancies')->whereIn('id', $vacancyIds)->delete();
            }
            \DB::table('message_templates')->where('company_id', $company->id)->delete();
            $company->forceDelete();
        }
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('name')
                    ->label("Ім'я")
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('company.name')
                    ->label('Компанія')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('email_verified_at')
                    ->label('Email верифікація')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('telegram_id')
                    ->label('Telegram ID')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('role')
                    ->label('Роль')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\UserRole ? $state->label() : $state)
                    ->toggleable(),
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
            ->filters([])
            ->recordActions([
                EditAction::make()->label('Редагувати'),
                DeleteAction::make()
                    ->label('Видалити')
                    ->before(fn (User $record) => self::deleteUserCascade($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->label('Видалити вибраних')
                        ->color('danger')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each(fn (User $u) => self::deleteUserCascade($u) && $u->delete()))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}

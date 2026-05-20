<?php

declare(strict_types=1);

namespace App\Filament\Resources\InvoiceResource;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Models\Invoice;
use App\Services\InvoiceService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel  = 'Рахунки (IBAN)';
    protected static ?string $modelLabel       = 'Рахунок';
    protected static ?string $pluralModelLabel = 'Рахунки';
    protected static string|UnitEnum|null $navigationGroup = 'Фінанси';
    protected static ?int    $navigationSort   = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Номер')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Роботодавець')
                    ->searchable(),

                Tables\Columns\TextColumn::make('payer_name')
                    ->label('Платник')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Сума')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' грн')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn ($state) => $state instanceof InvoiceStatus ? $state->label() : $state)
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger'  => fn ($state) => $state instanceof InvoiceStatus
                            && in_array($state->value, ['expired', 'cancelled']),
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Оплачено')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('confirm_payment')
                    ->label('Підтвердити оплату')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Pending)
                    ->requiresConfirmation()
                    ->action(function (Invoice $record) {
                        app(InvoiceService::class)->markAsPaid($record, 'manual');
                        Notification::make()
                            ->title('Оплату підтверджено')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
        ];
    }
}

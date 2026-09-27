<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentTransactions\Tables;

use App\Models\PaymentTransaction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PaymentTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('processed_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('gateway')
                    ->label('Провайдер')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'mono'      => 'success',
                        'wayforpay' => 'info',
                        'liqpay'    => 'warning',
                        'stripe'    => 'primary',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'mono'      => 'MonoPay',
                        'wayforpay' => 'WayForPay',
                        'liqpay'    => 'LiqPay',
                        'stripe'    => 'Stripe',
                        default     => ucfirst($state),
                    }),

                TextColumn::make('amount_uah')
                    ->label('Сума')
                    ->state(function (PaymentTransaction $record): string {
                        $amount = $record->amount_uah;
                        return $amount !== null ? number_format($amount, 0, '.', ' ') . ' ₴' : '—';
                    })
                    ->sortable(false),

                TextColumn::make('purpose')
                    ->label('Призначення')
                    ->state(fn (PaymentTransaction $record): string => $record->purpose)
                    ->searchable(false)
                    ->limit(40),

                TextColumn::make('event_id')
                    ->label('ID події')
                    ->limit(24)
                    ->tooltip(fn (PaymentTransaction $record): string => $record->event_id)
                    ->copyable()
                    ->copyMessage('ID скопійовано')
                    ->searchable(),

                TextColumn::make('order_id')
                    ->label('Order ID')
                    ->limit(20)
                    ->tooltip(fn (PaymentTransaction $record): string => $record->order_id)
                    ->copyable()
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('gateway')
                    ->label('Провайдер')
                    ->options([
                        'mono'      => 'MonoPay',
                        'wayforpay' => 'WayForPay',
                        'liqpay'    => 'LiqPay',
                        'stripe'    => 'Stripe',
                    ]),

                SelectFilter::make('days_filter')
                    ->label('Тариф')
                    ->options([
                        '15' => '15 днів',
                        '30' => '30 днів',
                        '90' => '90 днів',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['value'] ?? null)) {
                            return $query;
                        }
                        return $query->where('order_id', 'LIKE', "vac\_%\\_{$data['value']}\\_%" );
                    }),

                Filter::make('processed_at')
                    ->label('Період')
                    ->form([
                        DatePicker::make('from')
                            ->label('З')
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('until')
                            ->label('По')
                            ->displayFormat('d.m.Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn ($q) => $q->whereDate('processed_at', '>=', $data['from'])
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn ($q) => $q->whereDate('processed_at', '<=', $data['until'])
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'З: ' . Carbon::parse($data['from'])->format('d.m.Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'По: ' . Carbon::parse($data['until'])->format('d.m.Y');
                        }
                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make()->label('Деталі'),
            ])
            ->defaultSort('processed_at', 'desc')
            ->poll('60s')
            ->striped()
            ->emptyStateHeading('Платежів ще немає')
            ->emptyStateDescription('Тут з\'являться транзакції після першої оплати.');
    }
}

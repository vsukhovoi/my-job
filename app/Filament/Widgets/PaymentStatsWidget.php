<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\PaymentTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PaymentStatsWidget extends BaseWidget
{
    protected static ?int $sort            = 3;
    protected ?string     $pollingInterval = '300s';

    protected function getStats(): array
    {
        $today     = $this->getRevenueForPeriod('today');
        $thisMonth = $this->getRevenueForPeriod('month');
        $lastMonth = $this->getRevenueForPeriod('last_month');
        $byGateway = $this->getCountByGateway();

        $trend      = $lastMonth['total'] > 0
            ? round(($thisMonth['total'] - $lastMonth['total']) / $lastMonth['total'] * 100)
            : null;
        $trendLabel = $trend !== null
            ? ($trend >= 0 ? "+{$trend}% vs минулий місяць" : "{$trend}% vs минулий місяць")
            : 'Перший місяць';
        $trendColor = ($trend ?? 0) >= 0 ? 'success' : 'danger';

        return [
            Stat::make('Платежів сьогодні', $today['count'])
                ->description(number_format($today['total'], 0, '.', ' ') . ' ₴')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Дохід цього місяця', number_format($thisMonth['total'], 0, '.', ' ') . ' ₴')
                ->description("{$thisMonth['count']} транзакцій · {$trendLabel}")
                ->descriptionIcon($trendColor === 'success' ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($trendColor)
                ->chart($this->getDailyRevenueChart()),

            Stat::make('По провайдерах', $this->formatGatewayBreakdown($byGateway))
                ->description('За поточний місяць')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('info'),
        ];
    }

    /** @return array{count: int, total: float} */
    private function getRevenueForPeriod(string $period): array
    {
        $query = PaymentTransaction::query();

        $query = match ($period) {
            'today'      => $query->whereDate('processed_at', today()),
            'month'      => $query->whereMonth('processed_at', now()->month)
                                  ->whereYear('processed_at', now()->year),
            'last_month' => $query->whereMonth('processed_at', now()->subMonth()->month)
                                  ->whereYear('processed_at', now()->subMonth()->year),
            default      => $query,
        };

        $transactions = $query->get(['event_id', 'order_id', 'amount_kopecks']);
        $count = $transactions->count();

        $total = $transactions->sum(fn (PaymentTransaction $tx): float => $tx->amount_uah ?? 0);

        return compact('count', 'total');
    }

    /** @return array<string, int> */
    private function getCountByGateway(): array
    {
        return PaymentTransaction::query()
            ->whereMonth('processed_at', now()->month)
            ->whereYear('processed_at', now()->year)
            ->select('gateway', DB::raw('COUNT(*) as count'))
            ->groupBy('gateway')
            ->pluck('count', 'gateway')
            ->toArray();
    }

    private function formatGatewayBreakdown(array $byGateway): string
    {
        if (empty($byGateway)) {
            return 'Немає даних';
        }

        $labels = [
            'mono'      => 'MonoPay',
            'wayforpay' => 'WayForPay',
            'liqpay'    => 'LiqPay',
            'stripe'    => 'Stripe',
        ];

        return collect($byGateway)
            ->map(fn ($count, $gw) => ($labels[$gw] ?? $gw) . ': ' . $count)
            ->implode(', ');
    }

    private function getDailyRevenueChart(): array
    {
        return PaymentTransaction::query()
            ->where('processed_at', '>=', now()->subDays(14))
            ->orderBy('processed_at')
            ->get(['event_id', 'order_id', 'amount_kopecks', 'processed_at'])
            ->groupBy(fn (PaymentTransaction $tx): string => $tx->processed_at->toDateString())
            ->map(fn ($day): float => $day->sum(fn (PaymentTransaction $tx): float => $tx->amount_uah ?? 0))
            ->values()
            ->toArray();
    }
}

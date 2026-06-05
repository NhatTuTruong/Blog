<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class BlogStatsDateRange
{
    public const PERIOD_TODAY = 'today';

    public const PERIOD_YESTERDAY = 'yesterday';

    public const PERIOD_LAST_7 = 'last_7_days';

    public const PERIOD_LAST_30 = 'last_30_days';

    public const PERIOD_THIS_MONTH = 'this_month';

    public const PERIOD_LAST_MONTH = 'last_month';

    public const PERIOD_THIS_YEAR = 'this_year';

    public const PERIOD_ALL = 'all';

    public const PERIOD_CUSTOM = 'custom';

    public function __construct(
        public readonly string $period,
        public readonly ?CarbonInterface $start = null,
        public readonly ?CarbonInterface $end = null,
    ) {}

    public static function periodOptions(): array
    {
        return [
            self::PERIOD_TODAY => 'Hôm nay',
            self::PERIOD_YESTERDAY => 'Hôm qua',
            self::PERIOD_LAST_7 => '7 ngày qua',
            self::PERIOD_LAST_30 => '30 ngày qua',
            self::PERIOD_THIS_MONTH => 'Tháng này',
            self::PERIOD_LAST_MONTH => 'Tháng trước',
            self::PERIOD_THIS_YEAR => 'Năm nay',
            self::PERIOD_ALL => 'Tất cả',
            self::PERIOD_CUSTOM => 'Tùy chọn (từ – đến)',
        ];
    }

    public static function fromFilters(?array $filters): self
    {
        $period = (string) ($filters['period'] ?? self::PERIOD_THIS_MONTH);

        if (! array_key_exists($period, self::periodOptions())) {
            $period = self::PERIOD_THIS_MONTH;
        }

        $start = null;
        $end = null;

        if ($period === self::PERIOD_CUSTOM) {
            $startRaw = $filters['startDate'] ?? null;
            $endRaw = $filters['endDate'] ?? null;

            if ($startRaw && $endRaw) {
                $start = Carbon::parse($startRaw)->startOfDay();
                $end = Carbon::parse($endRaw)->endOfDay();

                if ($start->gt($end)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }

                return new self($period, $start, $end);
            }

            $now = Carbon::now();

            return new self($period, $now->copy()->startOfMonth(), $now->copy()->endOfMonth());
        }

        $now = Carbon::now();

        [$start, $end] = match ($period) {
            self::PERIOD_TODAY => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            self::PERIOD_YESTERDAY => [
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
            ],
            self::PERIOD_LAST_7 => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            self::PERIOD_LAST_30 => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            self::PERIOD_THIS_MONTH => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            self::PERIOD_LAST_MONTH => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
            self::PERIOD_THIS_YEAR => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            self::PERIOD_ALL => [null, null],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        return new self($period, $start, $end);
    }

    public function hasRange(): bool
    {
        return $this->start !== null && $this->end !== null;
    }

    public function isAllTime(): bool
    {
        return $this->period === self::PERIOD_ALL || ! $this->hasRange();
    }

    public function label(): string
    {
        if ($this->isAllTime()) {
            return 'Tất cả';
        }

        if ($this->period === self::PERIOD_CUSTOM) {
            return $this->start->format('d/m/Y') . ' – ' . $this->end->format('d/m/Y');
        }

        return self::periodOptions()[$this->period] ?? $this->period;
    }

    /** Kỳ trước cùng độ dài (để so sánh %). */
    public function previous(): ?self
    {
        if (! $this->hasRange()) {
            return null;
        }

        $days = $this->start->diffInDays($this->end) + 1;
        $prevEnd = $this->start->copy()->subDay()->endOfDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1)->startOfDay();

        return new self('previous', $prevStart, $prevEnd);
    }

    public function applyTo(Builder $query, string $column = 'created_at'): Builder
    {
        if (! $this->hasRange()) {
            return $query;
        }

        return $query->whereBetween($column, [$this->start, $this->end]);
    }

    /**
     * @return array<int, string>
     */
    public function dayLabels(): array
    {
        if (! $this->hasRange()) {
            $start = Carbon::now()->subDays(29)->startOfDay();
            $end = Carbon::now()->endOfDay();
        } else {
            $start = $this->start->copy()->startOfDay();
            $end = $this->end->copy()->endOfDay();
        }

        $labels = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $labels[] = $cursor->format('d/m');
            $cursor->addDay();
        }

        return $labels;
    }

    /**
     * @return array<int, string> keys Y-m-d
     */
    public function dayKeys(): array
    {
        if (! $this->hasRange()) {
            $start = Carbon::now()->subDays(29)->startOfDay();
            $end = Carbon::now()->endOfDay();
        } else {
            $start = $this->start->copy()->startOfDay();
            $end = $this->end->copy()->endOfDay();
        }

        $keys = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $keys[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }

        return $keys;
    }
}

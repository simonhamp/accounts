<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyChecklist extends Model
{
    /** @use HasFactory<\Database\Factories\MonthlyChecklistFactory> */
    use HasFactory;

    protected $fillable = [
        'period_month',
        'period_year',
        'items',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'integer',
            'period_year' => 'integer',
            'items' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return array{
     *     suppliers: array<int, array{completed: bool, bill_id: int|null, skipped: bool}>,
     *     income_sources: array<int, array{completed: bool, skipped: bool}>,
     *     invoices_reviewed: bool,
     *     bank_statements_checked: bool,
     *     other_incomes_reviewed: bool
     * }
     */
    public static function defaultItems(): array
    {
        return [
            'suppliers' => [],
            'income_sources' => [],
            'invoices_reviewed' => false,
            'bank_statements_checked' => false,
            'other_incomes_reviewed' => false,
        ];
    }

    public function scopeForMonth(Builder $query, int $month, int $year): Builder
    {
        return $query->where('period_month', $month)->where('period_year', $year);
    }

    public function scopeCurrentMonth(Builder $query): Builder
    {
        return $query->forMonth(now()->month, now()->year);
    }

    public static function findOrCreateForMonth(int $month, int $year): self
    {
        return self::firstOrCreate(
            ['period_month' => $month, 'period_year' => $year],
            ['items' => self::defaultItems()]
        );
    }

    public function getPeriodNameAttribute(): string
    {
        return Carbon::create($this->period_year, $this->period_month)->format('F Y');
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    public function markAsComplete(): void
    {
        $this->update(['completed_at' => now()]);
    }

    public function markAsIncomplete(): void
    {
        $this->update(['completed_at' => null]);
    }

    public function getCompletionPercentage(): int
    {
        $items = $this->items;
        $total = 0;
        $completed = 0;

        // Count suppliers
        foreach ($items['suppliers'] ?? [] as $supplier) {
            $total++;
            if ($supplier['completed'] || $supplier['skipped']) {
                $completed++;
            }
        }

        // Count income sources
        foreach ($items['income_sources'] ?? [] as $incomeSource) {
            $total++;
            if ($incomeSource['completed'] || $incomeSource['skipped']) {
                $completed++;
            }
        }

        // Count general items
        $generalItems = ['invoices_reviewed', 'bank_statements_checked', 'other_incomes_reviewed'];
        foreach ($generalItems as $item) {
            $total++;
            if ($items[$item] ?? false) {
                $completed++;
            }
        }

        if ($total === 0) {
            return 100;
        }

        return (int) round(($completed / $total) * 100);
    }

    public function updateItem(string $section, string|int $key, mixed $value): void
    {
        $items = $this->items;

        if (in_array($section, ['invoices_reviewed', 'bank_statements_checked', 'other_incomes_reviewed'], true)) {
            $items[$section] = (bool) $value;
        } else {
            $items[$section][$key] = array_merge($items[$section][$key] ?? [], is_array($value) ? $value : ['completed' => $value]);
        }

        $this->update(['items' => $items]);

        // Auto-complete if everything is done
        if ($this->getCompletionPercentage() === 100 && ! $this->isComplete()) {
            $this->markAsComplete();
        } elseif ($this->getCompletionPercentage() < 100 && $this->isComplete()) {
            $this->markAsIncomplete();
        }
    }

    public function addSupplier(int $supplierId): void
    {
        $items = $this->items;
        if (! isset($items['suppliers'][$supplierId])) {
            $items['suppliers'][$supplierId] = ['completed' => false, 'bill_id' => null, 'skipped' => false];
            $this->update(['items' => $items]);
        }
    }

    public function addIncomeSource(int $incomeSourceId): void
    {
        $items = $this->items;
        if (! isset($items['income_sources'][$incomeSourceId])) {
            $items['income_sources'][$incomeSourceId] = ['completed' => false, 'skipped' => false];
            $this->update(['items' => $items]);
        }
    }

    public function markSupplierCompleted(int $supplierId, ?int $billId = null): void
    {
        $this->updateItem('suppliers', $supplierId, ['completed' => true, 'bill_id' => $billId, 'skipped' => false]);
    }

    public function markSupplierSkipped(int $supplierId): void
    {
        $this->updateItem('suppliers', $supplierId, ['completed' => false, 'bill_id' => null, 'skipped' => true]);
    }

    public function markIncomeSourceCompleted(int $incomeSourceId): void
    {
        $this->updateItem('income_sources', $incomeSourceId, ['completed' => true, 'skipped' => false]);
    }

    public function markIncomeSourceSkipped(int $incomeSourceId): void
    {
        $this->updateItem('income_sources', $incomeSourceId, ['completed' => false, 'skipped' => true]);
    }

    public function getPendingSupplierIds(): array
    {
        return collect($this->items['suppliers'] ?? [])
            ->filter(fn ($item) => ! $item['completed'] && ! $item['skipped'])
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function getPendingIncomeSourceIds(): array
    {
        return collect($this->items['income_sources'] ?? [])
            ->filter(fn ($item) => ! $item['completed'] && ! $item['skipped'])
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}

<?php

namespace App\Models;

use App\Enums\InvestmentStatus;
use App\Enums\ProfitFrequency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference', 'user_id', 'investment_plan_id', 'amount', 'duration_months', 'status',
    'start_date', 'maturity_date', 'certificate_number', 'certificate_token', 'certificate_issued_at', 'maturity_reminder_sent_at',
    'profit_frequency',
])]
class Investment extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'duration_months' => 'integer',
            'status' => InvestmentStatus::class,
            'profit_frequency' => ProfitFrequency::class,
            'start_date' => 'date',
            'maturity_date' => 'date',
            'certificate_issued_at' => 'datetime',
            'maturity_reminder_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InvestmentPlan::class, 'investment_plan_id');
    }

    public function performanceEntries(): HasMany
    {
        return $this->hasMany(PerformanceEntry::class)->orderBy('recorded_at');
    }

    public function capitalPayouts(): HasMany
    {
        return $this->hasMany(CapitalPayout::class);
    }

    public function hasCertificate(): bool
    {
        return $this->certificate_number !== null
            && in_array($this->status, [InvestmentStatus::Active, InvestmentStatus::Matured, InvestmentStatus::PaidOut], true);
    }

    /** Share of the term elapsed, 0-100, for progress bars. */
    public function progressPercent(): int
    {
        if (! $this->start_date || ! $this->maturity_date) {
            return 0;
        }
        $total = max(1, $this->start_date->diffInDays($this->maturity_date));
        $elapsed = max(0, $this->start_date->diffInDays(now()->startOfDay(), false));

        return (int) min(100, round($elapsed / $total * 100));
    }

    public function daysToMaturity(): ?int
    {
        return $this->maturity_date ? max(0, (int) now()->startOfDay()->diffInDays($this->maturity_date, false)) : null;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}

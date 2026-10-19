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
    'profit_frequency', 'terms', 'policy_number', 'terms_accepted_at', 'terms_accepted_ip',
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
            'terms' => 'array',
            'terms_accepted_at' => 'datetime',
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

    public function policyDocuments(): HasMany
    {
        return $this->hasMany(PolicyDocument::class)->orderByDesc('version');
    }

    /** The agreement currently in force for this policy, if one has been issued. */
    public function currentPolicyDocument(): ?PolicyDocument
    {
        return $this->policyDocuments()->where('status', PolicyDocument::ISSUED)->first();
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class)->orderByDesc('id');
    }

    /** A notice of early withdrawal that is still running, if there is one. */
    public function openWithdrawal(): ?WithdrawalRequest
    {
        return $this->withdrawalRequests()->where('status', WithdrawalRequest::REQUESTED)->first();
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class)->orderByDesc('id');
    }

    /** The receipt currently in force (latest issued version), or null. */
    public function currentReceipt(): ?Receipt
    {
        return $this->receipts()->where('status', Receipt::ISSUED)->first();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}

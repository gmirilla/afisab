<?php

namespace App\Models;

use App\Enums\KycStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'phone', 'date_of_birth', 'address', 'nin', 'photo_path', 'bank_name', 'account_number',
    'account_name', 'id_document_path', 'kyc_status', 'kyc_note', 'kyc_reviewed_by', 'kyc_reviewed_at',
    'title', 'occupation', 'occupation_address', 'nominee_name', 'nominee_relationship', 'nominee_phone',
])]
#[Hidden(['nin', 'nin_hash'])]
class InvestorProfile extends Model
{
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'nin' => 'encrypted',
            'kyc_status' => KycStatus::class,
            'kyc_reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kyc_reviewed_by');
    }

    protected static function booted(): void
    {
        // The hash follows the NIN automatically, so the two can never disagree.
        static::saving(function (self $profile) {
            if ($profile->isDirty('nin')) {
                $profile->nin_hash = $profile->nin ? self::hashNin((string) $profile->nin) : null;
            }
        });
    }

    /** Keyed one-way hash of a NIN (digits only): lets us detect a repeat without storing it in the clear. */
    public static function hashNin(string $nin): string
    {
        return hash_hmac('sha256', preg_replace('/\D/', '', $nin), (string) config('app.key'));
    }

    /** Last four digits only, for staff who do not review KYC. */
    public function maskedNin(): string
    {
        $nin = (string) $this->nin;

        return $nin === '' ? '' : str_repeat('•', max(0, strlen($nin) - 4)).substr($nin, -4);
    }

    public function isApproved(): bool
    {
        return $this->kyc_status === KycStatus::Approved;
    }
}

<?php

namespace App\Models;

use App\Enums\KycStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'phone', 'date_of_birth', 'address', 'bvn', 'bank_name', 'account_number',
    'account_name', 'id_document_path', 'kyc_status', 'kyc_note', 'kyc_reviewed_by', 'kyc_reviewed_at',
    'occupation', 'occupation_address', 'nominee_name', 'nominee_relationship', 'nominee_phone',
])]
#[Hidden(['bvn'])]
class InvestorProfile extends Model
{
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'bvn' => 'encrypted',
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

    /** Last four digits only, for staff who do not review KYC. */
    public function maskedBvn(): string
    {
        $bvn = (string) $this->bvn;

        return $bvn === '' ? '' : str_repeat('•', max(0, strlen($bvn) - 4)).substr($bvn, -4);
    }

    public function isApproved(): bool
    {
        return $this->kyc_status === KycStatus::Approved;
    }
}

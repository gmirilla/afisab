<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One commodity (and how much) an enquirer asked about or offered. */
#[Fillable(['enquiry_id', 'commodity', 'quantity', 'position'])]
class EnquiryItem extends Model
{
    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TillCashMovement extends Model
{
    use HasFactory;

    protected $fillable = ['till_session_id', 'type', 'amount', 'reason', 'user_id'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function tillSession(): BelongsTo
    {
        return $this->belongsTo(TillSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

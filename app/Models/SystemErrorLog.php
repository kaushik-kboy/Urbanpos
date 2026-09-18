<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemErrorLog extends Model
{
    protected $fillable = [
        'module',
        'error_type',
        'message',
        'file',
        'line',
        'url',
        'method',
        'user_id',
        'user_name',
        'branch_id',
        'request_data',
        'stack_trace',
        'ip_address',
        'user_agent',
        'status',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'request_data' => 'array',
        'resolved_at'  => 'datetime',
        'line'         => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope for filtering by module
     */
    public function scopeForModule($query, ?string $module)
    {
        if (!empty($module) && $module !== 'all') {
            return $query->where('module', $module);
        }
        return $query;
    }

    /**
     * Scope for filtering by status
     */
    public function scopeWithStatus($query, ?string $status)
    {
        if (!empty($status) && $status !== 'all') {
            return $query->where('status', $status);
        }
        return $query;
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateBetween($query, ?string $from, ?string $to)
    {
        if (!empty($from)) {
            $query->whereDate('created_at', '>=', $from);
        }
        if (!empty($to)) {
            $query->whereDate('created_at', '<=', $to);
        }
        return $query;
    }
}

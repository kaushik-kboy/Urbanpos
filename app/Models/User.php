<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'pos_pin',
        'branch_id',
        'is_active',
        'time_in',
        'time_out',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Null means all-branch access (e.g. Owner); non-null restricts this user to one
     * branch — see App\Http\Middleware\EnsureBranchAccess.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'pos_pin',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Determine if user is currently within their scheduled shift timing.
     * Unrestricted if shift timings are not set or if user is an Administrator/Owner.
     */
    public function isWithinShift(): bool
    {
        if (empty($this->time_in) || empty($this->time_out)) {
            return true;
        }

        if ($this->hasRole(['Owner', 'Admin', 'Super Admin', 'Administrator'])) {
            return true;
        }

        $now = now()->format('H:i');
        $in = substr($this->time_in, 0, 5);
        $out = substr($this->time_out, 0, 5);

        if ($in <= $out) {
            // Standard daytime shift (e.g. 09:00 to 18:00)
            return $now >= $in && $now <= $out;
        }

        // Overnight shift crossing midnight (e.g. 21:00 to 06:00)
        return $now >= $in || $now <= $out;
    }
}

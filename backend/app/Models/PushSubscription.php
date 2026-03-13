<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'device_id',
        'platform',
        'token',
        'preferences',
        'quiet_hours_start',
        'quiet_hours_end',
        'timezone',
        'daily_reminder_time',
        'is_active',
        'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'is_active' => 'boolean',
            'last_notified_at' => 'datetime',
        ];
    }

    /**
     * Default notification preferences.
     */
    public static function defaultPreferences(): array
    {
        return [
            'verse_of_day' => true,
            'reading_plan' => true,
            'new_module' => true,
            'sync' => false,
        ];
    }

    /**
     * Check if a specific notification type is enabled.
     */
    public function isTypeEnabled(string $type): bool
    {
        $prefs = $this->preferences ?? self::defaultPreferences();

        return $prefs[$type] ?? false;
    }

    /**
     * Check if currently within quiet hours.
     */
    public function isInQuietHours(): bool
    {
        if (! $this->quiet_hours_start || ! $this->quiet_hours_end) {
            return false;
        }

        $now = now($this->timezone);
        $currentTime = $now->format('H:i');

        $start = $this->quiet_hours_start;
        $end = $this->quiet_hours_end;

        // Handle overnight quiet hours (e.g., 22:00 - 07:00)
        if ($start > $end) {
            return $currentTime >= $start || $currentTime < $end;
        }

        return $currentTime >= $start && $currentTime < $end;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }
}

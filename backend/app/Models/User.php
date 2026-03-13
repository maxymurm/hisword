<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
        'provider',
        'provider_id',
        'timezone',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
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
            'is_admin' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    // ── Relationships ──────────────────────────────

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function bookmarkFolders(): HasMany
    {
        return $this->hasMany(BookmarkFolder::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(Highlight::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function pins(): HasMany
    {
        return $this->hasMany(Pin::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(History::class);
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(UserPreference::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}

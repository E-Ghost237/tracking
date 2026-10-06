<?php

namespace App\Models;

use App\Casts\SecureEncrypted;
use App\Casts\SecureEncryptedJson;
use App\Models\Concerns\HasPublicId;
use App\Services\Notifications\NotificationService;
use App\Support\Permissions;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /**
     * In-memory defaults matching the column defaults, so new instances are complete.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'active', 'locale' => 'en', 'failed_logins' => 0];

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicId, Notifiable, SoftDeletes;

    /**
     * Only profile fields are mass assignable. Status, roles, lock state and 2FA
     * secrets are always set explicitly by services.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'locale',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'failed_logins',
        'locked_until',
        'last_login_ip',
    ];

    /**
     * @var array<string, bool>|null
     */
    private ?array $permissionCache = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => SecureEncrypted::class,
            'two_factor_recovery_codes' => SecureEncryptedJson::class,
            'two_factor_confirmed_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'notification_prefs' => 'array',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * @return HasMany<LoginAttempt, $this>
     */
    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->permissionCache === null) {
            $this->permissionCache = [];
            $this->loadMissing('roles.permissions');
            foreach ($this->roles as $role) {
                foreach ($role->permissions as $perm) {
                    $this->permissionCache[$perm->slug] = true;
                }
            }
        }

        return isset($this->permissionCache[$permission]);
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }

    public function isStaff(): bool
    {
        return $this->roles->contains('is_staff', true);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->trashed();
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    /**
     * Staff need two-factor authentication before they reach the back-office (FR-144).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isActive()
            && $this->hasPermission(Permissions::ADMIN_ACCESS)
            && $this->hasTwoFactorEnabled();
    }

    public function wantsNotification(string $event, string $channel = 'mail'): bool
    {
        $prefs = $this->notification_prefs ?? [];

        return (bool) ($prefs[$event][$channel] ?? true);
    }

    public function sendPasswordResetNotification($token): void
    {
        $locale = $this->preferredLocale();
        app(NotificationService::class)->send('account.password_reset', $this, [
            'reset_url' => route($locale.'.password.reset', ['token' => $token, 'email' => $this->email]),
            'minutes' => (string) config('auth.passwords.users.expire', 60),
        ]);
    }

    public function sendEmailVerificationNotification(): void
    {
        app(NotificationService::class)->send('account.verify', $this, [
            'verify_url' => URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
                'id' => $this->public_id,
                'hash' => sha1($this->getEmailForVerification()),
            ]),
        ]);
    }

    public function preferredLocale(): string
    {
        return in_array($this->locale, ['en', 'fr'], true) ? $this->locale : 'en';
    }
}

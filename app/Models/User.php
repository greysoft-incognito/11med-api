<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\Role;
use App\Models\Concerns\HasPublicId;
use App\Models\Scopes\NotDeletingUserScope;
use App\Notifications\SendCode;
use App\Traits\ModelCanExtend;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Toneflix\LaravelFileable\Traits\Fileable;
use Toneflix\LaravelPayPocket\Interfaces\WalletOperations;
use Toneflix\LaravelPayPocket\Traits\ManagesWallet;
use Valorin\Random\Random;

#[ScopedBy([NotDeletingUserScope::class])]
class User extends Authenticatable implements WalletOperations
{
    use Fileable;
    use HasApiTokens;
    use HasFactory;
    use HasPublicId;
    use HasRoles;
    use ManagesWallet;
    use ModelCanExtend;
    use Notifiable;
    use SoftDeletes;

    public const NOT_FOUND = 'USER_NOT_FOUND';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'firstname',
        'lastname',
        'address',
        'country',
        'state',
        'city',
        'email',
        'phone',
        'username',
        'password',
        'last_seen',
        'full_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // 'data',
        'password',
        'remember_token',
        'access_data',
        'email_verify_code',
        'phone_verify_code',
    ];

    /**
     * The model's attributes.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'data' => '{}',
        'access_data' => '{}',
    ];

    /**
     * The attributes to be appended
     *
     * @var array
     */
    protected $appends = [
        'user_data',
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
            'data' => AsCollection::class,
            'access_data' => AsCollection::class,
            'last_seen' => 'datetime',
            'deleting_at' => 'datetime',
            'last_attempt' => 'datetime',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
        ];
    }

    public function registerFileable()
    {
        $this->fileableLoader([
            'image' => 'avatar',
        ], 'default', true);
    }

    public static function registerEvents()
    {
        static::creating(function (User $model) {
            $model->firstname ??= str((string) $model->full_name)->before(' ')->toString();
            $model->username ??= $model->generateUsername($model->full_name ?? $model->firstname ?? 'user');
        });
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param  string|null  $field
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this
            ->when(
                is_numeric($value),
                fn($q) => $q->where('id', $value)->orWhere('username', $value),
                fn($q) => $q->where('username', $value)
            )
            ->firstOrFail();
    }

    /**
     * The `full_name` column; the kit's first and last name for accounts made before it.
     * PHP method names ignore case, so this one accessor serves `fullname` and `full_name`.
     */
    protected function fullname(): Attribute
    {
        return Attribute::make(
            get: fn(?string $value) => $value ?? collect([$this->firstname, $this->lastname])
                ->filter()
                ->join(' '),
        );
    }

    public function hasVerifiedPhone()
    {
        return $this->phone_verified_at !== null;
    }

    /**
     * Send the email verification message
     */
    public function sendEmailVerificationNotification()
    {
        $this->last_attempt = now();
        $this->email_verify_code = Random::otp(6);
        $this->save();

        $this->notify(new SendCode($this->email_verify_code, 'verify'));
    }

    /**
     * Send the phone verification message
     */
    public function sendPhoneVerificationNotification()
    {
        $this->last_attempt = now();
        $this->phone_verify_code = Random::otp(6);
        $this->save();

        $this->notify(new SendCode($this->phone_verify_code, 'verify-phone'));
    }

    public function markEmailAsVerified()
    {
        $this->last_attempt = null;
        $this->email_verify_code = null;
        $this->email_verified_at = now();
        $this->save();

        if ($this->wasChanged('email_verified_at')) {
            return true;
        }

        return false;
    }

    public function markPhoneAsVerified()
    {
        $this->last_attempt = null;
        $this->phone_verify_code = null;
        $this->phone_verified_at = now();
        $this->save();

        if ($this->wasChanged('phone_verified_at')) {
            return true;
        }

        return false;
    }

    /**
     * The user's one role.
     */
    public function role(): ?Role
    {
        $name = $this->relationLoaded('roles')
            ? $this->roles->first()?->name
            : $this->getRoleNames()->first();

        return $name === null ? null : Role::tryFrom($name);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(ConsentRecord::class);
    }

    /**
     * Get all of the transactions for the User
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the user's fullname.
     *
     * @return string
     */
    protected function userData(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->data,
            set: fn($value) => is_array($value)
                ? json_encode($value, JSON_FORCE_OBJECT)
                : $value,
        );
    }

    /**
     * Get temporary User
     */
    public function temp(): BelongsTo
    {
        return $this->belongsTo(TempUser::class, 'email', 'email');
    }
}

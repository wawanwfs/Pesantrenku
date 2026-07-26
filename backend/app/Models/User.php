<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'username', 'phone', 'avatar', 'status', 'address', 'profile', 'password', 'mfa_secret', 'mfa_enabled', 'authz_version'])]
#[Hidden(['password', 'remember_token', 'mfa_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public function roles()
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function pesantrens()
    {
        return $this->belongsToMany(Pesantren::class, 'pesantren_user')
            ->withPivot(['role_id', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        return $this->roles->pluck('name')->intersect($roles)->isNotEmpty();
    }

    public function belongsToPesantren(int $pesantrenId, string|array|null $roles = null): bool
    {
        $query = $this->pesantrens()->where('pesantrens.id', $pesantrenId)->wherePivot('status', 'active');

        if ($roles !== null) {
            $roles = is_array($roles) ? $roles : [$roles];
            $query->wherePivotIn('role_id', Role::query()->whereIn('name', $roles)->pluck('id'));
        }

        return $query->exists();
    }

    public function getRoleNamesAttribute(): array
    {
        return $this->roles->pluck('name')->values()->all();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'profile' => 'array',
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_enabled' => 'boolean',
            'authz_version' => 'integer',
        ];
    }
}

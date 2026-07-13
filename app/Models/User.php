<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * The user's import attempts (checkpoint records). ProjectImporter creates
     * them through this relation because Import.user_id is deliberately not
     * mass-assignable — same ownership rule as projects().
     */
    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    protected static function booted(): void
    {
        // Eloquent-delete the user's projects so Project's `deleting` hook fires for
        // each and purges its codex media files. Breeze's account deletion deletes the
        // user directly; the users → projects FK cascade is DB-level and would skip the
        // Project hook, leaking files. Deleting through Eloquent keeps purgeProject the
        // single purge trigger (media-lifecycle.md, binding decision Q6).
        static::deleting(function (User $user) {
            $user->projects->each->delete();
        });
    }
}

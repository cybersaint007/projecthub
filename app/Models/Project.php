<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_SHARED = 'shared';
    public const VISIBILITY_PUBLIC = 'public';

    public const ROLE_OWNER = 'owner';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_VIEWER = 'viewer';

    protected $fillable = ['name', 'description', 'code', 'owner_id', 'visibility'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Users with access via pivot (role: owner/editor/viewer).
     */
    public function accessUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @deprecated Use accessUsers() for new code. Kept for backward compatibility. */
    public function users(): BelongsToMany
    {
        return $this->accessUsers();
    }

    public function epics(): HasMany
    {
        return $this->hasMany(Epic::class)->orderBy('position')->orderBy('id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    /**
     * Current user's role on this project: owner, editor, viewer, or null if no access.
     */
    public function roleFor(?User $user): ?string
    {
        if (!$user) {
            return null;
        }
        if ($this->owner_id && (int) $this->owner_id === (int) $user->id) {
            return self::ROLE_OWNER;
        }
        $pivot = $this->accessUsers->firstWhere('id', $user->id);
        if ($pivot && isset($pivot->pivot->role)) {
            return $pivot->pivot->role;
        }
        return null;
    }

    /**
     * Scope: projects the user is allowed to see (admin sees all; others by visibility/ownership/access).
     */
    public function scopeAccessibleTo(Builder $query, ?User $user): Builder
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->isAdmin()) {
            return $query;
        }
        return $query->where(function (Builder $q) use ($user) {
            $q->where('visibility', self::VISIBILITY_PUBLIC)
                ->orWhere('owner_id', $user->id)
                ->orWhereHas('accessUsers', function (Builder $sub) use ($user) {
                    $sub->where('users.id', $user->id);
                });
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One row in the admin audit log: who did what to which record, from where.
 *
 * @property string $action
 * @property string $description
 * @property bool $succeeded
 * @property array<string, mixed>|null $changes
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'succeeded' => 'boolean',
            'changes' => 'array',
        ];
    }

    /**
     * Record an event for the signed-in user (or $actor, e.g. on a failed sign-in).
     *
     * @param  array<string, mixed>|null  $changes
     */
    public static function record(
        string $action,
        string $description,
        ?Model $subject = null,
        ?string $subjectLabel = null,
        ?array $changes = null,
        bool $succeeded = true,
        ?User $actor = null,
        ?string $actorName = null,
    ): self {
        // An explicit $actorName (e.g. the email typed on a failed sign-in) means
        // "this isn't the signed-in user", so don't fall back to the session.
        if ($actor === null && $actorName === null) {
            $actor = auth()->user();
        }
        $request = request();

        return static::create([
            'user_id' => $actor?->id,
            'actor_name' => $actor?->name ?? $actorName,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel,
            'description' => $description,
            'succeeded' => $succeeded,
            'changes' => $changes ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 250, ''),
        ]);
    }

    /**
     * Before/after values for the attributes that changed on a model being saved.
     * Call before save(); hidden attributes like passwords are never included.
     *
     * @param  list<string>  $only
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function diff(Model $model, array $only): array
    {
        return collect($model->getDirty())
            ->only($only)
            ->except($model->getHidden())
            ->mapWithKeys(fn ($value, $key) => [$key => ['from' => $model->getOriginal($key), 'to' => $value]])
            ->all();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "role.updated" => "Role updated" */
    public function actionLabel(): string
    {
        return Str::of($this->action)->replace(['.', '_'], ' ')->ucfirst()->value();
    }

    public function module(): string
    {
        return match (Str::before($this->action, '.')) {
            'auth' => 'Sign-in',
            'user' => 'Users',
            'role' => 'Roles',
            'permission' => 'Permissions',
            default => Str::headline(Str::before($this->action, '.')),
        };
    }
}

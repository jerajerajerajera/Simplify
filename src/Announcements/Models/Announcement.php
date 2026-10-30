<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Simplify\Core\Announcements\Services\Bell;
use Simplify\Core\Announcements\Support\Rules;

/**
 * @property int $id
 * @property string $category info, warning or success
 * @property string $title
 * @property ?string $body
 * @property ?CarbonInterface $starts_at empty: right away
 * @property ?CarbonInterface $ends_at empty: until it is ended
 * @property string $audience everyone or admins
 * @property ?string $button_label
 * @property ?string $button_url https://… or /path on this panel
 * @property bool $in_bell also in everyone's notifications
 * @property ?CarbonInterface $in_bell_at when it was put there
 * @property bool $push also as app notification through Simplify
 * @property int $pushed_to devices reached, added up
 * @property bool $in_game also posted in the chat of game servers
 * @property ?list<int> $in_game_servers with the groups empty: all servers
 * @property ?list<string> $in_game_groups ids of the author's server groups, read when it is sent
 * @property int $in_game_repeat minutes between posts, 0: once
 * @property ?CarbonInterface $in_game_at last post
 * @property ?array{sent: int, offline: int, unsupported: int, failed: int} $in_game_result
 * @property ?int $created_by
 */
final class Announcement extends Model
{
    protected $table = 'simplify_core_announcements';

    protected $fillable = ['category', 'title', 'body', 'starts_at', 'ends_at', 'audience', 'button_label', 'button_url', 'in_bell', 'push', 'in_game', 'in_game_servers', 'in_game_groups', 'in_game_repeat', 'created_by'];

    protected $attributes = ['category' => 'info', 'audience' => 'everyone'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'in_bell' => 'boolean',
            'in_bell_at' => 'datetime',
            'push' => 'boolean',
            'pushed_to' => 'integer',
            'in_game' => 'boolean',
            'in_game_servers' => 'array',
            'in_game_groups' => 'array',
            'in_game_repeat' => 'integer',
            'in_game_at' => 'datetime',
            'in_game_result' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // An ended or deleted announcement leaves no item behind in anyone's bell.
        self::deleted(fn (self $announcement) => Bell::forget($announcement->id));
        self::updated(function (self $announcement): void {
            $ended = $announcement->state() === 'ended';
            if ($ended || ($announcement->wasChanged('in_bell') && ! $announcement->in_bell)) {
                // Put there again when it is shown again, or turned on again.
                Bell::forget($announcement->id, kind: $ended ? null : Bell::EVERYONE);
                $announcement->forceFill(['in_bell_at' => null])->saveQuietly();
            }
            // A new start, or just turned on: it goes out in game again.
            if ($announcement->wasChanged(['starts_at', 'in_game'])) {
                $announcement->forceFill(['in_game_at' => null, 'in_game_result' => null])->saveQuietly();
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The accounts that unpinned it. */
    public function dismissedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'simplify_core_announcement_dismissals', 'announcement_id', 'user_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeLive(Builder $query): void
    {
        $now = now();
        $query->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }

    /** The live announcements this account sees and has not unpinned, newest first. */
    public static function barsFor(User $user): Builder
    {
        return self::query()
            ->live()
            ->when(! $user->isAdmin(), fn (Builder $q) => $q->where('audience', 'everyone'))
            ->whereDoesntHave('dismissedBy', fn (Builder $q) => $q->whereKey($user->getKey()))
            ->latest();
    }

    public function state(): string
    {
        return Rules::state($this->starts_at?->getTimestamp(), $this->ends_at?->getTimestamp(), time());
    }

    public function isLive(): bool
    {
        return $this->state() === 'live';
    }
}

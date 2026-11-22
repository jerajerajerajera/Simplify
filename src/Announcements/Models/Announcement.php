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
use Simplify\Core\Announcements\Support\StartTrigger;
use Simplify\Core\Announcements\Support\Weekly;

/**
 * @property int $id
 * @property string $category info, warning or success
 * @property string $title
 * @property ?string $body
 * @property ?CarbonInterface $starts_at empty: right away
 * @property ?CarbonInterface $ends_at empty: until it is ended
 * @property string $audience everyone, admins, or server: the owner and the people with access to the server its moment is about
 * @property ?string $button_label
 * @property ?string $button_url https://… or /path on this panel
 * @property bool $in_bell also in everyone's notifications
 * @property ?CarbonInterface $in_bell_at when it was put there
 * @property bool $bell_pinned on top of everyone's notifications until it ends
 * @property bool $push also as app notification through Simplify
 * @property int $pushed_to devices reached by its latest sending: at a time, this week's turn or the latest start
 * @property bool $in_game also posted in the chat of game servers
 * @property ?list<int> $in_game_servers with the groups empty: all servers
 * @property ?list<string> $in_game_groups ids of the author's server groups, read when it is sent
 * @property int $in_game_repeat minutes between posts, 0: once
 * @property ?CarbonInterface $in_game_at last post
 * @property ?array{sent: int, offline: int, unsupported: int, failed: int} $in_game_result
 * @property string $send_on time (at starts_at), start (a moment of one of start_servers) or weekly (on weekly_days at weekly_times)
 * @property bool $bar shown as a bar at the top of every page, each time it goes out
 * @property ?string $stay how long the bar stays: for (stay_minutes), until (ends_at, one at a time only) or ended (until it is ended)
 * @property int $stay_minutes
 * @property string $start_event start, stop or crash
 * @property ?array<int, int> $start_pending server id => when its stop was seen: it waits to be called a crash
 * @property ?int $shown_server the server the bar that shows now is about
 * @property ?string $in_game_target a server moment: started (the chat of that server) or servers (in_game_servers)
 * @property ?list<string> $weekly_times HH:MM in weekly_zone; empty: weekly_at
 * @property ?list<int> $start_servers
 * @property bool $start_once goes out one more time: the next moment, or the next turn of the week
 * @property ?array<int, bool|int> $start_seen server id => at the last look: not running (false), or since when it runs
 * @property bool $start_daily "Once per day": only the first start of each chosen day per server
 * @property ?array<int, string> $start_sent server id => the day ("YYYY-MM-DD" in weekly_zone) its start last went out
 * @property ?string $start_from HH:MM in weekly_zone: starts from then count
 * @property ?string $start_until HH:MM in weekly_zone: starts until then count
 * @property ?list<int> $weekly_days ISO days, 1 Monday … 7 Sunday
 * @property ?string $weekly_at HH:MM in weekly_zone
 * @property ?string $weekly_zone the author's time zone
 * @property int $weekly_hours before stay_minutes: how long the bar showed each time
 * @property ?CarbonInterface $weekly_from the turn, or the server moment, that went out last
 * @property ?CarbonInterface $weekly_until its bar shows until then
 * @property ?int $created_by
 * @property ?CarbonInterface $created_at
 * @property ?CarbonInterface $updated_at
 */
final class Announcement extends Model
{
    protected $table = 'simplify_core_announcements';

    protected $fillable = ['category', 'title', 'body', 'starts_at', 'ends_at', 'audience', 'button_label', 'button_url', 'in_bell', 'bell_pinned', 'push', 'in_game', 'in_game_servers', 'in_game_groups', 'in_game_repeat', 'send_on', 'start_servers', 'start_once', 'start_daily', 'start_from', 'start_until', 'weekly_days', 'weekly_at', 'weekly_zone', 'weekly_hours', 'bar', 'stay', 'stay_minutes', 'start_event', 'weekly_times', 'in_game_target', 'created_by'];

    protected $attributes = ['category' => 'info', 'audience' => 'everyone', 'send_on' => 'time', 'stay_minutes' => Rules::DEFAULT_STAY_MINUTES, 'start_event' => 'start'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'in_bell' => 'boolean',
            'in_bell_at' => 'datetime',
            'bell_pinned' => 'boolean',
            'push' => 'boolean',
            'pushed_to' => 'integer',
            'in_game' => 'boolean',
            'in_game_servers' => 'array',
            'in_game_groups' => 'array',
            'in_game_repeat' => 'integer',
            'in_game_at' => 'datetime',
            'in_game_result' => 'array',
            'start_servers' => 'array',
            'start_once' => 'boolean',
            'start_seen' => 'array',
            'start_daily' => 'boolean',
            'start_sent' => 'array',
            'weekly_days' => 'array',
            'weekly_hours' => 'integer',
            'bar' => 'boolean',
            'stay_minutes' => 'integer',
            'start_pending' => 'array',
            'shown_server' => 'integer',
            'weekly_times' => 'array',
            'weekly_from' => 'datetime',
            'weekly_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // An ended or deleted announcement leaves no item behind in anyone's bell. An announcement on a server start is
        // the exception while it exists: its bell items are messages of starts that happened, and one that goes out once
        // ends right after it went out (K-B68).
        self::deleted(fn (self $announcement) => Bell::forget($announcement->id));
        // One at a time: its bar stays for a while (its end is worked out here), or until it is ended. Only when one of
        // these changed, so "End now" on an announcement stays ended when its text is edited later.
        self::saving(function (self $announcement): void {
            // Made without saying where it shows: a server moment is a message without a bar, the others have a bar,
            // on week days for the hours it always had.
            if (! $announcement->exists) {
                if ($announcement->getAttribute('bar') === null) {
                    $announcement->bar = $announcement->send_on !== StartTrigger::START;
                }
                if ($announcement->stay === null && $announcement->send_on === Weekly::SEND_ON && $announcement->weekly_hours) {
                    $announcement->stay_minutes = Rules::minutes($announcement->weekly_hours * 60);
                }
                $announcement->stay ??= match (true) {
                    $announcement->send_on !== StartTrigger::TIME => 'for',
                    $announcement->ends_at !== null => 'until',
                    default => 'ended',
                };
            }
            if ($announcement->send_on !== StartTrigger::TIME || ! (! $announcement->exists || $announcement->isDirty(['send_on', 'bar', 'stay', 'stay_minutes', 'starts_at']))) {
                return;
            }
            if ($announcement->bar && $announcement->stay === 'for') {
                $announcement->ends_at = ($announcement->starts_at ?? now())->clone()->addMinutes(Rules::minutes($announcement->stay_minutes));
            } elseif (! $announcement->bar || ($announcement->stay === 'ended' && $announcement->isDirty(['send_on', 'bar', 'stay']) && $announcement->exists)) {
                $announcement->ends_at = null;
            }
        });
        self::updated(function (self $announcement): void {
            $ended = $announcement->state() === 'ended' && $announcement->send_on !== StartTrigger::START;
            if ($ended || ($announcement->wasChanged('in_bell') && ! $announcement->in_bell)) {
                // Put there again when it is shown again, or turned on again.
                Bell::forget($announcement->id, kind: $ended ? null : Bell::EVERYONE);
                $announcement->forceFill(['in_bell_at' => null])->saveQuietly();
            }
            // A new start, or just turned on: it goes out in game again.
            // Other servers, or switched to "when a server starts": the next look only remembers, it does not send.
            if ($announcement->wasChanged(['start_servers', 'send_on', 'start_event'])) {
                $announcement->forceFill(['start_seen' => null, 'start_sent' => null, 'start_pending' => null])->saveQuietly();
            }
            // Other days, times or length: the next run works out its turn again.
            if ($announcement->wasChanged(['send_on', 'weekly_days', 'weekly_at', 'weekly_times', 'weekly_zone', 'weekly_hours', 'stay', 'stay_minutes', 'start_servers', 'start_event', 'start_once'])) {
                $announcement->forceFill(['weekly_from' => null, 'weekly_until' => null, 'shown_server' => null])->saveQuietly();
            }
            // "Only the next time" that went out and is given a new end: it waits for a next time again.
            if ($announcement->start_once && $announcement->wasChanged('ends_at') && $announcement->state() !== 'ended' && $announcement->weekly_until?->isPast()) {
                $announcement->forceFill(['weekly_from' => null, 'weekly_until' => null, 'shown_server' => null])->saveQuietly();
            }
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

    /**
     * Sent at a time (and shown as a bar), not when a server starts. One on days of the week counts while the bar of
     * its last turn shows.
     *
     * @param  Builder<self>  $query
     */
    public function scopeTimed(Builder $query): void
    {
        $now = now();
        $query->where(fn (Builder $q) => $q->whereNull('send_on')
            ->orWhere('send_on', StartTrigger::TIME)
            ->orWhere(fn (Builder $q) => $q->where('send_on', Weekly::SEND_ON)->where('weekly_from', '<=', $now)->where('weekly_until', '>', $now)));
    }

    /**
     * Showing now: one at a time, the turn of one on week days, or the bar a server moment opened.
     *
     * @param  Builder<self>  $query
     */
    public function scopeShowing(Builder $query): void
    {
        $now = now();
        $query->where(fn (Builder $q) => $q->timed()
            ->orWhere(fn (Builder $q) => $q->where('send_on', StartTrigger::START)->where('bar', true)->where('weekly_from', '<=', $now)->where('weekly_until', '>', $now)));
    }

    /**
     * The bars this account sees and has not unpinned, newest first. One for "the people of the server" shows to
     * the owner of the server its moment was about and to the people with access to it.
     */
    public static function barsFor(User $user): Builder
    {
        $id = $user->getKey();

        return self::query()
            ->live()
            ->showing()
            ->where('bar', true)
            ->where(fn (Builder $q) => $q->where('audience', 'everyone')
                ->when($user->isAdmin(), fn (Builder $q) => $q->orWhere('audience', 'admins'))
                ->orWhere(fn (Builder $q) => $q->where('audience', Rules::SERVER_AUDIENCE)->where(fn (Builder $q) => $q
                    ->whereIn('shown_server', fn ($q) => $q->select('id')->from('servers')->where('owner_id', $id))
                    ->orWhereIn('shown_server', fn ($q) => $q->select('server_id')->from('subusers')->where('user_id', $id)))))
            ->whereDoesntHave('dismissedBy', fn (Builder $q) => $q->whereKey($id))
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

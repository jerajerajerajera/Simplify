<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Services;

use App\Filament\Admin\Pages\Settings as PelicanSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Simplify\Core\Announcements\Mail\AnnouncementMail;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Support\Diagnose;
use Simplify\Core\Support\Preferences;
use Throwable;

/**
 * Announcements by email, through the panel's own mail settings (Pelican's Settings › Mail). Each one is emailed once,
 * when it starts, to everyone who sees it and did not turn these emails off; a few each minute, so a big panel stays
 * under the mail server's limits. A repeating one is emailed the first time only: each account gets it once.
 */
final class Email
{
    /** Emails sent per announcement each minute. */
    public const BATCH = 50;

    /** Accounts that sign up or turn emails back on within a day after the start still get it. */
    public const CATCH_UP_SECONDS = 86400;

    /** The account's choice (simplify_preferences): false when they turned announcement emails off. */
    public const PREFERENCE = 'announcement_emails';

    private const SENT = 'simplify_core_announcement_mails';

    /** Mail drivers that never reach anyone: Laravel's defaults before mail is set up. */
    private const NOWHERE = ['log', 'array', ''];

    public static function available(): bool
    {
        return self::configured((string) config('mail.default'), (string) config('mail.from.address'));
    }

    /** Whether mail goes somewhere: a real mail driver and a sender address. */
    public static function configured(string $mailer, string $from): bool
    {
        return ! in_array(strtolower(trim($mailer)), self::NOWHERE, true) && filter_var(trim($from), FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Pelican's own mail settings, where an admin sets up the mail server. */
    public static function settingsUrl(): ?string
    {
        try {
            return PelicanSettings::getUrl(['tab' => 'mail::data::tab'], panel: 'admin');
        } catch (Throwable) {
            return null;
        }
    }

    public static function wanted(User $user): bool
    {
        return Preferences::get($user, self::PREFERENCE) !== false;
    }

    public static function want(User $user, bool $wanted): void
    {
        Preferences::set($user, self::PREFERENCE, $wanted);
    }

    /** The link in each email that turns them off (and back on), without signing in. */
    public static function unsubscribeUrl(User $user): string
    {
        return url(URL::signedRoute('simplify.announcement-emails', ['user' => $user->id], absolute: false));
    }

    /**
     * The next emails of each announcement that started within the last day. Returns the emails sent this time.
     */
    public static function sendDue(): int
    {
        if (! self::available()) {
            return 0;
        }

        $sent = 0;
        $since = now()->subSeconds(self::CATCH_UP_SECONDS);
        $due = Announcement::query()
            ->live()
            ->timed()
            ->where('email', true)
            ->where(fn ($q) => $q->where('starts_at', '>=', $since)
                ->orWhere(fn ($q) => $q->whereNull('starts_at')->where('created_at', '>=', $since))
                ->orWhere('weekly_from', '>=', $since))
            ->get();
        foreach ($due as $announcement) {
            $sent += self::sendSome($announcement);
        }

        return $sent;
    }

    private static function sendSome(Announcement $announcement): int
    {
        $done = DB::table(self::SENT)->where('announcement_id', $announcement->id)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $users = Bell::audience($announcement)->get()
            ->filter(fn (User $user) => ($announcement->audience !== 'admins' || $user->isAdmin())
                && filter_var((string) $user->email, FILTER_VALIDATE_EMAIL) !== false && self::wanted($user));
        $todo = $users->reject(fn (User $user) => in_array((int) $user->id, $done, true))->values();
        $total = count($done) + $todo->count();
        $sent = 0;
        $failed = false;
        foreach ($todo->take(self::BATCH) as $user) {
            try {
                Mail::to((string) $user->email)->locale($user->language ?: config('app.locale'))->send(new AnnouncementMail($announcement, $user));
            } catch (Throwable $error) {
                // The mail server refused: the rest waits for the next minute, and the list says so.
                Diagnose::trace('email #'.$announcement->id.' failed: '.class_basename($error));
                report($error);
                $failed = true;
                break;
            }
            // Marked after it went: a crash halfway sends that one again, never skips it.
            DB::table(self::SENT)->insertOrIgnore(['announcement_id' => $announcement->id, 'user_id' => $user->id, 'created_at' => now()]);
            $sent++;
        }
        $announcement->forceFill(['email_total' => $total, 'email_sent' => count($done) + $sent, 'email_failed' => $failed])->saveQuietly();
        if ($sent > 0) {
            Diagnose::trace('email #'.$announcement->id.' sent to '.$sent.' accounts');
        }

        return $sent;
    }

    /** How far the emails got, for the list: null when it is not emailed. */
    public static function progress(bool $email, int $sent, int $total, bool $failed): ?string
    {
        return match (true) {
            ! $email => null,
            $failed => 'failed',
            $total === 0 && $sent === 0 => 'waiting',
            $sent < $total => 'sending',
            default => 'sent',
        };
    }
}

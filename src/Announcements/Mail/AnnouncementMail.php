<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Services\Email;
use Simplify\Core\WebApp\WebApp;

/** One announcement as an email: the panel's name, the title, the message and its button, and a link to stop them. */
final class AnnouncementMail extends Mailable
{
    public function __construct(public Announcement $announcement, public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->announcement->title);
    }

    public function headers(): Headers
    {
        // One click in the mail app turns them off too (RFC 8058).
        return new Headers(text: [
            'List-Unsubscribe' => '<'.Email::unsubscribeUrl($this->user).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $url = (string) $this->announcement->button_url;

        return new Content(view: 'simplify::announcements.mail', text: 'simplify::announcements.mail-text', with: [
            'panel' => WebApp::name(),
            'icon' => WebApp::icon(192)[0],
            'title' => $this->announcement->title,
            'body' => (string) $this->announcement->body,
            'button' => $url === '' ? url('/') : (str_starts_with($url, '/') ? url($url) : $url),
            'label' => $url !== '' && trim((string) $this->announcement->button_label) !== '' ? (string) $this->announcement->button_label : __('simplify::announcements.mail.open'),
            'host' => (string) parse_url(url('/'), PHP_URL_HOST),
            'stop' => Email::unsubscribeUrl($this->user),
        ]);
    }
}

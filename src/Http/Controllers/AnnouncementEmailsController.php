<?php

declare(strict_types=1);

namespace Simplify\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Simplify\Core\Announcements\Services\Email;
use Simplify\Core\WebApp\WebApp;

/**
 * The link at the bottom of each announcement email: turns them off, or back on, without signing in. The signed link
 * is the account's own; opening it only shows a button, so a mail scanner that follows links changes nothing, and a
 * mail app's one-click unsubscribe (a POST without "on") turns them off.
 */
final class AnnouncementEmailsController
{
    public function __invoke(Request $request, int $user): Response
    {
        $account = User::query()->find($user);
        abort_if($account === null, 404);
        // In the language of their account, like the emails.
        if (is_string($account->language) && $account->language !== '') {
            app()->setLocale($account->language);
        }
        if ($request->isMethod('POST')) {
            Email::want($account, $request->input('on') === '1');
        }
        $wanted = Email::wanted($account);
        $t = 'simplify::announcements.mail.';
        $host = (string) parse_url(url('/'), PHP_URL_HOST);
        $html = '<!doctype html><html lang="'.e(str_replace('_', '-', app()->getLocale())).'"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
            .'<title>'.e(__($t.'page_title')).'</title><style>'
            .'body{margin:0;padding:48px 16px;background:#f4f4f5;color:#18181b;font:15px/1.5 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}'
            .'main{max-width:440px;margin:0 auto;background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:24px}'
            .'h1{margin:0 0 8px;font-size:19px}p{margin:0 0 18px;color:#52525b}'
            .'button{font:inherit;font-weight:600;border:1px solid #e4e4e7;background:#fff;color:#18181b;border-radius:8px;padding:8px 14px;cursor:pointer}'
            .'@media (prefers-color-scheme:dark){body{background:#09090b;color:#fafafa}main{background:#18181b;border-color:#27272a}p{color:#a1a1aa}button{background:#27272a;border-color:#3f3f46;color:#fafafa}}'
            .'</style></head><body><main>'
            .'<h1>'.e(WebApp::name()).'</h1>'
            .'<p>'.e(__($t.($wanted ? 'page_on' : 'page_off'), ['panel' => $host])).'</p>'
            .'<form method="post" action="'.e($request->fullUrl()).'">'
            .($wanted ? '' : '<input type="hidden" name="on" value="1">')
            .'<button type="submit">'.e(__($t.($wanted ? 'stop' : 'turn_on'))).'</button></form>'
            .'</main></body></html>';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}

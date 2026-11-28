<?php

declare(strict_types=1);

namespace Simplify\Core\Embed;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Simplify\Core\Embed\Services\EggLogo;
use Simplify\Core\Embed\Services\EmbedDetails;
use Simplify\Core\Embed\Services\EmbedOptions;
use Simplify\Core\Embed\Services\Incidents;
use Simplify\Core\Embed\Services\ServerList;
use Simplify\Core\Embed\Services\Snippet;
use Simplify\Core\Embed\Services\StatusAddress;
use Simplify\Core\Embed\Services\StatusCss;
use Simplify\Core\SimplifyPlugin;
use Simplify\Core\Support\Brand;
use Simplify\Core\Support\PanelSettings;
use Simplify\Core\WebApp\WebApp;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * For other websites: the server list as JSON, the script that draws it and a small page for an iframe.
 * Public and read-only, so every response allows any origin and nothing depends on a session.
 */
final class EmbedController
{
    private const HEADERS = ['Access-Control-Allow-Origin' => '*', 'X-Content-Type-Options' => 'nosniff'];

    public function servers(): Response
    {
        $json = json_encode(ServerList::payload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return response($json, 200, [
            ...self::HEADERS,
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age='.ServerList::TTL,
        ]);
    }

    /**
     * An uploaded image of a server that is on the website (its Simplify banner, or the image picked on its Website page), so other websites can show it. Any other banner (of a
     * server that is not on the website, or no longer in use) answers 404, like a file that does not exist.
     */
    public function banner(string $file): BinaryFileResponse
    {
        $path = EmbedDetails::publicPath($file);
        abort_if($path === null, 404);

        // Every upload gets a new random name, so a name never changes content.
        return response()->file($path, [...self::HEADERS, 'Cache-Control' => 'public, max-age=86400']);
    }

    /** A logo uploaded for an egg or for the status page in Settings › Website; any other file answers 404. */
    public function logo(string $file): BinaryFileResponse
    {
        $path = EggLogo::publicPath($file);
        abort_if($path === null, 404);

        return response()->file($path, [...self::HEADERS, 'Cache-Control' => 'public, max-age=86400']);
    }

    public function script(Request $request): Response
    {
        $path = plugin_path(SimplifyPlugin::ID, 'resources', 'js', 'embed.js');
        $etag = '"'.substr(hash_file('sha256', $path), 0, 16).'"';
        $headers = [
            ...self::HEADERS,
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
            'ETag' => $etag,
        ];
        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304, $headers);
        }

        return response((string) file_get_contents($path), 200, $headers);
    }

    /** The incidents as an RSS feed, next to the status page; 404 while the feed is off. */
    public function feed(): Response
    {
        abort_unless(EmbedOptions::all()['feed'], 404);

        return response(Incidents::feed(), 200, [
            ...self::HEADERS,
            'Content-Type' => 'application/rss+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age='.ServerList::TTL,
        ]);
    }

    /** /status on the panel's own address: the list as a page of its own, always on, the same as frame?page=1. */
    public function status(Request $request): Response
    {
        $request->query->set('page', '1');

        return $this->frame($request);
    }

    /**
     * A page that only holds the list, for websites that prefer an iframe. The query takes the same options as the
     * script's data-* attributes. With ?page=1 it is a page of its own (the status page): some room, a background and
     * the look from Settings › Website › Status page (title, logo, intro, theme, accent, own CSS).
     */
    public function frame(Request $request): Response
    {
        $page = $request->query('page') === '1';
        $options = $page ? EmbedOptions::all() : null;
        $rules = ['server' => Snippet::ID, 'game' => Snippet::GAME, 'servers' => Snippet::IDS];
        $data = [];
        foreach (['style', 'accent', 'corners', 'players', 'modpack', 'join', 'uptime', 'server', 'game', 'servers'] as $key) {
            $value = $request->query($key);
            if (is_string($value) && preg_match($rules[$key] ?? '/^[#A-Za-z0-9_-]{1,40}$/', $value)) {
                $data[$key] = $value;
            }
        }
        // The status page's own theme and accent, unless the address asks for another.
        if ($options !== null) {
            $data['style'] ??= $options['page_style'] !== '' ? $options['page_style'] : $options['style'];
            if ($options['page_accent'] !== '') {
                $data['accent'] ??= $options['page_accent'];
            }
        }
        $theme = (string) ($data['style'] ?? '');
        // The page draws the banner and the incident log too.
        if ($page) {
            $data['page'] = '1';
        }
        $attributes = '';
        foreach ($data as $key => $value) {
            $attributes .= ' data-'.$key.'="'.e($value).'"';
        }
        $dark = 'html{background:#0b0b0e}';
        $style = match (true) {
            ! $page => 'html,body{background:transparent}body{padding:2px}',
            $theme === 'dark' => $dark,
            $theme === 'light' => 'html{background:#fafafa}',
            default => 'html{background:#fafafa}@media (prefers-color-scheme:dark){'.$dark.'}',
        };
        $name = $options !== null && $options['page_title'] !== '' ? $options['page_title'] : __('simplify::embed-list.title', ['name' => WebApp::name()]);
        $title = e($name);
        $heading = '';
        $own = '';
        $alternate = '';
        $headers = ['Content-Security-Policy' => 'frame-ancestors *'];
        if ($options !== null) {
            $style .= 'body{padding:24px 16px;max-width:1100px;margin:0 auto;font:15px/1.5 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#18181b}'
                .'.page-head{display:flex;align-items:center;gap:12px;margin:0 0 16px}.page-head:has(+.page-intro){margin-bottom:8px}'
                .'.page-logo{height:40px;width:auto;max-width:160px;object-fit:contain;flex:none}'
                .'.page-feed{margin-left:auto;flex:none;font-size:12px;font-weight:600;color:inherit;opacity:.7;text-decoration:none;border:1px solid currentColor;border-radius:7px;padding:2px 8px}.page-feed:hover{opacity:1}'
                .'h1{margin:0;font:600 22px/1.3 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#18181b;overflow-wrap:anywhere}'
                .'.page-intro{margin:0 0 18px;max-width:70ch;color:#52525b;white-space:pre-line;overflow-wrap:anywhere}';
            $night = 'body,h1{color:#f4f4f5}.page-intro{color:#a1a1aa}';
            $style .= match ($theme) {
                'dark' => $night,
                'light' => '',
                default => '@media (prefers-color-scheme:dark){'.$night.'}',
            };
            $logo = self::pageLogo($options);
            // A page of its own (and the status address) names what it lists.
            $feed = $options['feed'] ? StatusAddress::pageUrl('feed.xml') : null;
            $alternate = $feed === null ? '' : '<link rel="alternate" type="application/rss+xml" title="'.$title.'" href="'.e($feed).'">';
            $heading = '<header class="page-head">'.($logo === null ? '' : '<img class="page-logo" src="'.e($logo).'" alt="">').'<h1>'.$title.'</h1>'
                .($feed === null ? '' : '<a class="page-feed" href="'.e($feed).'" title="'.e(__('simplify::embed-list.feed')).'">RSS</a>').'</header>'
                .($options['page_intro'] === '' ? '' : '<p class="page-intro">'.e($options['page_intro']).'</p>');
            // The admin's own CSS, cleaned on save and again here; the policy below lets nothing load from elsewhere
            // or run except the list's own script.
            $own = StatusCss::clean($options['page_css'])['css'];
            $panel = rtrim(url('/'), '/');
            $headers['Content-Security-Policy'] = "frame-ancestors *; default-src 'none'; script-src 'self' $panel; connect-src 'self' $panel; "
                ."style-src 'self' $panel 'unsafe-inline'; img-src * data:; font-src 'self' $panel data:; base-uri 'none'; form-action 'none'";
        }
        $html = '<!doctype html><html lang="'.e(str_replace('_', '-', app()->getLocale())).'"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.$title.'</title>'
            .(($favicon = WebApp::favicon()) === null ? '' : '<link rel="icon" href="'.e($favicon).'">')
            .$alternate
            .'<style>html,body{margin:0}'.$style.'</style>'
            .($own === '' ? '' : '<style>'.$own.'</style>')
            .'</head>'
            .'<body>'.$heading.'<div id="simplify-servers"></div><script src="'.e(url('/simplify/embed.js')).'"'.$attributes.' defer></script></body></html>';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
            ...$headers,
            'X-Content-Type-Options' => 'nosniff',
            // The settings check for this, to know an address of its own reaches this panel's status page.
            StatusAddress::HEADER => '1',
        ]);
    }

    /**
     * The status page's logo: the panel's logo from Settings › Branding, the one uploaded for the page, or none.
     *
     * @param  array{page_logo: string, page_logo_file: string}  $options
     */
    private static function pageLogo(array $options): ?string
    {
        if ($options['page_logo'] === 'upload') {
            return $options['page_logo_file'] === '' ? null : url('/simplify/embed/logos/'.basename($options['page_logo_file']));
        }
        if ($options['page_logo'] === 'panel') {
            $saved = PanelSettings::get('brand_logo');
            $file = Brand::logoFile(is_string($saved) ? $saved : null);

            return $file === null ? null : url('/simplify/brand/'.$file);
        }

        return null;
    }
}

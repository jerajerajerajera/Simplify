<?php

declare(strict_types=1);

namespace Simplify\Core\Embed\Services;

/**
 * The admin's own CSS for the status page (Settings › Website › Status page). The page runs on the panel's own
 * address, so the CSS may only style: nothing that loads a file from elsewhere or runs code, and nothing that ends the
 * style block. Whatever is not allowed is removed or made invalid (the browser then skips that declaration), and each
 * kind of removal is named so Settings can say what went. The page's Content-Security-Policy is the second fence.
 */
final class StatusCss
{
    /** At most this many bytes are kept. */
    public const MAX = 10240;

    /** A data: image that url() may still hold, so a small picture can go in without another site. */
    private const DATA_IMAGE = '#^\s*([\'"]?)data:image/(?:png|jpe?g|gif|webp);base64,[A-Za-z0-9+/=\s]+\1\s*$#i';

    /**
     * The CSS as it is saved, and the kinds of things removed: import, url, script, escape, markup, size.
     *
     * @return array{css: string, removed: list<string>}
     */
    public static function clean(string $css): array
    {
        $removed = [];
        $note = function (string $kind) use (&$removed): void {
            if (! in_array($kind, $removed, true)) {
                $removed[] = $kind;
            }
        };
        $css = str_replace("\0", '', $css);
        $css = (string) preg_replace('#/\*.*?(\*/|$)#s', '', $css);
        // Markup: "<" is never needed in CSS and could end the style block (</style>).
        if (str_contains($css, '<')) {
            $note('markup');
            $css = str_replace('<', '', $css);
        }
        // Escapes could spell a blocked word in another way (@\69mport, u\72l).
        if (str_contains($css, '\\')) {
            $note('escape');
            $css = str_replace('\\', '', $css);
        }
        $css = (string) preg_replace_callback('#@(?:import|charset|namespace)\b[^;{}]*;?#i', function () use ($note): string {
            $note('import');

            return '';
        }, $css);
        // url(...) only for a data: image; anything else becomes an unknown function, which the browser skips.
        $css = (string) preg_replace_callback('#\burl\s*\(([^)]*)\)?#i', function (array $match) use ($note): string {
            if (preg_match(self::DATA_IMAGE, $match[1]) === 1 && str_ends_with($match[0], ')')) {
                return $match[0];
            }
            $note('url');

            return 'blocked()';
        }, $css);
        // Other ways to load a file: image-set("x.png"), src("x"), image("x"), cross-fade(...).
        $css = (string) preg_replace_callback('#\b(?:-webkit-)?(?:image-set|image|src|cross-fade|element)\s*\(#i', function () use ($note): string {
            $note('url');

            return 'blocked(';
        }, $css);
        // Code: old IE expression(), javascript: and the bindings of IE and old Firefox.
        $css = (string) preg_replace_callback('#\bexpression\s*\(|javascript\s*:|vbscript\s*:|\bbehavior\s*:|-moz-binding\s*:#i', function () use ($note): string {
            $note('script');

            return 'blocked(';
        }, $css);
        $css = trim($css);
        if (strlen($css) > self::MAX) {
            $note('size');
            $css = mb_strcut($css, 0, self::MAX);
        }

        return ['css' => $css, 'removed' => $removed];
    }
}

<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Livewire\Exceptions\ComponentNotFoundException;
use Throwable;

/**
 * One fresh try instead of a 500 when a page asks for a Livewire component that is not there (K-B63).
 *
 * Right after an update the request that sees the new version starts PHP's opcache over (FreshViews), but that
 * request itself still runs the previous version's classes, which may not register a component the new code asks for
 * ("Unable to find component: [simplify-hosting-settings-form]"). The next request reads every class again.
 */
final class StaleCode
{
    /** Livewire's own answer to a call on a page that is gone: the browser offers to load the page again. */
    public const EXPIRED = 'expired';

    /** Open the same page once more. */
    public const RELOAD = 'reload';

    /** Show the error as it is. */
    public const ERROR = 'error';

    /** The query parameter that marks the second try, so a component that is really missing never loops. */
    public const RETRIED = 'simplify-fresh';

    public static function answer(bool $livewire, string $method, bool $retried): string
    {
        if ($livewire) {
            return self::EXPIRED;
        }

        return in_array($method, ['GET', 'HEAD'], true) && ! $retried ? self::RELOAD : self::ERROR;
    }

    public static function retryUrl(string $url): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').self::RETRIED.'=1';
    }

    /** Also when a view wraps the error: a component missing in a Blade page reaches the handler as a ViewException. */
    public static function isMissingComponent(?Throwable $error): bool
    {
        for (; $error !== null; $error = $error->getPrevious()) {
            if ($error instanceof ComponentNotFoundException) {
                return true;
            }
        }

        return false;
    }

    public static function register(Application $app): void
    {
        $handler = $app->make(ExceptionHandler::class);
        if (! method_exists($handler, 'renderable')) {
            return;
        }
        $handler->renderable(function (Throwable $error, Request $request) {
            if (! self::isMissingComponent($error)) {
                return null;
            }
            $answer = self::answer($request->hasHeader('X-Livewire'), $request->getMethod(), $request->query->has(self::RETRIED));
            if ($answer === self::ERROR) {
                return null;
            }
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            return $answer === self::EXPIRED ? response('', 419) : redirect()->to(self::retryUrl($request->fullUrl()));
        });
    }
}

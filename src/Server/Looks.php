<?php

declare(strict_types=1);

namespace Simplify\Core\Server;

use App\Enums\SubuserPermission;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Simplify\Core\Models\ServerLook;

/** A server's colour and banner. Whoever may rename a server may change how it looks. */
final class Looks
{
    public const DIRECTORY = 'simplify/banners';

    public const FILE_PATTERN = '[A-Za-z0-9_-]{1,80}\.(png|jpe?g|webp)';

    public static function canEdit(User $user, Server $server): bool
    {
        return $user->can(SubuserPermission::SettingsRename, $server);
    }

    /** @return array{color: ?string, banner: ?string} the banner as a URL */
    public static function for(Server $server): array
    {
        return self::present(ServerLook::query()->where('server_id', $server->id)->first());
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{color: ?string, banner: ?string}> by server id, only servers with a look
     */
    public static function forMany(array $ids): array
    {
        return ServerLook::query()->whereIn('server_id', $ids)->get()
            ->mapWithKeys(fn (ServerLook $look) => [$look->server_id => self::present($look)])->all();
    }

    /**
     * Saves the colour and banner; a replaced banner is deleted. $banner is a path made by an upload, null for no
     * banner, or false to keep the current one.
     */
    public static function save(Server $server, ?string $color, string|false|null $banner): void
    {
        $color = $color !== null && preg_match('/^#[0-9a-f]{6}$/i', $color) ? strtolower($color) : null;
        $look = ServerLook::query()->firstOrNew(['server_id' => $server->id]);
        $old = $look->banner;
        if ($banner === false) {
            $banner = $old;
        } elseif ($banner !== null && ! preg_match('#^'.self::DIRECTORY.'/'.self::FILE_PATTERN.'$#', $banner)) {
            $banner = null;
        }
        $look->fill(['color' => $color, 'banner' => $banner])->save();
        if ($old !== null && $old !== $banner) {
            Storage::disk('local')->delete($old);
        }
    }

    /** The local path of a banner file, if it exists. */
    public static function path(string $file): ?string
    {
        $path = self::DIRECTORY.'/'.$file;

        return preg_match('#^'.self::FILE_PATTERN.'$#', $file) && Storage::disk('local')->exists($path)
            ? Storage::disk('local')->path($path)
            : null;
    }

    /** @return array{color: ?string, banner: ?string} */
    private static function present(?ServerLook $look): array
    {
        return [
            'color' => $look?->color,
            'banner' => $look?->banner ? url('/simplify/banners/'.basename($look->banner)) : null,
        ];
    }
}

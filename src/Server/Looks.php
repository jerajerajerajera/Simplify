<?php

declare(strict_types=1);

namespace Simplify\Core\Server;

use App\Enums\SubuserPermission;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Simplify\Core\Models\ServerLook;
use Simplify\Core\Overview\Artwork;
use Simplify\Core\Support\Assets;

/**
 * A server's colour and banner. Whoever may rename a server may change how it looks. The banner is an uploaded file
 * (one upload can serve several servers), one of Simplify's own scenes ("scene:bg-valley.jpg"), or none: then
 * Artwork picks a scene by itself.
 */
final class Looks
{
    /** The server colours to pick from (hex => name), also for other plugins such as Server Maker. */
    public const COLORS = ['#ef4444' => 'Red', '#f97316' => 'Orange', '#eab308' => 'Gold', '#84cc16' => 'Lime', '#22c55e' => 'Green', '#10b981' => 'Mint',
        '#06b6d4' => 'Cyan', '#3b82f6' => 'Blue', '#6366f1' => 'Indigo', '#a855f7' => 'Purple', '#ec4899' => 'Pink', '#f43f5e' => 'Rose'];

    public const DIRECTORY = 'simplify/banners';

    public const SCENE = 'scene:';

    public const FILE_PATTERN = '[A-Za-z0-9_-]{1,80}\.(png|jpe?g|webp)';

    public static function canEdit(User $user, Server $server): bool
    {
        return $user->can(SubuserPermission::SettingsRename, $server);
    }

    /** @return array{color: ?string, banner: ?string, banner_id: string} the banner as a URL */
    public static function for(Server $server): array
    {
        return self::present(ServerLook::query()->where('server_id', $server->id)->first());
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{color: ?string, banner: ?string, banner_id: string}> by server id, only servers with a look
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
        } elseif ($banner !== null && ! self::valid($banner)) {
            $banner = null;
        }
        $look->fill(['color' => $color, 'banner' => $banner])->save();
        if ($old !== null && $old !== $banner) {
            self::deleteUnused($old);
        }
    }

    /**
     * What someone can pick for a server: Simplify's scenes, and the uploads on servers they may change.
     *
     * @return list<array{id: string, url: string, upload: bool}>
     */
    public static function choices(User $user): array
    {
        $choices = array_map(fn (string $file) => ['id' => self::SCENE.$file, 'url' => Assets::url($file), 'upload' => false], [...Artwork::SCENES, Artwork::OTHER]);
        $servers = $user->accessibleServers()->get()->filter(fn (Server $server) => self::canEdit($user, $server))->modelKeys();
        $uploads = ServerLook::query()->whereIn('server_id', $servers)->where('banner', 'like', self::DIRECTORY.'/%')->distinct()->orderBy('banner')->pluck('banner');
        foreach ($uploads as $path) {
            $choices[] = ['id' => basename((string) $path), 'url' => url('/simplify/banners/'.basename((string) $path)), 'upload' => true];
        }

        return $choices;
    }

    /** A choice from choices() as it is stored: null for automatic. */
    public static function stored(User $user, string $id): string|false|null
    {
        if ($id === 'auto') {
            return null;
        }
        foreach (self::choices($user) as $choice) {
            if ($choice['id'] === $id) {
                return $choice['upload'] ? self::DIRECTORY.'/'.$id : $id;
            }
        }

        return false;
    }

    /**
     * Every uploaded banner, with the servers that show it, for the admins' list in Simplify's settings.
     *
     * @return list<array{file: string, url: string, servers: list<string>}>
     */
    public static function uploads(): array
    {
        $looks = ServerLook::query()->with('server:id,name')->where('banner', 'like', self::DIRECTORY.'/%')->get()->groupBy('banner');
        $uploads = [];
        foreach ($looks as $path => $group) {
            $uploads[] = ['file' => basename((string) $path), 'url' => url('/simplify/banners/'.basename((string) $path)), 'servers' => $group->map(fn (ServerLook $look) => (string) $look->server?->name)->filter()->values()->all()];
        }

        return $uploads;
    }

    /** Removes an upload: its servers go back to their automatic scene. */
    public static function forget(string $file): void
    {
        $path = self::DIRECTORY.'/'.$file;
        if (preg_match('#^'.self::FILE_PATTERN.'$#', $file)) {
            ServerLook::query()->where('banner', $path)->update(['banner' => null]);
            Storage::disk('local')->delete($path);
        }
    }

    private static function valid(string $banner): bool
    {
        if (str_starts_with($banner, self::SCENE)) {
            return in_array(substr($banner, strlen(self::SCENE)), [...Artwork::SCENES, Artwork::OTHER], true);
        }

        return preg_match('#^'.self::DIRECTORY.'/'.self::FILE_PATTERN.'$#', $banner) === 1;
    }

    /** An upload is deleted once no server shows it any more. */
    private static function deleteUnused(string $old): void
    {
        if (! str_starts_with($old, self::SCENE) && ! ServerLook::query()->where('banner', $old)->exists()) {
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

    /** @return array{color: ?string, banner: ?string, banner_id: string} */
    private static function present(?ServerLook $look): array
    {
        $banner = $look?->banner;

        return [
            'color' => $look?->color,
            'banner' => match (true) {
                $banner === null => null,
                str_starts_with($banner, self::SCENE) => Assets::url(substr($banner, strlen(self::SCENE))),
                default => url('/simplify/banners/'.basename($banner)),
            },
            // Which choice is picked, for the picker: auto, a scene, or the upload's file name.
            'banner_id' => $banner === null ? 'auto' : (str_starts_with($banner, self::SCENE) ? $banner : basename($banner)),
        ];
    }
}

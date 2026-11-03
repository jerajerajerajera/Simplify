<?php

declare(strict_types=1);

namespace Simplify\Core\Embed\Services;

use App\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Simplify\Core\Overview\Artwork;
use Simplify\Core\Server\Looks;
use Simplify\Core\Support\SafeCache;

/**
 * What a server shows on the website besides Pelican's own data, filled in by its owner or a root admin on the
 * server's Website page. Every field is optional: empty uses the server's description, its banner, the download
 * of its modpack, and the list's own order.
 */
final class EmbedDetails
{
    public const TABLE = 'simplify_core_embed_details';

    /** Images uploaded on the Website page, apart from the server banners. */
    public const DIRECTORY = 'simplify/embed';

    public const FIELDS = ['description', 'banner', 'download', 'address2', 'position'];

    public const DESCRIPTION = 300;

    /** @return array{description: ?string, banner: ?string, download: ?string, address2: ?string, position: ?int} */
    public static function for(Server $server): array
    {
        return self::many([(int) $server->id])[(int) $server->id] ?? array_fill_keys(self::FIELDS, null);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{description: ?string, banner: ?string, download: ?string, address2: ?string, position: ?int}>
     */
    public static function many(array $ids): array
    {
        $out = [];
        foreach (DB::table(self::TABLE)->whereIn('server_id', $ids)->get() as $row) {
            $out[(int) $row->server_id] = [
                'description' => self::text($row->description),
                'banner' => self::text($row->banner),
                'download' => self::text($row->download),
                'address2' => self::text($row->address2),
                'position' => $row->position === null ? null : (int) $row->position,
            ];
        }

        return $out;
    }

    /**
     * Saves the fields. $banner is null for the server's own banner, or a stored banner (Looks' format, or an
     * upload in DIRECTORY). A replaced upload is deleted when nothing else uses it.
     *
     * @param  array{description?: ?string, banner?: ?string, download?: ?string, address2?: ?string, position?: ?int}  $fields
     */
    public static function save(Server $server, array $fields): void
    {
        $old = self::for($server)['banner'];
        $values = [];
        foreach (self::FIELDS as $field) {
            $value = $fields[$field] ?? null;
            $values[$field] = $field === 'position' ? ($value === null ? null : max(0, min(9999, (int) $value))) : self::text($value);
        }
        if ($values['banner'] !== null && ! self::validBanner($values['banner'])) {
            $values['banner'] = $old;
        }
        if (array_filter($values, fn ($value) => $value !== null) === []) {
            DB::table(self::TABLE)->where('server_id', $server->id)->delete();
        } else {
            DB::table(self::TABLE)->updateOrInsert(['server_id' => $server->id], [...$values, 'updated_at' => now(), 'created_at' => now()]);
        }
        if ($old !== null && $old !== $values['banner']) {
            self::deleteUnused($old);
        }
        SafeCache::forget(ServerList::CACHE);
    }

    /** A banner as it may be stored: a scene, a server banner upload, or an upload of the Website page. */
    public static function validBanner(string $banner): bool
    {
        if (str_starts_with($banner, Looks::SCENE)) {
            return in_array(substr($banner, strlen(Looks::SCENE)), [...Artwork::SCENES, Artwork::OTHER], true);
        }

        return preg_match('#^('.preg_quote(Looks::DIRECTORY, '#').'|'.preg_quote(self::DIRECTORY, '#').')/'.Looks::FILE_PATTERN.'$#', $banner) === 1;
    }

    /** The local path of an image uploaded on a Website page, if it exists. */
    public static function path(string $file): ?string
    {
        $path = self::DIRECTORY.'/'.$file;

        return preg_match('#^'.Looks::FILE_PATTERN.'$#', $file) === 1 && Storage::disk('local')->exists($path)
            ? Storage::disk('local')->path($path)
            : null;
    }

    /**
     * The local path of an uploaded image for the website: only when a server on the website shows it, from the
     * Website page or as its Simplify banner.
     */
    public static function publicPath(string $file): ?string
    {
        if (preg_match('#^'.Looks::FILE_PATTERN.'$#', $file) !== 1) {
            return null;
        }
        $shown = EmbedServers::ids();
        foreach ([self::DIRECTORY, Looks::DIRECTORY] as $directory) {
            $path = $directory.'/'.$file;
            $used = DB::table(self::TABLE)->whereIn('server_id', $shown)->where('banner', $path)->exists()
                || ($directory === Looks::DIRECTORY && DB::table('simplify_server_looks')->whereIn('server_id', $shown)->where('banner', $path)->exists());
            if ($used && Storage::disk('local')->exists($path)) {
                return Storage::disk('local')->path($path);
            }
        }

        return null;
    }

    private static function deleteUnused(string $banner): void
    {
        if (str_starts_with($banner, self::DIRECTORY.'/') && ! DB::table(self::TABLE)->where('banner', $banner)->exists()) {
            Storage::disk('local')->delete($banner);
        }
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}

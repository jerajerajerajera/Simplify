<?php

declare(strict_types=1);

namespace Simplify\Core\Embed\Services;

use App\Models\Egg;
use App\Models\Server;
use Illuminate\Support\Facades\Storage;
use Simplify\Core\Server\Looks;
use Simplify\Core\Subdomains\Services\Games;
use Simplify\Core\Support\Assets;
use Throwable;

/**
 * The logo of a server on the website, from its egg: a logo uploaded for that egg in Settings › Website, else the
 * egg's own icon in Pelican, else the logo of the software it runs (Paper, Velocity, Python, …; resources/img/LOGOS.md),
 * else the Minecraft block for any other Minecraft server. Null leaves the server's initials.
 */
final class EggLogo
{
    /** Logos uploaded per egg. */
    public const DIRECTORY = 'simplify/embed/logos';

    /** From the egg's name, its tags and the Docker image: the first that matches wins (Python before Discord: a bot in Python gets Python's). */
    private const SOFTWARE = [
        'paper' => '/\bpaper(mc)?\b/i',
        'velocity' => '/velocity/i',
        'spigot' => '/spigot/i',
        'modrinth' => '/modrinth/i',
        'curseforge' => '/curse ?forge/i',
        'python' => '/python|discord\.py/i',
        'nodejs' => '/\bnode(js|\.js)?\b|discord\.js|yolks:nodejs/i',
        'go' => '/golang|\bgo\b|yolks:go_/i',
        'dotnet' => '/\.net\b|dotnet/i',
        'discord' => '/discord|red-?bot/i',
    ];

    public static function validUpload(string $file): bool
    {
        return preg_match('#^'.preg_quote(self::DIRECTORY, '#').'/'.Looks::FILE_PATTERN.'$#', $file) === 1;
    }

    /** The bundled logo for this text (egg name, tags, image), such as "logo-paper.svg"; null when nothing matches. */
    public static function software(string $text): ?string
    {
        foreach (self::SOFTWARE as $name => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return 'logo-'.$name.'.svg';
            }
        }

        return null;
    }

    /** The automatic logo of a server: its software's, else the Minecraft block for Minecraft. */
    public static function automatic(Server $server): ?string
    {
        // A server whose egg is gone has no logo (Pelican types the egg as always there).
        try {
            $egg = $server->egg;
            $name = self::software(implode(' ', [(string) $egg->name, implode(' ', (array) $egg->tags), (string) $server->image]));

            return $name ?? (Games::minecraft($server) ? 'game-minecraft.svg' : null);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The logo's public address and where it comes from.
     *
     * @param  array<int, string>  $uploads  EmbedOptions' logos
     * @return array{url: string, from: 'upload'|'pelican'|'software'}|null
     */
    public static function for(Server $server, array $uploads): ?array
    {
        $upload = $uploads[(int) $server->egg_id] ?? null;
        if (is_string($upload) && self::validUpload($upload)) {
            return ['url' => route('simplify.embed.logo', ['file' => basename($upload)]), 'from' => 'upload'];
        }
        // A server whose egg is gone has none (Pelican types the egg as always there).
        $egg = $server->getRelationValue('egg');
        $own = $egg instanceof Egg ? $egg->icon : null;
        if (is_string($own) && $own !== '') {
            return ['url' => $own, 'from' => 'pelican'];
        }
        $automatic = self::automatic($server);

        return $automatic === null ? null : ['url' => Assets::url($automatic), 'from' => 'software'];
    }

    /** The local path of an uploaded logo that is in use, for the public route; null for anything else. */
    public static function publicPath(string $file): ?string
    {
        if (preg_match('#^'.Looks::FILE_PATTERN.'$#', $file) !== 1) {
            return null;
        }
        $path = self::DIRECTORY.'/'.$file;
        $options = EmbedOptions::all();
        // An egg's logo, or the status page's own logo.
        if ((! in_array($path, $options['logos'], true) && $path !== $options['page_logo_file']) || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->path($path);
    }
}

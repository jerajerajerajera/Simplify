<?php

declare(strict_types=1);

namespace Simplify\Core\Hub;

/**
 * Plain text from the Markdown descriptions on the Hub.
 */
final class HubText
{
    /** The first sentences as plain text, without headings, images, badges or code. */
    public static function summary(string $markdown, int $max = 160): string
    {
        $text = preg_replace('/```.*?```/s', ' ', $markdown) ?? '';
        $lines = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^(#|!\[|\[!\[|<|>|\||-{3,}|_?originally (created|made) by)/i', $line)) {
                continue;
            }
            $lines[] = preg_replace('/^(?:[-*+]|\d+\.)\s+/', '', $line) ?? $line;
        }
        $text = implode(' ', $lines);
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $text) ?? '';
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? '';
        $text = str_replace(['**', '__', '`'], '', $text);
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max);
        $sentence = (int) mb_strrpos($cut, '. ');
        if ($sentence > 60) {
            return mb_substr($cut, 0, $sentence + 1);
        }
        $word = mb_strrpos($cut, ' ') ?: $max;

        return rtrim(mb_substr($cut, 0, $word), ' ,;:').'…';
    }

    /**
     * "Originally created by [Name](https://...)" in a description, so the original maker is credited too.
     *
     * @return array{name: string, url: string}|null
     */
    public static function basedOn(string $markdown): ?array
    {
        if (! preg_match('/originally (?:created|made) by \[([^\]]{1,60})\]\((https:\/\/[^)\s]+)\)/i', $markdown, $match)) {
            return null;
        }

        return ['name' => trim($match[1]), 'url' => $match[2]];
    }

    public static function clip(string $value, int $max): string
    {
        return mb_substr(trim(strip_tags($value)), 0, $max);
    }
}

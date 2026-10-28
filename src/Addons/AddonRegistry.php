<?php

declare(strict_types=1);

namespace Simplify\Core\Addons;

use Closure;
use Throwable;

/**
 * The add-ons that tagged themselves for Simplify. A broken add-on is reported and left out, so it never breaks a page.
 */
final class AddonRegistry
{
    public const TAG = 'simplify.addons';

    /** @var list<Addon>|null */
    private ?array $addons = null;

    /**
     * @param  Closure(): iterable<string>  $classes  the tagged class names
     * @param  Closure(string): mixed  $make  builds one add-on from its class name
     * @param  Closure(Throwable): void  $report
     */
    public function __construct(
        private readonly Closure $classes,
        private readonly Closure $make,
        private readonly Closure $report,
    ) {}

    /** @return list<Addon> sorted by name, one per id */
    public function all(): array
    {
        return $this->addons ??= $this->load();
    }

    public function find(string $id): ?Addon
    {
        foreach ($this->all() as $addon) {
            if ($addon->id() === $id) {
                return $addon;
            }
        }

        return null;
    }

    /**
     * Collects one value from every add-on, for example all dashboard buttons.
     *
     * @template T
     *
     * @param  Closure(Addon): list<T>  $read
     * @return list<T>
     */
    public function collect(Closure $read): array
    {
        $values = [];
        foreach ($this->all() as $addon) {
            try {
                array_push($values, ...$read($addon));
            } catch (Throwable $error) {
                ($this->report)($error);
            }
        }

        return $values;
    }

    /** @return list<Addon> */
    private function load(): array
    {
        $byId = [];
        foreach (($this->classes)() as $class) {
            try {
                $addon = ($this->make)($class);
                if ($addon instanceof Addon) {
                    $byId[$addon->id()] ??= $addon;
                }
            } catch (Throwable $error) {
                ($this->report)($error);
            }
        }
        uasort($byId, fn (Addon $a, Addon $b) => strnatcasecmp($a->name(), $b->name()));

        return array_values($byId);
    }
}

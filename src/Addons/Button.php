<?php

declare(strict_types=1);

namespace Simplify\Core\Addons;

/**
 * A button that an add-on shows in Simplify, for example "Create server" on the dashboard.
 */
final readonly class Button
{
    public function __construct(
        public string $label,
        public string $url,
        public ?string $icon = null,
        public bool $primary = false,
    ) {}
}

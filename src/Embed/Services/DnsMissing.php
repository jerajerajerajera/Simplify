<?php

declare(strict_types=1);

namespace Simplify\Core\Embed\Services;

use RuntimeException;

/** The status address has no record in public DNS yet (StatusAddress::check). */
final class DnsMissing extends RuntimeException {}

<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

/**
 * One server's live numbers, from the utilization block Wings returns. A number Wings did not send stays null,
 * so a tile shows a dash instead of an invented zero.
 */
final readonly class Usage
{
    public function __construct(
        public string $state,
        public ?float $cpuPercent,
        public ?int $memoryBytes,
        public ?int $diskBytes,
        public ?int $rxBytes = null,
        public ?int $txBytes = null,
    ) {}

    /** @param  array<string, mixed>  $resources */
    public static function fromWings(string $state, array $resources): self
    {
        $disk = self::number($resources['disk_bytes'] ?? null);
        if ($state === 'offline') {
            return new self($state, 0.0, 0, $disk, 0, 0);
        }

        $cpu = $resources['cpu_absolute'] ?? null;

        return new self(
            $state,
            self::valid($cpu) ? round((float) $cpu, 1) : null,
            self::number($resources['memory_bytes'] ?? null),
            $disk,
            self::number($resources['network']['rx_bytes'] ?? null),
            self::number($resources['network']['tx_bytes'] ?? null),
        );
    }

    public static function unknown(): self
    {
        return new self('missing', null, null, null);
    }

    /** @return array{state: string, cpu: ?float, memory: ?int, disk: ?int, rx: ?int, tx: ?int} */
    public function toArray(): array
    {
        return ['state' => $this->state, 'cpu' => $this->cpuPercent, 'memory' => $this->memoryBytes, 'disk' => $this->diskBytes, 'rx' => $this->rxBytes, 'tx' => $this->txBytes];
    }

    private static function number(mixed $value): ?int
    {
        return self::valid($value) ? (int) round((float) $value) : null;
    }

    private static function valid(mixed $value): bool
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value >= 0;
    }
}

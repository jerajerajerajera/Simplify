<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A root admin's own chat command for an egg.
 *
 * @property int $egg_id
 * @property string $command with {message}, or "-" to skip that game
 */
final class GameCommand extends Model
{
    protected $table = 'simplify_core_announcement_commands';

    protected $primaryKey = 'egg_id';

    public $incrementing = false;

    protected $fillable = ['egg_id', 'command'];
}

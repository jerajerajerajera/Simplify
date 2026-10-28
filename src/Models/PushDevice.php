<?php

declare(strict_types=1);

namespace Simplify\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A browser or installed app that a user turned app notifications on for.
 *
 * @property int $id
 * @property int $user_id
 * @property string $device short hash of the endpoint, so the same browser replaces its old entry
 * @property string $endpoint
 * @property string $p256dh
 * @property string $auth
 * @property string $label
 * @property ?list<string> $received tags of the last messages sent once, so none arrives twice
 * @property ?Carbon $last_sent_at
 * @property Carbon $created_at
 */
final class PushDevice extends Model
{
    protected $table = 'simplify_push_devices';

    protected $fillable = ['user_id', 'device', 'endpoint', 'p256dh', 'auth', 'label', 'received', 'last_sent_at'];

    protected $hidden = ['endpoint', 'p256dh', 'auth'];

    protected $casts = ['received' => 'array', 'last_sent_at' => 'datetime'];
}

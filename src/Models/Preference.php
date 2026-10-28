<?php

declare(strict_types=1);

namespace Simplify\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One choice a user made in Simplify, such as the overview layout. Saved per account, so every device shows the same.
 *
 * @property int $user_id
 * @property string $key
 * @property mixed $value
 */
final class Preference extends Model
{
    protected $table = 'simplify_preferences';

    protected $fillable = ['user_id', 'key', 'value'];

    protected $casts = ['value' => 'json'];
}

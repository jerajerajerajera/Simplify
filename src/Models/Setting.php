<?php

declare(strict_types=1);

namespace Simplify\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One panel-wide Simplify setting, chosen by a root admin on the settings page.
 *
 * @property string $key
 * @property mixed $value
 */
final class Setting extends Model
{
    protected $table = 'simplify_settings';

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'json'];
}

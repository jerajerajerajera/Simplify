<?php

declare(strict_types=1);

namespace Simplify\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How a server looks on the overview and its own page: a colour and a banner image, chosen by its owner.
 *
 * @property int $server_id
 * @property ?string $color
 * @property ?string $banner path on the local disk
 */
final class ServerLook extends Model
{
    protected $table = 'simplify_server_looks';

    protected $fillable = ['server_id', 'color', 'banner'];
}

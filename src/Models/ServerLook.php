<?php

declare(strict_types=1);

namespace Simplify\Core\Models;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How a server looks on the overview and its own page: a colour and a banner image, chosen by its owner.
 *
 * @property int $server_id
 * @property ?string $color
 * @property ?string $banner path on the local disk, or one of Simplify's scenes (Looks::SCENE)
 */
final class ServerLook extends Model
{
    protected $table = 'simplify_server_looks';

    protected $fillable = ['server_id', 'color', 'banner'];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The announcement form asks what, when, where and who (V65, 22-11): every place (bar, bell, app, game chat) works
// with every moment. The bar is a choice of its own with its own length (bar, stay, stay_minutes), a week day takes
// several times (weekly_times), a server moment is a start, a stop or a crash (start_event; start_pending holds the
// stops that wait to be called a crash), its chat goes to that server or to chosen ones (in_game_target) and its bar
// knows which server it is about (shown_server). Existing announcements keep doing what they did: one at a time and
// one on week days keep their bar, one on a server start keeps none and now reaches the people of that server
// instead of everyone on the panel. Like every Simplify migration, it can run again.
return new class extends Migration
{
    private const TABLE = 'simplify_core_announcements';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }
        foreach (['bar', 'stay', 'stay_minutes', 'start_event', 'weekly_times', 'in_game_target', 'shown_server', 'start_pending'] as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                continue;
            }
            Schema::table(self::TABLE, function (Blueprint $table) use ($column) {
                match ($column) {
                    'bar' => $table->boolean('bar')->default(true),
                    // Empty: not carried over yet (see below).
                    'stay' => $table->string('stay', 8)->nullable(),
                    'stay_minutes' => $table->unsignedInteger('stay_minutes')->default(180),
                    'start_event' => $table->string('start_event', 8)->default('start'),
                    'weekly_times' => $table->json('weekly_times')->nullable(),
                    'in_game_target' => $table->string('in_game_target', 8)->nullable(),
                    'shown_server' => $table->unsignedInteger('shown_server')->nullable(),
                    'start_pending' => $table->json('start_pending')->nullable(),
                };
            });
        }

        // Every announcement written before this: what it did then, in the new columns.
        foreach (DB::table(self::TABLE)->whereNull('stay')->get() as $row) {
            $sendOn = Schema::hasColumn(self::TABLE, 'send_on') ? ($row->send_on ?? 'time') : 'time';
            $new = match ($sendOn) {
                'start' => [
                    'bar' => false,
                    'stay' => 'for',
                    'stay_minutes' => 10,
                    'in_game_target' => 'started',
                    'audience' => $row->audience === 'everyone' ? 'server' : $row->audience,
                ],
                'weekly' => [
                    'bar' => true,
                    'stay' => 'for',
                    'stay_minutes' => max(1, (int) ($row->weekly_hours ?? 24)) * 60,
                    'weekly_times' => is_string($row->weekly_at ?? null) && $row->weekly_at !== '' ? json_encode([$row->weekly_at]) : null,
                    'in_game_target' => 'servers',
                ],
                default => [
                    'bar' => true,
                    'stay' => $row->ends_at === null ? 'ended' : 'until',
                    'in_game_target' => 'servers',
                ],
            };
            DB::table(self::TABLE)->where('id', $row->id)->update($new);
        }
    }

    // Uninstalling Simplify keeps its data: Pelican rolls back a plugin's migrations on uninstall, and installing it
    // again finds every table and setting as it was.
    public function down(): void {}
};

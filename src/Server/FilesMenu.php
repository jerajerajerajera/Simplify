<?php

declare(strict_types=1);

namespace Simplify\Core\Server;

use App\Filament\Server\Resources\Files\Pages\ListFiles;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Support\Enums\IconSize;
use Simplify\Core\Support\Legacy;
use Throwable;

/**
 * Files: a file's Delete stands in its ⋯ menu, under Rename, Move and the rest, instead of as a red bin of its own next
 * to that menu (Jeroen 21-11: dangerous actions go in the ⋯ menu). Pelican builds a row's buttons in the page itself,
 * with no way in for a plugin, so Simplify moves the button once the page has built its table: the same button, with
 * Pelican's own check and its question before it deletes. The legacy panel keeps Pelican's row.
 */
final class FilesMenu
{
    /** For every Livewire component, right after it was built; only Pelican's Files list gets changed. */
    public static function tidy(object $component): void
    {
        if (! $component instanceof ListFiles || Legacy::on()) {
            return;
        }
        try {
            $table = $component->getTable();
            $actions = $table->getRecordActions();
            $menu = collect($actions)->first(fn ($action) => $action instanceof ActionGroup);
            $delete = collect($actions)->first(fn ($action) => $action instanceof DeleteAction);
            if ($menu === null || $delete === null) {
                return;
            }
            // In the menu it shows its name next to the bin, as the other items do; Pelican hides the name of a bin.
            $delete->grouped()->hiddenLabel(false)->tooltip(null)->iconSize(IconSize::Large);
            $menu->actions([...$menu->getActions(), $delete]);
            $table->recordActions(array_values(array_filter($actions, fn ($action) => $action !== $delete)));
        } catch (Throwable $error) {
            // Another Pelican with another row: the row stays as Pelican made it.
            report($error);
        }
    }
}

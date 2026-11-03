<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin;

use Livewire\Livewire;

/**
 * Pelican's Plugins page lists its categories (All, Plugin, Theme, Language Pack) as tabs above the table. Inside
 * Simplify they are a filter button in the table's toolbar, like the filters on the overview (Jeroen 3-11). The
 * choices, their counts and their query stay Pelican's own: the button only sets the page's activeTab.
 */
final class CategoryFilter
{
    /** The page whose tabs become the filter. */
    public const PAGE = 'App\\Filament\\Admin\\Resources\\Plugins\\Pages\\ListPlugins';

    public static function render(): string
    {
        $page = Livewire::current();
        if (! AdminTop::here() || ! is_object($page) || ! is_a($page, self::PAGE) || ! method_exists($page, 'getTabs')) {
            return '';
        }
        $options = [];
        foreach ($page->getTabs() as $key => $tab) {
            $options[] = ['key' => (string) $key, 'label' => (string) $tab->getLabel(), 'count' => $tab->getBadge(), 'icon' => $tab->getIcon()];
        }
        if (count($options) < 2) {
            return '';
        }
        $active = (string) ($page->activeTab ?? $options[0]['key']);

        return view('simplify::partials.category-filter', ['options' => $options, 'active' => $active])->render();
    }
}

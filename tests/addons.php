<?php

// The add-on registry without a panel: order, duplicates, and broken add-ons that must not break Simplify.
declare(strict_types=1);

require __DIR__.'/../src/Addons/Addon.php';
require __DIR__.'/../src/Addons/Button.php';
require __DIR__.'/../src/Addons/AddonRegistry.php';

use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Addons\Button;

final class MakerAddon extends Addon
{
    public function id(): string
    {
        return 'simplify-servermaker';
    }

    public function name(): string
    {
        return 'Server Maker';
    }

    public function dashboardActions(): array
    {
        return [new Button('Create server', '/admin/create', 'tabler-plus', primary: true)];
    }
}

final class EmbedAddon extends Addon
{
    public function id(): string
    {
        return 'simplify-embed';
    }

    public function name(): string
    {
        return 'Embed';
    }

    public function dashboardActions(): array
    {
        throw new RuntimeException('embed is broken');
    }
}

final class SecondMaker extends Addon
{
    public function id(): string
    {
        return 'simplify-servermaker';
    }

    public function name(): string
    {
        return 'Another Server Maker';
    }
}

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

$reported = [];
$registry = new AddonRegistry(
    fn () => [MakerAddon::class, 'MissingAddon', EmbedAddon::class, SecondMaker::class, stdClass::class],
    fn (string $class) => class_exists($class) ? new $class : throw new RuntimeException("no class $class"),
    function (Throwable $error) use (&$reported): void {
        $reported[] = $error->getMessage();
    },
);

$names = array_map(fn (Addon $addon) => $addon->name(), $registry->all());
check($names === ['Embed', 'Server Maker'], 'add-ons are sorted by name, one per id, other classes left out: '.json_encode($names));
check($reported === ['no class MissingAddon'], 'an add-on that cannot be built is reported and skipped: '.json_encode($reported));
check($registry->find('simplify-embed') instanceof EmbedAddon, 'find() returns an add-on by id');
check($registry->find('simplify-nothing') === null, 'find() returns null for an unknown id');

$reported = [];
$actions = $registry->collect(fn (Addon $addon) => $addon->dashboardActions());
check(count($actions) === 1 && $actions[0]->label === 'Create server' && $actions[0]->primary, 'collect() gathers the buttons of every add-on');
check($reported === ['embed is broken'], 'collect() reports an add-on that throws and goes on: '.json_encode($reported));

$plain = new class extends Addon
{
    public function id(): string
    {
        return 'plain';
    }

    public function name(): string
    {
        return 'Plain';
    }
};
check($plain->description() === '' && $plain->icon() === 'tabler-puzzle' && $plain->settingsUrl() === null && $plain->dashboardActions() === [] && $plain->hiddenServerPrefixes() === [], 'an add-on only has to give an id and a name');

// The contract only grows with defaults: id() and name() stay the only methods an add-on must write.
$required = array_values(array_map(fn (ReflectionMethod $m) => $m->name, array_filter((new ReflectionClass(Addon::class))->getMethods(), fn (ReflectionMethod $m) => $m->isAbstract())));
check($required === ['id', 'name'], 'only id() and name() are abstract: '.json_encode($required));
check(is_int(Addon::API) && Addon::API >= 3, 'Addon::API names the contract version');
$plain->saveSetup(['anything' => true]);
check($plain->setupFields() === [] && $plain->setupState() === [], 'an add-on asks nothing in the setup unless it wants to');

if ($failures) {
    echo "FAIL add-ons:\n  ".implode("\n  ", $failures)."\n";
    exit(1);
}
echo "PASS add-on registry\n";

// The overview filters (resources/js/dashboard.js) without a panel. Usage: node tests/overview-filters.cjs
// K-B15 (Jeroen, 3-11, 2.0.0-beta.1): with one game, one node, only your own servers and no groups the Filter vanished on
// desktop and phone. The filter is per egg (not every server is a game) and stays there; CPU and RAM take the colour of their load.
const path = require('path');
global.window = { addEventListener() {} };
global.document = { addEventListener() {}, querySelector: () => null };
global.localStorage = { getItem: () => null, setItem() {} };
require(path.join(__dirname, '../resources/js/dashboard.js'));
let failed = 0;
const check = (label, ok, detail) => {
  if (!ok) failed++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}${ok ? '' : ': ' + JSON.stringify(detail)}`);
};
const make = (servers) => {
  const d = window.pelicanSimplify({}, { servers, user: 'jeroen' });
  d.filterText = { filter_egg: 'Egg', all_eggs: 'All eggs', filter_show: 'Show', all_servers: 'All servers', my_servers: 'My servers', others: 'Others', filter_node: 'Node', all_nodes: 'All nodes', filter_group: 'Group', all_groups: 'All groups' };
  d.overviewGroups = [];
  return d;
};
const server = (uuid, egg, software, extra = {}) => ({ uuid, egg_id: egg, software, game: 'minecraft', mine: true, node_id: 1, node_name: 'Beelink', ...extra });

// Jeroen's Beelink: Minecraft only, one node, all his own.
const beelink = make([server('a', 1, 'Paper'), server('b', 1, 'Paper'), server('c', 2, 'Forge')]);
const keys = (beelink.filterSections?.() || []).map((s) => s.key);
check('the filter stays with one game, one node and only your own servers', keys.length > 0, keys);
check('the first filter is the egg', keys[0] === 'egg', keys);
const eggs = (beelink.filterSections?.() || [])[0]?.options.map((o) => o.label);
check('each egg is its own choice, not one "Minecraft"', JSON.stringify(eggs) === JSON.stringify(['All eggs', 'Forge', 'Paper']), eggs);

const single = make([server('a', 1, 'Paper')]);
check('one egg still shows the filter', (single.filterSections?.() || []).some((s) => s.key === 'egg'), single.filterSections?.());

if (typeof beelink.setFilter === 'function' && keys[0] === 'egg') {
  const forge = beelink.filterSections()[0].options.find((o) => o.label === 'Forge').value;
  check('the count behind a choice', beelink.filterCount?.('egg', forge) === 1 && beelink.filterCount?.('egg', 'all') === 3, beelink.filterCount?.('egg', forge));
  beelink.filterMenu = 'egg';
  beelink.setFilter('egg', forge);
  check('choosing an egg narrows the servers and closes the menu', beelink.visibleServers().map((s) => s.uuid).join() === 'c' && !beelink.filterMenu, beelink.visibleServers().map((s) => s.uuid));
  check('the egg button names its choice', beelink.filterChoice(beelink.filterSections()[0]) === 'Forge', beelink.filterChoice(beelink.filterSections()[0]));
  beelink.filterMenu = 'egg';
  check('a desktop dropdown holds only its own filter', beelink.filterMenuSections().map((s) => s.key).join() === 'egg');
  beelink.filterMenu = 'all';
  check('the phone Filter holds every filter', beelink.filterMenuSections().length === beelink.filterSections().length);
}

// Mixed panel: others' servers and a second node add Show and Node after Egg.
const mixed = make([server('a', 1, 'Paper'), server('b', 3, 'Palworld', { mine: false, node_id: 2, node_name: 'VPS' })]);
check('Show and Node follow the egg when they have a choice', (mixed.filterSections?.() || []).map((s) => s.key).join() === 'egg,show,node', mixed.filterSections?.().map((s) => s.key));

// W4: the colour of CPU and RAM follows the load.
const level = (usage, key) => beelink.cardMetricLevel?.({ usage }, key);
check('CPU below 60% is calm', level({ cpu_percent: 12, cpu_limit: 100 }, 'cpu') === 'is-ok', level({ cpu_percent: 12, cpu_limit: 100 }, 'cpu'));
check('CPU from 60% warns', level({ cpu_percent: 130, cpu_limit: 200 }, 'cpu') === 'is-warn');
check('RAM from 85% of its limit is hot', level({ memory_bytes: 900, memory_limit_bytes: 1000 }, 'ram') === 'is-hot');
check('RAM without a limit stays calm', level({ memory_bytes: 900, memory_limit_bytes: 0 }, 'ram') === 'is-ok');
check('an unknown value is grey', level({ cpu_percent: null }, 'cpu') === 'is-none');

if (failed) { console.log(`${failed} failed`); process.exit(1); }

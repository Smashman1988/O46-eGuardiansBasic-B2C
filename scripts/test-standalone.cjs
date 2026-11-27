const fs = require('node:fs');
const path = require('node:path');
const { execFileSync, spawnSync } = require('node:child_process');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..');
process.chdir(root);
const listOnly = process.argv.includes('--list');
const env = process.env;
const out = path.join(root, '.cache', 'standalone');
const ceres = env.O46_CERES_ROOT || '.cache/perf-audit426/ref-ceres';
const io = env.O46_IO_ROOT || '.cache/perf-audit426/ref-io';
const photos = env.O46_CARD_PHOTOS || '.cache/b2c-parity/auftrag04-fotos';
const php = env.O46_PHP || 'php';
// PHP diagnostics belong in the log, outside the HTML captured by Twig's output buffer.
const phpFlags = ['-d', 'display_errors=stderr'];
if (env.O46_PHP_EXT) phpFlags.push('-d', 'extension_dir=' + env.O46_PHP_EXT, '-d', 'extension=mbstring');
const jobs = [];
const covered = new Set();
function test(file, args = [], tag = '', extraEnv = {}) {
    covered.add(file);
    const isPHP = file.endsWith('.php');
    jobs.push({name: file + tag, exe: isPHP ? php : process.execPath,
        args: [...(isPHP ? phpFlags : []), 'tests/' + file, ...args], extraEnv});
}

for (const v of [2, 3]) {
    const twig = (v === 2 ? env.O46_TWIG2_AUTOLOAD : env.O46_TWIG_AUTOLOAD) || '.cache/contact-widget/autoload' + v + '.php';
    const dir = path.join(out, String(v));
    const tag = '-twig' + v;
    for (const name of ['header_basket_context', 'twig_compatibility']) test(name + '.php', [twig], tag);
    for (const name of ['facets', 'card_attributes', 'card_image_loading', 'seo_pagination', 'pdp_render_contract',
        'navigation_contract', 'facet_delivery', 'live_shopping_grid']) {
        test(name + '.php', [twig, path.join(dir, name + '.json')], tag,
            name === 'seo_pagination' ? {O46_SEO_HISTORY_FIXTURES: path.join(dir, 'heads.json')} : {});
    }
    const old = path.join(dir, 'old.json'), current = path.join(dir, 'new.json');
    test('tracking_render.php', [twig, old, 'dca87e4'], tag + '-baseline');
    test('tracking_render.php', [twig, current], tag + '-current');
    test('tracking_contract.cjs', [old, current, path.join(dir, 'receipts.json')], tag);
    test('wish_list_layout.php', [twig, ceres], tag);
    test('facet_delivery.cjs', [path.join(dir, 'facet_delivery.json'), path.join(dir, 'facet-browser.json')], tag);
    if (v === 3) {
        test('ceres_language_mount.cjs', [ceres, twig, path.join(out, 'mount.json'), path.join(dir, 'seo_pagination.json')]);
        test('basket_quantity_sync.cjs', [ceres, twig]);
        test('pdp_gallery_browser.cjs', [path.join(dir, 'pdp.html'), path.join(out, 'gallery.json')]);
        test('wishlist_ceres.cjs', [ceres, path.join(dir, 'pdp.html')]);
        test('seo_scroll_history.cjs', [path.join(dir, 'heads.json'), path.join(out, 'history.json')]);
        test('card_flow.cjs', [dir, photos]);
    }
}
for (const [file, args] of Object.entries({
    asset_manifest: [], attribute_lookups: [], wishlist_availability: [], wishlist_logout: [io], 'ceres-list-query': [path.join(ceres, 'src')],
    content_controller: [path.join(out, 'content.json')], search_index: [ceres, io, path.join(out, 'search.json')],
    variation_salability: [io, path.join(out, 'salability.json')]
})) test(file + '.php', args);
for (const file of ['api_transport.cjs', 'auth_responses.cjs']) {
    test(file);
    test(file, ['--min'], '-min');
}
for (const name of ['cache_compatibility', 'variation_prices', 'quickview_pointer', 'search_response_order', 'search_escape', 'wishlist_cache', 'wishlist_isolation']) test(name + '.cjs');

// The aggregate npm test also checks the PlentyONE allowlist and rebuilds assets in check mode.
assert.ok(require('../package.json').scripts.test.includes('npm run test:plentyone'));
covered.add('plentyone_php.cjs');
jobs.push({name: 'npm-test', exe: process.execPath, args: [env.npm_execpath, 'test']});
for (const file of execFileSync('git', ['ls-files', '--cached', '--others', '--exclude-standard', '-z', '--', '*.php'], {encoding: 'utf8'}).split('\0').filter(Boolean)) {
    jobs.push({name: 'lint-' + file.replaceAll('/', '_'), exe: php, args: ['-l', file]});
}

// These files export helpers imported by the test programs. Browser specs have their own Playwright inventory.
const helpers = new Set(['card_markup_contract.php', 'list-fixtures.php', 'theme_assets.cjs']);
const missing = fs.readdirSync('tests').filter(file => /\.(cjs|php)$/.test(file) && !covered.has(file) && !helpers.has(file));
assert.deepEqual(missing, [], 'Testprogrammen fehlt ein Eintrag im Einzeltestlauf');
if (!listOnly) {
    for (const version of ['2', '3']) fs.mkdirSync(path.join(out, version), {recursive: true});
}
let failures = 0;
for (const job of jobs) {
    if (listOnly) { console.log(job.name); continue; }
    const log = fs.openSync(path.join(out, job.name + '.log'), 'w');
    try {
        const result = spawnSync(job.exe, job.args, {env: {...env, ...job.extraEnv}, stdio: ['ignore', log, log], timeout: 420000});
        const outcome = result.error?.message || result.signal || result.status;
        console.log(job.name, outcome);
        if (outcome !== 0) failures++;
    } finally { fs.closeSync(log); }
}
console.log(listOnly ? jobs.length + ' Einzelprogramme' : (jobs.length - failures) + '/' + jobs.length + ' bestanden');
if (failures) process.exitCode = 1;

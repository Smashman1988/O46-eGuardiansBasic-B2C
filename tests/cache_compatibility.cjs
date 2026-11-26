// Temporary originals for HTML cached before the theme's asset migration (T15a).
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');

const resources = path.join(__dirname, '../resources');
const originals = {
    'css/main-111025.min.css': '0b7ce7da32981645147964131b6dc975ef3827c77913cb4632b23c7733577ca9',
    'css/checkout-140525.min.css': '4f1bbf4447c2404522ce23692aa470eac58a8f01665003bae1b718ad6fdf7b0c',
    'js/cat-050525.min.js': '7383043fab7707ac453759146afdf38d7ff4688eb0c4eaf87c42e905f29a723c',
    'js/item-050525.min.js': 'a9480adbe72c23cff995a48eff2e972511478d36cfae163a29ed1a8bd362c966',
    'js/main-050525.min.js': '47e38f926601711fd78bfa6cea760183016b228c2687c788e55b66636ee65801',
    'js/vue-060525.min.js': 'e7b01b2f28211ffca4e2803b2e5dac58c4a3f163c2d7123bb183f74c31e8d1f4'
};

for (const [name, expected] of Object.entries(originals)) {
    const bytes = fs.readFileSync(path.join(resources, name));
    assert.equal(crypto.createHash('sha256').update(bytes).digest('hex'), expected, name);
}

const runtimeFiles = [
    'asset-manifest.json',
    'asset-versions.json',
    ...fs.readdirSync(path.join(resources, 'views'), {recursive: true})
        .filter(file => file.endsWith('.twig'))
        .map(file => path.join('views', file))
];
for (const file of runtimeFiles) {
    const source = fs.readFileSync(path.join(resources, file), 'utf8');
    for (const name of Object.keys(originals)) {
        assert.ok(!source.includes(path.basename(name)), file + ' references ' + name);
    }
}
console.log('Six cache compatibility assets match production and have no current template or manifest references.');


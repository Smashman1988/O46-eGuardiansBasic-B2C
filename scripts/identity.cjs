const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

function verifyIdentity(root) {
    const name = 'GuardiansBasic';
    const plugin = JSON.parse(fs.readFileSync(path.join(root, 'plugin.json'), 'utf8'));
    assert.equal(plugin.name, name);
    assert.equal(plugin.namespace, name);
    for (const provider of [plugin.serviceProvider, ...plugin.dataProviders.map(provider => provider.key)]) {
        assert.ok(provider.startsWith(name + '\\'), 'Unexpected provider: ' + provider);
        const file = provider.slice(name.length + 1).replaceAll('\\', '/') + '.php';
        assert.ok(fs.existsSync(path.join(root, 'src', file)), 'Missing provider: ' + file);
    }

    const files = ['plugin.json', 'config.json'];
    for (const dir of ['src', 'resources']) {
        const entries = fs.readdirSync(path.join(root, dir), {recursive: true, withFileTypes: true});
        for (const entry of entries) {
            if (entry.isFile() && /\.(php|twig|properties|json|js|css|svg)$/i.test(entry.name)) {
                files.push(path.relative(root, path.join(entry.parentPath, entry.name)));
            }
        }
    }
    const foreignName = new RegExp(name + 'B2B', 'i');
    for (const file of files) {
        const raw = fs.readFileSync(path.join(root, file), 'utf8');
        const decoded = raw.replace(/\\\//g, '/').replace(/%2f|&#(?:47|x2f);/gi, '/');
        assert.doesNotMatch(decoded, foreignName, 'Foreign theme identity in ' + file);
        assert.doesNotMatch(decoded, /\/(?:plugins?|pluginsets?)\/\d+(?=\/|$)/i, 'Numeric plugin path in ' + file);
        if (file.endsWith('.php')) {
            const directory = path.dirname(path.relative('src', file));
            const expected = directory === '.' ? name : name + '\\' + directory.split(path.sep).join('\\');
            assert.equal(raw.match(/^\s*namespace\s+([^;]+);/m)?.[1], expected, 'Namespace mismatch in ' + file);
        }
    }
    return files.length;
}

module.exports = {verifyIdentity};
if (require.main === module) console.log(verifyIdentity(path.resolve(__dirname, '..')) + ' runtime files checked');

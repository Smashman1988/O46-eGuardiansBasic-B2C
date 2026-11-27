// Offline deployment preflight. The stable7 snapshot lists PHP symbols separately from platform helpers.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const Parser = require('php-parser');
const policy = require('./fixtures/plentyone-stable7.json');
const parser = new Parser({ast: {withPositions: true}});
const lower = name => name.replace(/^\\/, '').toLowerCase();
const qualified = (ns, name) => [ns, name].filter(Boolean).join('\\').toLowerCase();
const allowed = {
    function: new Set([...policy.functions, 'pluginApp', 'publicPath', 'pluginSetId'].map(lower)),
    class: new Set(policy.classes.map(lower))
};
const platform = /^(plenty|io|ceres)\\/;
const classDeclarations = new Set(['class', 'interface', 'trait', 'enum']);
const callbackPosition = {array_map:0, array_filter:1, array_reduce:1, array_walk:1, array_walk_recursive:1,
    usort:1, uasort:1, uksort:1, array_udiff:-1, array_udiff_assoc:-1, array_uintersect:-1,
    array_uintersect_assoc:-1, array_diff_uassoc:-1, array_diff_ukey:-1, array_intersect_uassoc:-1, array_intersect_ukey:-1};

function walk(node, visit, parent, field) {
    if (!node?.kind) return;
    visit(node, parent, field);
    for (const [key, value] of Object.entries(node)) {
        if (Array.isArray(value)) value.forEach(child => walk(child, visit, node, key));
        else if (value?.kind) walk(value, visit, node, key);
    }
}

function imports(children) {
    const map = {function:new Map(), class:new Map()};
    for (const group of children.filter(n => n.kind === 'usegroup')) {
        for (const item of group.items) {
            const type = item.type || group.type || 'class';
            if (type === 'const') continue;
            const name = lower(qualified(group.name, item.name));
            map[type].set(lower(item.alias?.name || name.split('\\').pop()), name);
        }
    }
    return map;
}

function check(sources) {
    const scopes = [], declared = {function:new Set(), class:new Set()}, errors = [];
    function addScope(file, ns, children) {
        const aliases = imports(children);
        scopes.push({file, ns, children, aliases});
        for (const child of children) walk(child, node => {
            const kind = classDeclarations.has(node.kind) ? 'class' : node.kind === 'function' ? 'function' : '';
            if (kind && node.name) declared[kind].add(qualified(ns, node.name.name));
        });
    }
    for (const [file, source] of sources) {
        const tree = parser.parseCode(source, file);
        addScope(file, '', tree.children.filter(n => n.kind !== 'namespace'));
        for (const ns of tree.children.filter(n => n.kind === 'namespace')) addScope(file, ns.name, ns.children);
    }
    for (const {file, ns, children, aliases} of scopes) {
        function resolve(ref, kind) {
            const name = lower(ref.name);
            if (ref.resolution === 'fqn' || ref.name.startsWith('\\')) return name;
            if (ref.resolution === 'rn') return qualified(ns, name);
            const [first, ...remaining] = name.split('\\');
            const alias = (remaining.length ? aliases.class : aliases[kind]).get(first);
            if (alias) return qualified(alias, remaining.join('\\'));
            const local = qualified(ns, name);
            if (kind === 'function' && !remaining.length && !declared.function.has(local)) return name;
            return local;
        }
        function report(node, message) { errors.push(file + ':' + node.loc.start.line + ' ' + message); }
        function verify(node, kind, name) {
            if (!allowed[kind].has(name) && !declared[kind].has(name) && !(kind === 'class' && platform.test(name))) {
                report(node, kind + ' ' + name + ' is not allowed or declared');
            }
        }
        function callback(node) {
            if (node?.kind === 'variable') return report(node, 'dynamic callback cannot be verified');
            if (node?.kind === 'string') {
                const [name, method] = lower(node.value).split('::');
                verify(node, method ? 'class' : 'function', name);
            } else if (node?.kind === 'array' && node.items[0]?.value?.kind === 'string') {
                verify(node, 'class', lower(node.items[0].value.value));
            }
        }
        for (const child of children) walk(child, (node, parent, key) => {
            if (node.kind === 'useitem') {
                const type = node.type || parent.type || 'class';
                if (type !== 'const') verify(node, type, lower(qualified(parent.name, node.name)));
            }
            if (node.kind === 'call') {
                const callee = node.what;
                if (callee.kind === 'string') return callback(callee);
                if (callee.kind === 'variable') return report(callee, 'dynamic callable cannot be verified');
                if (callee.kind === 'staticlookup' && callee.what.kind === 'name' &&
                    resolve(callee.what, 'class') === 'closure' && callee.offset.name?.toLowerCase() === 'fromcallable') {
                    callback(node.arguments[0]);
                }
                if (callee.kind !== 'name') return;
                const name = resolve(callee, 'function');
                verify(callee, 'function', name);
                if (name === 'pluginapp' && node.arguments[0]?.kind === 'string') {
                    verify(node.arguments[0], 'class', lower(node.arguments[0].value));
                }
                if (Object.hasOwn(callbackPosition, name)) {
                    const named = node.arguments.find(arg => arg.kind === 'namedargument' && arg.name === 'callback');
                    callback(named ? named.value : node.arguments.at(callbackPosition[name]));
                }
                if (name === 'array_udiff_uassoc' || name === 'array_uintersect_uassoc') {
                    node.arguments.slice(-2).forEach(callback);
                }
                return;
            }
            if (node.kind === 'new' && node.what.kind === 'string') verify(node.what, 'class', lower(node.what.value));
            if (['new','staticlookup'].includes(node.kind) && node.what.kind === 'variable') {
                report(node.what, 'dynamic class cannot be verified');
            }
            if (node.kind === 'attribute') verify(node, 'class', resolve(node, 'class'));
            if (node.kind !== 'name') return;
            const isClass = ['extends','implements','traits','trait','instead','type','types'].includes(key) ||
                (key === 'what' && ['new','staticlookup','catch'].includes(parent.kind)) ||
                (parent.kind === 'bin' && parent.type === 'instanceof' && key === 'right');
            if (isClass) verify(node, 'class', resolve(node, 'class'));
        });
    }
    return errors;
}

// Positive and negative controls run in memory and use exactly the production analyser.
const controls = [
    [String.raw`namespace Shop; use function \preg_match as matches; class C { function ctype_digit() {} }
function myCheck() {} myCheck(); matches('/a/', 'a'); (new C)->ctype_digit(); C::ctype_digit();
pluginApp(\Plenty\Plugin\Http\Request::class); // ctype_digit()
$s = 'ctype_digit()';`, []],
    [String.raw`namespace A; use function \ctype_digit as digits; digits('4'); \CTYPE_DIGIT('5');`, ['function ctype_digit','function ctype_digit','function ctype_digit']],
    [String.raw`namespace A; use PDO as Db; new Db; Db::class; function f(\ReflectionClass $r) {}`, ['class pdo','class pdo','class pdo','class reflectionclass']],
    [String.raw`namespace A; use Plenty\Plugin\{Controller as Base, Http\Request}; class View extends Base {}`, []],
    [String.raw`namespace A; function ctype_digit() {} ctype_digit(); \ctype_digit('1');`, ['function ctype_digit']],
    [String.raw`array_map('ctype_digit', []); pluginApp('PDO');`, ['function ctype_digit','class pdo']],
    [String.raw`$fn = 'ctype_digit'; $fn(1); $name = 'PDO'; new $name;`, ['dynamic callable','dynamic class']],
    [String.raw`array_filter(callback:'ctype_digit', array:[]); \Closure::fromCallable('ctype_digit');`, ['function ctype_digit','function ctype_digit']],
    [String.raw`namespace A; function f($x) { return $x instanceof \ArrayAccess; } use \ReflectionFunction;`, ['class arrayaccess','class reflectionfunction']]
];
for (const [code, expected] of controls) {
    const actual = check([['control.php', '<?php '+code]]).map(s => s.split(' ').slice(1,3).join(' '));
    assert.deepEqual(actual, expected);
}

const root = path.resolve(__dirname, '..');
// Inspect every shipped directory, including new untracked PHP files. Test fixtures and tooling are local.
const local = new Set(['tests','scripts','node_modules','.git','.cache','.github']);
function sourceFiles(directory) {
    return fs.readdirSync(directory,{withFileTypes:true}).flatMap(entry => {
        const file = path.join(directory,entry.name);
        if (entry.isDirectory()) return directory === root && local.has(entry.name) ? [] : sourceFiles(file);
        return entry.isFile() && /\.(?:php[0-9]*|phtml|inc)$/i.test(entry.name) ? [file] : [];
    });
}
const files = sourceFiles(root);
assert.ok(files.length && files.some(file => file.endsWith('Base_Extension.php')), 'Missing production plugin source');
const errors = check(files.map(file => [path.relative(root,file),fs.readFileSync(file,'utf8')]));
if (errors.length) {
    console.error(errors.join('\n'));
    process.exitCode = 1;
} else {
    console.log('PlentyONE stable7: '+files.length+' production PHP files and '+controls.length+' controls passed.');
}

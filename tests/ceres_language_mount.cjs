// node tests/ceres_language_mount.cjs <Ceres checkout> <Twig autoload> [results.json] [seo-results.json]
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const root = path.resolve(__dirname,'..');
const [ceres,twig,output,seoReport] = process.argv.slice(2);
const php = process.env.O46_PHP || 'php';
const flags = process.platform === 'win32' ? ['-d','extension_dir='+path.join(path.dirname(php),'ext'),'-d','extension=mbstring'] : [];
const fragments = JSON.parse(execFileSync(php,[...flags,'tests/cross-browser/render.php',twig],{cwd:root,maxBuffer:16000000}));
const original = fs.readFileSync(path.join(ceres,'resources/js/dist/ceres-base.js'),'utf8');
const entry = 'return __webpack_require__(__webpack_require__.s = "./resources/js/src/base.js");';
assert.ok(original.includes(entry),'Expected Ceres webpack entry');
const vue = original.replace(entry,'__webpack_require__("./node_modules/bootstrap/dist/js/bootstrap.js"); __webpack_require__("./resources/js/src/app/jQuery.js"); window.Vue = __webpack_require__("./node_modules/vue/dist/vue.js"); window.Vuex = __webpack_require__("./node_modules/vuex/dist/vuex.esm.js").default; window.Vue.use(window.Vuex); window.Vue.prototype.$mount = __webpack_require__("./resources/js/src/mount.js").mount;');
const codes = ['de','en','fr','it','es'];
function loadRoutes(reportFile) {
    const readRoutes = file => JSON.parse(fs.readFileSync(file, 'utf8')).cases['b2c/storefront-route-matrix'];
    if (reportFile) return readRoutes(reportFile);

    const directory = fs.mkdtempSync(path.join(require('node:os').tmpdir(), 'o46-language-'));
    const report = path.join(directory, 'seo.json');
    try {
        execFileSync(php, [...flags, 'tests/seo_pagination.php', twig, report], {cwd: root});
        return readRoutes(report);
    } finally {
        if (fs.existsSync(report)) fs.unlinkSync(report);
        fs.rmdirSync(directory);
    }
}
const destinations = loadRoutes(seoReport);
assert.ok(destinations?.newsletter && destinations?.checkout && destinations?.category, 'Missing expected storefront route fixtures');
const mainScriptPages = ['home', 'privacy', 'registration', 'account', 'wish-list', 'basket', 'checkout', 'login', 'legal', 'terms'];
const usesCeresJquery = ['login', 'basket', 'checkout', 'account'];
const pages = new Map();
for (const [kind,targets] of Object.entries(destinations)) {
    for (const lang of codes) pages.set(targets[lang],{kind,lang,targets});
}
(async()=>{
    const browser = await chromium.launch();
    const cases = [];
    try {
        const page = await browser.newPage();
        page.setDefaultTimeout(5500);
        const errors=[];
        page.on('pageerror',e=>errors.push(e.message));
        await page.route('**/*',async route=>{
            const request=route.request(), url=new URL(request.url());
            if (url.pathname==='/ceres-vue.js') return route.fulfill({contentType:'text/javascript',body:vue});
            const asset=/^\/assets\/(jquery-3\.7\.1|core|cat|main|item)\.min\.js$/.exec(url.pathname);
            if(asset) return route.fulfill({path:path.join(root,'resources/js',asset[1]+'.min.js')});
            if(request.isNavigationRequest()){
                const current=pages.get(url.origin+url.pathname);
                assert.ok(current,url.pathname);
                const {kind,lang,targets}=current;
                const pageScript = kind === 'item' ? 'item' : mainScriptPages.includes(kind) ? 'main' : 'cat';
                const ceresJquery = usesCeresJquery.includes(kind);
                const parts=fragments.languages[lang];
                const routes=codes.map(c=>'<meta name="o46-language-route" lang="'+c+'" content="'+targets[c]+'">').join('');
                const body='<!doctype html><html lang="'+lang+'"><head>'+routes+'</head><body id="isVueView"><div id="vue-app">'+parts.header+'</div>'+
                    parts.languageOptions+fragments.dynamicModal+'<button id="totop"></button><script src="/ceres-vue.js"></script>'+
                    '<script>window.activeLang="'+lang+'";window.messageServiceTranslations={};window.addEventListener("load",()=>eGuardians.initialize());window.vueApp=new Vue({store:new Vuex.Store({state:{basket:{data:{itemSum:0}}}})}).$mount("#vue-app")</script>'+
                    (ceresJquery?'':'<script src="/assets/jquery-3.7.1.min.js"></script>')+'<script defer src="/assets/core.min.js"></script><script defer src="/assets/'+pageScript+'.min.js"></script></body></html>';
                return route.fulfill({contentType:'text/html',body});
            }
            assert.equal(request.resourceType(),'image','Unexpected service or widget call '+url.pathname);
            return route.fulfill({status:204});
        });
        for(const [kind,targets] of Object.entries(destinations)) for(const from of codes) for(const to of codes){
            if(from===to) continue;
            await page.goto(targets[from]);
            assert.ok(await page.evaluate(()=>window.vueApp.$el===document.querySelector('#vue-app') && window.vueApp._isMounted));
            assert.equal(await page.locator('#vue-app .eg-language-choice').count(),0);
            const account = page.locator('header .btn-login-open');
            assert.equal(await account.getAttribute('aria-label'), (await account.textContent()).trim());
            await page.locator('[data-eg-language-switch]').click();
            const option=page.locator('.dynamicModal .eg-language-choice[hreflang="'+to+'"]');
            await option.waitFor({state:'visible'}).catch(e=>{throw Error(e.message+' '+errors.join(' | '))});
            await option.click();
            await page.waitForURL(targets[to]);
            assert.equal(await page.locator('html').getAttribute('lang'),to);
            cases.push({kind,from,to});
        }
        assert.deepEqual(errors,[]);
        if(output) fs.writeFileSync(output,JSON.stringify({cases,errors},null,2));
        console.log(cases.length+' navigations after mounting the Vue runtime shipped by Ceres');
    } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

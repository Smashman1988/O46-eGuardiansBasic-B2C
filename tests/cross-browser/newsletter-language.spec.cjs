const { test: base, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file));
const languages = ['de', 'en', 'fr', 'it', 'es'];
const publicOrigin = 'https://www.outlet46.de';
const preview = 'https://preview.outlet46.test';
const routes = {
    home: ['/', '/en/', '/fr/', '/it/', '/es/'],
    category: ['/alle-artikel/', '/en/All-Articles/', '/fr/tous-les-articles/', '/it/tutti-gli-articoli/', '/es/todos-los-articulos/'],
    item: ['/schuh_42/', '/en/shoe_42/', '/fr/chaussure_42/', '/it/scarpa_42/', '/es/zapato_42/'],
    content: ['/newsletter-anmeldung/', '/en/newsletter-sign-up/', '/fr/inscription-a-la-newsletter/', '/it/iscriviti-alla-newsletter/', '/es/inscribase-al-boletin/']
};
function locate(pathname) {
    for (const [kind, paths] of Object.entries(routes)) {
        const index = paths.indexOf(pathname);
        if (index !== -1) return {kind, lang: languages[index]};
    }
    const variant = /^\/(?:(en|fr|it|es)\/)?42_102$/.exec(pathname);
    return variant && {kind:'item', lang:variant[1] || 'de'};
}
const styles = [...read('resources/views/PageDesign/Partials/Head.twig').toString().matchAll(/<style([^>]*)>[\s\S]*?<\/style>/g)];
const critical = [styles[2], ...styles.filter(m=>/data-eg-(first-paint|logo)-critical/.test(m[1]))]
    .map(m => m[0]).join('').replaceAll("{{ plugin_path('GuardiansBasic') }}", '/plugin/GuardiansBasic');

function render(fragments, kind, lang, options) {
    const parts = fragments.languages[lang];
    const bundle = {home:'main', category:'cat', item:'item', content:'main'}[kind];
    const tags = languages.map((code,i) => {
        if (code === options.missing) return '';
        const url = (code === options.foreign ? 'https://unknown.example' : publicOrigin) + routes[kind][i];
        return kind === 'category' ? '<meta name="o46-language-route" lang="'+code+'" content="'+url+'">'
            : '<link rel="alternate" hreflang="'+code+'" href="'+url+'">';
    }).join('');
    const title = {home:'Outlet46',category:'Alle Artikel',item:'Artikel 42',content:'Newsletter'}[kind];
    const tiles = ['d1','h1','all1','d1','h1','all1','d1','h1','all1','d1','h1','all1'].map(image =>
        '<div class="col-6 col-lg-4 mb-3"><img width="600" height="540" style="width:100%;height:auto" alt="" src="/plugin/GuardiansBasic/images/home/'+image+'.webp"></div>').join('');
    const product = kind === 'item' ? '<article class="single" data-item data-mainitemid="42"><div data-eg-variations=\'[{"variationId":102}]\'></div></article>' : '';
    return '<!doctype html><html lang="'+lang+'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'+tags+critical+
        '<link rel="stylesheet" href="/assets/main.min.css"></head><body data-eg-shop-dialogs>'+parts.header+
        '<main class="container" id="page-body" style="min-height:4600px"><h1>'+title+'</h1>'+product+'<div class="row" style="min-height:1800px;align-content:start">'+tiles+'</div><img class="w-100" src="/plugin/GuardiansBasic/images/home/all1.webp" alt=""><div class="row">'+tiles+'</div></main>'+
        (kind==='content'?'':parts.newsletter)+fragments.footer+fragments.dynamicModal+fragments.backdrop+
        '<script>window.activeLang="'+lang+'";window.messageServiceTranslations={searchexample:"Beispiele"};window.addEventListener("load",function(){eGuardians.initialize();document.body.dataset.ready="1"})</script>'+
        '<script src="/assets/jquery-3.7.1.min.js"></script><script defer src="/assets/core.min.js"></script><script defer src="/assets/'+bundle+'.min.js"></script>'+
        (kind==='category'?'<script defer src="/assets/facets.min.js"></script>':'')+'</body></html>';
}
const test = base.extend({
    store: async ({page, context}, use) => {
        const f = JSON.parse(read('.cache/cross-browser/fragments.json'));
        const options = {};
        const errors = [], writes = [], widgetRequests = [];
        page.on('pageerror', e => errors.push(e.stack||e.message));
        page.on('console', m => {if(m.type()==='error') errors.push(m.text());});
        await context.addCookies([{name:'fixture_session', value:'basket-46', url:preview, httpOnly:true, secure:true}]);
        await context.route('**/*', async route => {
            const req = route.request(), url = new URL(req.url()), p = url.pathname;
            if (req.method() !== 'GET') writes.push(req.method()+' '+p);
            if (p.includes('getShopBuilderContent')) widgetRequests.push(p);
            const asset = p.match(/^\/assets\/([\w.-]+\.(js|css))$/);
            if (asset) return route.fulfill({body:read('resources/'+asset[2]+'/'+asset[1]),contentType:asset[2]==='css'?'text/css':'text/javascript'});
            if (req.resourceType()==='image') {
                const flag = /\/flags\/4x3\/(de|gb|fr|it|es)\.svg$/.exec(p);
                if (flag) return route.fulfill({contentType:'image/png',body:read('resources/images/flags/4x3/'+flag[1]+'.png')});
                const local = p.match(/^\/plugin\/GuardiansBasic\/(images\/[\w./-]+)$/);
                if (local && !p.includes('..') && fs.existsSync(path.join(root,'resources',local[1]))) return route.fulfill({path:path.join(root,'resources',local[1])});
                return route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>'});
            }
            if (req.resourceType()==='font') {
                const file=path.join(root,'resources/documents/fonts',path.basename(p));
                if(fs.existsSync(file)) return route.fulfill({path:file});
            }
            if (p==='/rest/io/session/') return route.fulfill({json:{basket:{itemQuantity:2,itemWishListIds:[]}}});
            const entity = url.origin===preview && locate(p);
            if (entity && req.isNavigationRequest()) return route.fulfill({contentType:'text/html',body:render(f,entity.kind,entity.lang,options)});
            errors.push('Unexpected request '+req.method()+' '+url.href);
            return route.abort();
        });
        const open = async (p) => {
            await page.goto(preview+p);
            await expect(page.locator('body')).toHaveAttribute('data-ready','1');
        };
        await use({open, options, widgetRequests});
        expect(errors).toEqual([]);
        expect(writes).toEqual([]);
        expect(widgetRequests).toEqual([]);
        expect((await context.cookies(preview)).find(c=>c.name==='fixture_session')?.value).toBe('basket-46');
    }
});
const opener = page => page.locator('[data-eg-language-switch]');
const dialog = page => page.locator('.dynamicModal');
const choice = (page, lang) => dialog(page).locator('.eg-language-choice[hreflang="'+lang+'"]');

for (const [kind, paths] of Object.entries(routes)) {
    test('Sprachwechsel mit Navigation in allen 20 Richtungen auf '+kind, async ({page,store}) => {
        test.setTimeout(180000);
        for (const [index, source] of languages.entries()) {
            for (const [targetIndex, target] of languages.entries()) {
                if (source===target) continue;
                await store.open(paths[index]+'?currency=CHF&ShipToCountry=1');
                await opener(page).click();
                await expect(choice(page,source)).toHaveAttribute('aria-current','page');
                await Promise.all([page.waitForURL(preview+paths[targetIndex]+'?currency=CHF&ShipToCountry=1'),choice(page,target).click()]);
                await expect(page.locator('html')).toHaveAttribute('lang',target);
                await expect(page.locator('body')).toHaveAttribute('data-ready','1');
            }
        }
    });
}
test('Sprachdialog schließen, fehlende Übersetzung, fremder Host und ausgewählte Variante', async({page,store}, info)=>{
    await store.open(routes.home[0]);
    await opener(page).click();
    await expect(dialog(page).locator('.eg-language-choice')).toHaveCount(5);
    await page.screenshot({path:info.outputPath('sprachwahl.png'),animations:'disabled'});
    await page.keyboard.press('Escape');
    await expect(dialog(page)).toBeHidden();
    await opener(page).click();
    await dialog(page).locator('[data-dismiss="modal"]').first().click();
    await expect(dialog(page)).toBeHidden();
    Object.assign(store.options,{missing:'fr',foreign:'es'});
    await store.open(routes.item[1]+'?currency=CHF');
    await page.evaluate(()=>window.jQuery('[data-eg-variations]').data('activeVariationId',102));
    await opener(page).click();
    for(const code of ['fr','es']){
        const link=choice(page,code);
        await expect(link).toHaveAttribute('aria-disabled','true');
        await link.evaluate(el=>el.click());
        await expect(dialog(page).getByRole('status')).toBeVisible();
        await expect(page).toHaveURL(preview+routes.item[1]+'?currency=CHF');
    }
    await choice(page,'it').click();
    await expect(page).toHaveURL(preview+'/it/42_102?currency=CHF');
    await expect(page.locator('html')).toHaveAttribute('lang','it');
});
test('Newsletter in fünf Sprachen, Scrollschwelle, Consent, Überlagerung und erneuter Besuch', async({page,context,store},info)=>{
    const popup=page.locator('#o46-newsletter');
    for(const [index,lang] of languages.entries()){
        await store.open(routes.home[index]);
        await expect(popup).toBeHidden();
        await page.evaluate(()=>{window.ConsentManager={hasResponse:()=>false};window.scrollTo(0,2001);});
        await expect.poll(()=>page.evaluate(()=>scrollY)).toBeGreaterThanOrEqual(2000);
        await expect(popup).toBeHidden();
        await page.evaluate(()=>{window.ConsentManager.hasResponse=()=>true;document.dispatchEvent(new Event('consent-change'));});
        await expect(popup).toBeVisible();
        await expect(popup.getByRole('heading')).toContainText(/7\s*%/);
        await expect(popup.getByRole('link')).toHaveCount(1);
        await expect(popup.getByRole('link')).toHaveAttribute('href',routes.content[index]);
        const boxes=await popup.evaluate(el=>{
            const rect=el.getBoundingClientRect(),actions=el.querySelector('.o46-newsletter-actions').getBoundingClientRect(),link=el.querySelector('a').getBoundingClientRect();
            return {visible:rect.right<=innerWidth && rect.top>=0 && rect.bottom<=innerHeight, full:Math.abs(actions.width-link.width)<1};
        });
        expect(boxes).toEqual({visible:true,full:true});
        if(lang==='de'){
            await page.screenshot({path:info.outputPath('newsletter.png'),animations:'disabled'});
            for(const overlay of ['menu-open','search-open','filter-open','basket-preview-open']){
                await page.evaluate(c=>document.documentElement.classList.add(c),overlay);
                await expect(popup).toBeHidden();
                await page.evaluate(c=>document.documentElement.classList.remove(c),overlay);
            }
        }
        await popup.getByRole('link').click();
        await expect(page).toHaveURL(preview+routes.content[index]);
        await expect(page.locator('html')).toHaveAttribute('lang',lang);
    }
    await store.open('/');
    await page.evaluate(()=>window.scrollTo(0,1999));
    await expect.poll(()=>page.evaluate(()=>scrollY)).toBe(1999);
    await expect(popup).toBeHidden();
    await page.evaluate(()=>window.scrollTo(0,2000));
    await popup.getByRole('button').click();
    await expect(popup).toBeHidden();
    const cookie=(await context.cookies(preview)).find(c=>c.name==='o46_newsletter_dismissed');
    expect(cookie?.value).toBe('1');
    expect(cookie.expires-Date.now()/1000).toBeGreaterThan(604700);
    await store.open('/en/');
    await page.evaluate(()=>window.scrollTo(0,2050));
    await expect(popup).toBeHidden();
    await context.clearCookies({name:'o46_newsletter_dismissed'});
    await context.addCookies([{name:'showModal',value:'false',url:preview}]);
    await store.open('/');
    await page.evaluate(()=>window.scrollTo(0,2050));
    await expect(popup).toBeHidden();
});

test('Standardsprachroute, Sessionwährung und einmalige Initialisierung', async({page,store})=>{
    await page.addInitScript(()=>{
        const add=EventTarget.prototype.addEventListener;
        window.newsletterCloseBindings=0;
        EventTarget.prototype.addEventListener=function(type,callback,options){
            if(type==='click' && this instanceof Element && this.matches('.o46-newsletter-close')) window.newsletterCloseBindings++;
            return add.call(this,type,callback,options);
        };
    });
    await store.open('/fr/');
    await page.evaluate(()=>{
        // Model IO returning the default URL only once and a non-default session currency.
        document.querySelector('link[hreflang="de"]').hreflang='x-default';
        document.querySelector('#eg-language-options').dataset.currency='USD';
    });
    await page.addScriptTag({url:preview+'/assets/core.min.js'});
    expect(await page.evaluate(()=>window.newsletterCloseBindings)).toBe(1);
    await opener(page).click();
    await choice(page,'de').click();
    await expect(page).toHaveURL(preview+'/?currency=USD');
    await expect(page.locator('html')).toHaveAttribute('lang','de');
});

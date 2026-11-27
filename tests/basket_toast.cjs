// Exercise the delivered page bundles against same-origin IO fixtures. Set O46_TOAST_BASE
// to a local Git revision to also capture and compare the already released appearance.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.O46_PLAYWRIGHT_MODULE || 'playwright');
const base = process.env.O46_TOAST_BASE;
const langs = ['de','en','fr','it','es'];
const oldKeys = langs.map(lang => 'basketPreviewSmall:' + (lang==='de' ? '' : '/' + lang) + ':v2');
const out = path.resolve('.cache/basket-toast');
const foreign = {'basketPreviewSmall:/de:v2':'custom','basketPreviewSmall:/en:v20':'other','unrelated':'same'};
const read = (file, before) => before ? execFileSync('git',['show',base+':'+file],{maxBuffer:8e6}) : fs.readFileSync(file);

async function run(browser, lang, width, before) {
    const page = await browser.newPage({viewport:{width,height:860},serviceWorkers:'block'});
    try {
        const bundle = width===390 ? 'cat' : width===768 ? 'item' : 'main';
        const label = read('resources/lang/'+lang+'/Template.properties',before).toString().match(/^superinwkb\s*=\s*(.+)$/m)[1].trim();
        const twig = read('resources/views/DynamicContent/DynamicBasketPreviewSmall.twig',before).toString();
        const markup = twig.replace(/\{\{\s*trans\("[\w]+::Template\.superinwkb"\)\s*\}\}/,label);
        assert.ok(markup.includes(label));
        assert.match(read('resources/views/PageDesign/PageDesign.twig',before).toString(), /<template id="o46-basket-toast">.*DynamicBasketPreviewSmall/);
        const actions=[], pending=[], positions=new Set();
        const quickArticle='<article data-item data-eg-active-variation="202"><input data-item-quantity value="1"><button id="quick-buy">+</button><div class="item-actions-wrap"></div></article>';
        const basket={itemQuantity:0,itemSum:0,currency:'EUR',shippingCountryId:1,itemWishListIds:[]};
        const doc='<!doctype html><html lang="'+lang+'"><body><header id="page-header"><div class="basket-prev-small2"><div class="basket-prev-small-data"></div></div></header>'+
            '<template id="o46-basket-toast">'+markup+'</template><article data-item data-eg-active-variation="101"><input data-item-quantity value="1"><button id="buy">+</button><div class="item-actions-wrap"></div></article></body></html>';
        await page.route('**/*',async route=>{
            const req=route.request(), url=new URL(req.url());
            if(req.isNavigationRequest()) return route.fulfill({contentType:'text/html; charset=utf-8',body:doc});
            if(req.resourceType()==='font') {
                const font=path.join('resources/documents/fonts',path.basename(url.pathname));
                return fs.existsSync(font)?route.fulfill({path:font}):route.abort();
            }
            actions.push(req.method()+' '+url.pathname);
            if(url.pathname==='/quick-product' && url.searchParams.get('quickViewItem')==='true') return route.fulfill({contentType:'text/html; charset=utf-8',body:quickArticle});
            if(url.pathname==='/rest/io/basket/') return route.fulfill({json:{data:basket}});
            if(url.pathname==='/rest/io/basket/items/') return new Promise(resolve=>pending.push(async ok=>{
                if(ok) {
                    basket.itemQuantity++;
                    positions.add(new URLSearchParams(req.postData()).get('variationId'));
                }
                await route.fulfill({status:ok?201:409,json:{data:ok,events:{AfterBasketChanged:{basket}}}});resolve();
            }));
            return route.abort();
        });
        await page.goto('https://toast.invalid/'+lang+'/');
        await page.evaluate(({keys,foreign})=>{
            for (const key of keys) localStorage.setItem(key,'<div>legacy</div>');
            for (const [key,val] of Object.entries(foreign)) localStorage.setItem(key,val);
            sessionStorage.setItem('other','unchanged');
            window.messageServiceTranslations={'0':'Error'};
        },{keys:oldKeys,foreign});
        await page.addStyleTag({content:read('resources/css/main.min.css',before).toString()});
        for(const name of ['jquery-3.7.1','core',bundle]) await page.addScriptTag({content:read('resources/js/'+name+'.min.js',before).toString()});
        await page.evaluate(()=>{O46Core.basketHeader.start();return O46Core.basketHeader.pendingRead;});
        const storage=()=>page.evaluate(()=>({local:Object.fromEntries(Object.entries(localStorage)),session:Object.fromEntries(Object.entries(sessionStorage)),cookie:document.cookie}));
        const initial=await storage();
        for(const [k,v] of Object.entries(foreign)) assert.equal(initial.local[k],v);
        for(const k of oldKeys) assert.equal(initial.local[k],before?'<div>legacy</div>':undefined);
        const toast=page.locator('#wkb-toast');
        let activeButton='#buy';
        const start=async(selector='#buy')=>{
            activeButton=selector;
            const post=page.waitForRequest(r=>r.method()==='POST' && r.url().endsWith('/rest/io/basket/items/'));
            await page.evaluate(sel=>eGuardians.events.addItemToBasket(document.querySelector(sel)),selector);
            await post;
            for(let attempt=0;!pending.length && attempt<50;attempt++) await new Promise(r=>setTimeout(r,10));
            assert.equal(pending.length,1);
        };
        const finish=async ok=>{
            const received=page.waitForResponse(r=>r.url().endsWith('/rest/io/basket/items/'));
            await pending.shift()(ok); await received;
            await page.waitForFunction(sel=>!document.querySelector(sel)?.disabled,activeButton);
        };
        const from=actions.length;
        await start();assert.equal(await toast.count(),0);await finish(false);
        assert.equal(await toast.count(),0);
        await start();assert.equal(await toast.count(),0);await finish(true);
        await page.waitForFunction(()=>document.querySelector('#wkb-toast.show'));
        assert.equal((await toast.innerText()).trim(),label);
        const shape=await toast.evaluate(el=>{const r=el.getBoundingClientRect(),s=getComputedStyle(el);return [r.x,r.y,r.width,r.height,s.color,s.backgroundColor,s.transitionDuration,s.opacity,s.visibility];});
        assert.equal(shape.at(-1),'visible');
        const stage=before?'before':'after', prefix=path.join(out,lang+'-'+width);
        if(base){
            await page.evaluate(()=>document.fonts.ready);
            const png=await page.screenshot({path:prefix+'-'+stage+'.png',animations:'disabled',clip:{x:0,y:0,width,height:180}});
            if(before)fs.writeFileSync(prefix+'.json',JSON.stringify(shape));
            else {
                assert.deepEqual(shape,JSON.parse(fs.readFileSync(prefix+'.json')));
                assert.ok(png.equals(fs.readFileSync(prefix+'-before.png')),'Toast pixels differ: '+lang+' '+width);
            }
        }
        if(!before){
            await page.clock.install();
            await start();await finish(true);
            await page.clock.runFor(1500);
            await start();await finish(true);
            await page.clock.runFor(1600);
            assert.equal(await page.locator('html.basket-prev-small-open #wkb-toast.show').count(),1);
            await page.clock.runFor(1500);
            assert.equal(await page.locator('#wkb-toast.show,html.basket-prev-small-open').count(),0);
        }
        assert.deepEqual(actions.slice(from),Array(before?2:4).fill('POST /rest/io/basket/items/'));
        if(!before){
            const beforeOpen=actions.length;
            await page.evaluate(()=>{
                document.body.insertAdjacentHTML('beforeend','<button id="peek" data-href="/quick-product">Quickview</button><div class="modal modal-cross"><div class="modal-dialog"><div class="modal-body"></div></div></div>');
                window.MagicZoom={start(){},stop(){}};
                O46Core.openQuickview(document.querySelector('#peek'),0);
            });
            await page.waitForFunction(()=>O46Core.quickview.phase==='shown');
            await start('#quick-buy');await finish(false);
            assert.equal(await page.locator('.modal-cross.show').count(),1);
            assert.equal(await page.locator('#wkb-toast.show').count(),0);
            await start('#quick-buy');await finish(true);
            await page.waitForFunction(()=>O46Core.quickview.phase==='hidden' && document.querySelector('#wkb-toast.show'));
            assert.deepEqual([...positions],['101','202']);
            assert.deepEqual(actions.slice(beforeOpen),['GET /quick-product','POST /rest/io/basket/items/','POST /rest/io/basket/items/']);
        }
        const final=await storage();
        for(const [k,v] of Object.entries(foreign))assert.equal(final.local[k],v);
        for(const k of oldKeys)assert.equal(final.local[k],initial.local[k]);
        assert.deepEqual(final.session,initial.session);
        assert.equal(final.cookie,initial.cookie);
        assert.ok(!JSON.stringify(final).includes('wkb-toast'));
        return {lang,width,bundle,stage,cartWrites:before?2:6,toastRequests:0,legacyKeys:oldKeys.filter(k=>k in final.local).length};
    } finally {await page.close();}
}
(async()=>{
    fs.mkdirSync(out,{recursive:true});
    const browser=await chromium.launch({executablePath:process.env.O46_BROWSER_EXECUTABLE});
    const result=[];
    try {
        for(const lang of langs) for(const width of [390,768,1440]){
            if(base)result.push(await run(browser,lang,width,true));
            result.push(await run(browser,lang,width,false));
        }
        fs.writeFileSync(path.join(out,'results.json'),JSON.stringify(result,null,2));
        console.log(result.length+' toast scenarios passed.');
    } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

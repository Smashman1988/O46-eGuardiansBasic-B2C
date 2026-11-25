// node tests/pdp_gallery_browser.cjs <rendered PDP HTML> <results.json>
const fs = require('node:fs');
const assert = require('node:assert/strict');
const {chromium} = require(process.env.O46_PLAYWRIGHT_MODULE || 'playwright');

async function run() {
    const render = file => fs.readFileSync(file, 'utf8')
        .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '').replace(/<link\b[^>]*>/gi, '');
    const html = render(process.argv[2]);
    const browser = await chromium.launch({executablePath:process.env.O46_BROWSER_EXECUTABLE});
    const results = [];
    const save = () => fs.writeFileSync(process.argv[3], JSON.stringify(results,null,2));
    try {
        const page = await browser.newPage({viewport:{width:390,height:850},hasTouch:true,isMobile:true,userAgent:'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36'});
        page.setDefaultTimeout(6000);
        let dimensions = [700,900];
        await page.route('**/*', route => {
            assert.equal(route.request().method(),'GET');
            if (route.request().resourceType()==='document') return route.fulfill({contentType:'text/html',body:html});
            if (route.request().resourceType()==='image') {
                const [w,h]=dimensions;
                return route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="'+w+'" height="'+h+'"><rect width="100%" height="100%" fill="white"/><circle cx="50%" cy="50%" r="12" fill="red"/></svg>'});
            }
            return route.fulfill({status:204,body:''});
        });
        await page.goto('https://fixture.invalid/product');
        await page.addStyleTag({path:'resources/css/main.min.css'});
        for (const [w,h] of [[700,900],[900,600],[800,800],[1600,320],[300,900]]) {
            dimensions=[w,h];
            const box=await page.locator('#ItemImgZoom > img').evaluate(async (img, n) => {
                img.src='/format-'+n+'.svg'; await img.decode();
                const frame=img.closest('.eg-gallery-frame').getBoundingClientRect();
                return [img.naturalWidth,img.naturalHeight,getComputedStyle(img).objectFit,Math.round(frame.height)];
            },w+'-'+h);
            assert.deepEqual(box.slice(0,3),[w,h,'contain']);
            results.push({format:[w,h],frameHeight:box[3]});
        }
        assert.equal(new Set(results.map(r=>r.frameHeight)).size,1);save();

        for (const width of [320,375,390,430,768,992,1280,1440,1920]) {
            await page.setViewportSize({width,height:850});
            const layout = await page.locator('article.single').evaluate(article => {
                const gallery = article.querySelector('.stickyarticle').getBoundingClientRect();
                const info = article.querySelector('[data-eg-loading-attr]').getBoundingClientRect();
                const button = article.querySelector('[data-item-tobasket]');
                const buy = button.getBoundingClientRect();
                return {
                    sideBySide: gallery.right <= info.left + 1,
                    stacked: gallery.bottom <= info.top + 1,
                    purchaseOutsidePrice: !button.closest('.eg-product-offer'),
                    inViewport: buy.left >= -1 && buy.right <= innerWidth + 1
                };
            });
            assert.ok(width >= 768 ? layout.sideBySide : layout.stacked, 'PDP grid at '+width);
            assert.ok(layout.purchaseOutsidePrice && layout.inViewport, 'Buy button at '+width);
            results.push({width,layout});
        }
        await page.setViewportSize({width:390,height:850});
        save();
        dimensions=[700,900];
        await page.reload();
        await page.addStyleTag({path:'resources/css/main.min.css'});
        await page.addScriptTag({path:'resources/js/jquery-3.7.1.min.js'});
        await page.addScriptTag({path:'resources/js/core.min.js'});
        await page.addScriptTag({path:'resources/js/item.min.js'});
        await page.addScriptTag({path:'resources/js/magiczoom.min.js'});
        const counter=page.locator('[data-eg-gallery-counter]');
        const count=async value=>page.waitForFunction(value=>document.querySelector('[data-eg-gallery-counter]').textContent===value,value);
        await count('1 / 3'); await page.locator('#ItemImgZoom .mz-figure').waitFor();
        await page.locator('#ItemImgZoom').scrollIntoViewIfNeeded();
        await page.waitForTimeout(600);
        const rect=await page.locator('#ItemImgZoom').boundingBox();
        const client=await page.context().newCDPSession(page);
        async function swipe(left) {
            const start=rect.x+rect.width*(left?0.8:0.2),y=rect.y+rect.height*0.48;
            await client.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x:start,y}]});
            for(let i=1;i<=8;i++) {
                await client.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x:start+(left?-1:1)*rect.width*0.57*i/8,y}]});
                await page.waitForTimeout(18);
            }
            await client.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
        }
        await swipe(true);await count('2 / 3');await page.waitForTimeout(350);
        await swipe(false);await count('1 / 3');results.push({swipe:'both directions'});save();
        await page.waitForTimeout(650); // Let the vendor finish the preceding swipe before the desktop click.
        await page.setViewportSize({width:1440,height:900});
        await page.locator('.single-prev-images a').nth(2).click();await count('3 / 3');
        await page.locator('.eg-gallery-expand').press('Enter');
        await page.locator('.mz-expand .mz-image-stage img').first().waitFor({state:'visible'});
        await page.evaluate(()=>MagicZoom.close('ItemImgZoom'));
        await page.locator('.mz-expand').waitFor({state:'hidden'});
        results.push({thumbnail:'3 / 3',zoom:'open and close'});save();
        await page.evaluate(()=>{
            window.activeLang='de';
            window.messageServiceTranslations={wishlistplus:'Added',wishlistminus:'Removed',searchexample:'Suche'};
            eGuardians.initialize();
            window.writes=[];
            eGuardians.item.watchList.add=(id,cb)=>{writes.push(id);window.reply=cb;};
            O46Core.basketHeader.refreshAfterWrite=()=>{};
        });
        const heart=page.locator('article.single button[data-eg-handler="watchListTrigger"]');
        await heart.click();
        assert.deepEqual(await page.evaluate(()=>writes),['1011']);
        await page.evaluate(()=>reply({data:{variationId:1011}},201));
        assert.equal(await heart.getAttribute('aria-pressed'),'true');
        results.push({wishlist:'Single variation without an attribute selector'}); save();
        const multi = render(require('node:path').join(require('node:path').dirname(process.argv[2]),'pdp-multi.html'));
        await page.route('**/multi-item/', route => route.fulfill({contentType:'text/html',body:multi}));
        await page.goto('https://shop.test/multi-item/');
        for (const file of ['jquery-3.7.1.min.js','core.min.js','item.min.js']) {
            await page.addScriptTag({path:'resources/js/'+file});
        }
        await page.evaluate(()=>{
            window.activeLang='fr';window.messageServiceTranslations={wishlistplus:'Added',wishlistminus:'Removed',searchexample:'Recherche'};
            eGuardians.initialize();window.writes=[];
            eGuardians.item.watchList.add=(id,cb)=>{writes.push(id);window.reply=cb;};
            O46Core.basketHeader.refreshAfterWrite=()=>{};
        });
        await heart.click();
        const picker=page.locator('#eg-wish-picker');
        await picker.waitFor({state:'visible'});
        await picker.locator('[data-wish-id="1012"]').click();
        await picker.locator('[data-wish-save]').click();
        assert.deepEqual(await page.evaluate(()=>writes),['1012']);
        await page.evaluate(()=>reply({error:true},409));
        assert.equal(await picker.isVisible(),true);
        await picker.locator('[data-wish-save]').click();
        await page.evaluate(()=>reply({data:{variationId:1012}},201));
        await picker.waitFor({state:'hidden'});
        assert.equal(await heart.getAttribute('aria-pressed'),'true');
        results.push({wishlist:'B2C variation selection, rejected write, retry and confirmation'});save();
        const dir = require('node:path').dirname(process.argv[2]);
        for (const [language, pathname] of [['de','/42_1012/'],['en','/en/42_1012/']]) {
            const linked = render(require('node:path').join(dir, 'pdp-linked-'+language+'.html'));
            await page.route(url => url.origin === 'https://store.example.test' && url.pathname.replace(/\/?$/,'/') === pathname, route => route.fulfill({contentType:'text/html',body:linked}));
        }
        async function mountItem(language) {
            for (const file of ['jquery-3.7.1.min.js','core.min.js','item.min.js']) {
                await page.addScriptTag({path:'resources/js/'+file});
            }
            await page.evaluate(lang=>{
                window.activeLang=lang;
                window.messageServiceTranslations={};
                eGuardians.initialize();
            },language);
        }
        await page.goto('https://store.example.test/42_1012/');
        await mountItem('de');
        const selected = page.locator('article.single [data-eg-selected-attribute]');
        assert.deepEqual(await selected.allTextContents(),['M','Grau']);
        await page.locator('[data-eg-language-switch]').click();
        const english = page.locator('.dynamicModal a[hreflang="en"]');
        const target = new URL(await english.getAttribute('href'));
        assert.equal(target.origin,'https://store.example.test');
        assert.equal(target.pathname.replace(/\/?$/,'/'),'/en/42_1012/');
        await english.click();
        await page.waitForURL(target.href);
        await mountItem('en');
        assert.deepEqual(await selected.allTextContents(),['M','Grey']);
        assert.equal(await page.locator('article.single [data-item-variation-select] [data-selected]').count(),2);
        await page.evaluate(()=>{
            window.basketWrites=[];
            eGuardians.apiservice.post=(url,data)=>window.basketWrites.push([url,data.variationId,data.quantity]);
        });
        await page.locator('article.single [data-item-tobasket]').click();
        assert.deepEqual(await page.evaluate(()=>window.basketWrites),[['/rest/io/basket/items/','1012',1]]);
        results.push({languageSwitch:'DE to EN with size and colour, correct variation passed to basket'});save();
        console.log(results.length+' local gallery, wishlist and product checks passed');
    } finally { await browser.close(); }
}
run().catch(error=>{console.error(error);process.exitCode=1;});

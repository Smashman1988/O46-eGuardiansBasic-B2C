// Check an actual mouse press and release against the shipped Bootstrap dialog.
const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require(process.env.O46_PLAYWRIGHT_MODULE || 'playwright');
const resources = path.join(__dirname, '../resources');

async function pressCloseDuringEntrance(page, touch, open) {
    // Arm the hit test before opening. Returning the point directly avoids an extra
    // remote handle lookup and a separate mouse-move round trip during the transition.
    await page.evaluate(() => {
        window.earlyQuickviewPoint = new Promise(resolve => {
            const start = performance.now();
            function sample() {
                const button = document.querySelector('.modal-cross button.close');
                const box = button?.getBoundingClientRect();
                if (box?.width && O46Core.quickview?.phase === 'opening') {
                    const x = box.left + box.width / 2;
                    const y = Math.max(0, box.top) + 5;
                    if (box.bottom > y && button.contains(document.elementFromPoint(x,y))) {
                        resolve({x,y});
                        return;
                    }
                }
                if (performance.now() - start > 4500) resolve(null);
                else requestAnimationFrame(sample);
            }
            sample();
        });
    });
    const pointer = page.evaluate(() => window.earlyQuickviewPoint).then(async point => {
        assert.ok(point,'Close target never hittable during opening');
        if (touch) await page.touchscreen.tap(point.x,point.y);
        else await page.mouse.click(point.x,point.y,{delay:160});
        return point.y;
    });
    const [y] = await Promise.all([pointer,open()]);
    return y;
}

async function main() {
    const browser = await chromium.launch({ executablePath: process.env.O46_BROWSER_EXECUTABLE });
    try {
        const page = await browser.newPage({ viewport: { width: 1024, height: 840 } });
        const html = '<!doctype html><button id="opener" data-href="/item">Product</button>' +
            '<div class="modal fade modal-cross" tabindex="-1" role="dialog"><div class="modal-dialog"><div class="modal-content">' +
            '<div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>×</span></button></div>' +
            '<div class="modal-body"></div></div></div></div>';
        await page.route('**/*', route => route.request().url() === 'https://example.invalid/'
            ? route.fulfill({ contentType: 'text/html', body: html })
            : route.abort());
        await page.goto('https://example.invalid/');
        await page.addStyleTag({ path: path.join(resources, 'css/main.min.css') });
        await page.addScriptTag({ path: path.join(resources, 'js/jquery-3.7.1.min.js') });
        await page.addScriptTag({ path: path.join(resources, 'js/core.min.js') });
        await page.addScriptTag({ path: path.join(resources, 'js/cat.min.js') });
        await page.evaluate(() => {
            window.MagicZoom = { start() {} };
            O46Core.ensureMagicZoom = () => Promise.resolve();
            eGuardians.apiservice.get = (url, params, done) => done('<div style="height:640px">Product details</div>');
            const opener = document.querySelector('#opener');
            opener.addEventListener('click', () => O46Core.openQuickview(opener, 0));
            window.closeTargets = [];
            for (const type of ['pointerdown', 'pointerup']) {
                document.addEventListener(type, event => {
                    if (event.target !== opener) closeTargets.push([type, !!event.target.closest('button.close')]);
                }, true);
            }
        });
        for (const width of [1024, 390]) {
            await page.setViewportSize({ width, height: 840 });
            const y = await pressCloseDuringEntrance(page,false,()=>page.locator('#opener').press('Enter'));
            const targets = await page.evaluate(() => closeTargets.splice(0));
            assert.deepEqual(targets, [['pointerdown', true], ['pointerup', true]], width + 'px close target at ' + y);
            await page.locator('.modal-cross').waitFor({ state: 'hidden', timeout: 2000 });
            await page.locator('.modal-backdrop').waitFor({ state: 'detached' });
            assert.equal(await page.locator('#opener').evaluate(el => document.activeElement === el), true);
        }
        await page.evaluate(()=>{
            eGuardians.apiservice.get=(url,params,done)=>done('<article data-item data-eg-active-variation="123"><button class="buy">Buy</button></article>');
            window.messageServiceTranslations={0:'Error'};
            window.postReplies=[];
            eGuardians.apiservice.post=(url,data,reply)=>{ if(url!=='/rest/io/basket/items/'||data.variationId!=='123') throw Error('Invalid basket request'); postReplies.push(reply); };
            O46Core.basketHeader.accept=()=>{};
            O46Core.basketHeader.refreshAfterWrite=()=>{};
            eGuardians.ui.message.error.show=()=>{};
            eGuardians.helper.btnShowLoader=eGuardians.helper.btnHideLoader=()=>{};
            window.toasts=0;
            eGuardians.basket.openBasketPreviewSmall=()=>{window.toasts++;};
        });
        const open=async function(url) {
            await page.locator('#opener').evaluate((el,url)=>{el.dataset.href=url;$(el).data('href',url);el.click();},url);
            await page.waitForFunction(()=>O46Core.quickview.phase==='shown');
        };
        const buy=()=>page.locator('.modal-cross .buy').evaluate(el=>eGuardians.events.addItemToBasket(el));
        const reply=(success)=>page.evaluate(ok=>postReplies.shift()({data:ok},ok?201:409),success);
        await open('/product-a');
        await buy(); await reply(false);
        assert.equal(await page.evaluate(()=>O46Core.quickview.phase),'shown');
        await buy();
        await page.locator('.modal-cross .close').click();
        await page.waitForFunction(()=>O46Core.quickview.phase==='hidden');
        await open('/product-b');
        await reply(true);
        assert.equal(await page.evaluate(()=>O46Core.quickview.phase),'shown');
        await buy(); await reply(true);
        await page.waitForFunction(()=>O46Core.quickview.phase==='hidden');
        assert.equal(await page.evaluate(()=>window.toasts),1);
        console.log('2 early pointer closes and 3 confirmed/failed/stale basket response cases passed');
    } finally {
        await browser.close();
    }
}

module.exports = {pressCloseDuringEntrance};
if (require.main === module) main().catch(error => { console.error(error); process.exitCode = 1; });

// Real Twig card HTML and the shipped browser bundle. All requests stay in this fixture.
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const assert = require('node:assert/strict');
const {chromium, expect} = require('@playwright/test');
const out = path.resolve('.cache/wishlist-cache');
fs.mkdirSync(out,{recursive:true});
const php = process.env.O46_PHP || 'php';
const twig = process.env.O46_TWIG_AUTOLOAD || '.cache/contact-widget/autoload3.php';
const phpArgs = process.env.O46_PHP_EXT ? ['-d','extension_dir='+process.env.O46_PHP_EXT,'-d','extension=mbstring'] : [];
execFileSync(php,[...phpArgs,'tests/card_attributes.php',twig,path.join(out,'cards.json')],{
    env:{...process.env,O46_WISH_CACHE_FIXTURES:out},stdio:['ignore','pipe','inherit']});
const retail = require('../package.json').name.includes('b2c');
const api = '/rest/' + (retail ? 'guardiansbasic' : 'guardiansbasicb2b') + '/wishlist';
const controls = retail ? fs.readFileSync(path.join(out,'controls.html'),'utf8') : '';
const sizes = [12,24,66,100,500];
const results = [];
(async () => {
    const browser = await chromium.launch({executablePath:process.env.O46_BROWSER_EXECUTABLE});
    try {
        for (const size of sizes) {
            const grid = fs.readFileSync(path.join(out,'cards-'+size+'.html'),'utf8');
            assert.doesNotMatch(grid,/data-saved=|item-in-wishlist|itemWishListIds/);
            const html = '<!doctype html><html lang="de"><head><meta charset="UTF-8"><link rel="stylesheet" href="/assets/main.min.css"></head><body data-eg-wish-api="'+api+'" data-eg-wish-shop="0">' +
                grid + controls + '<script src="/assets/jquery-3.7.1.min.js"></script><script src="/assets/core.min.js"></script>' +
                '<script src="/assets/cat.min.js"></script><script>O46Core.basketHeader.start()</script></body></html>';
            for (const saved of [[],[1000,1030,1090],Array.from({length:5000},(_,i)=>1000+i)]) {
                const page = await browser.newPage();
                const logs = [], errors = [];
                page.on('pageerror',e=>errors.push(e.message));
                let release;
                const wait = new Promise(resolve => {release=resolve;});
                await page.route('**/*',async route=>{
                    const url = new URL(route.request().url());
                    if (url.hostname !== 'shop.invalid') return route.fulfill({status:204});
                    if (url.pathname==='/category/') return route.fulfill({contentType:'text/html',body:html});
                    if (url.pathname.startsWith('/assets/')) {
                        const file = path.basename(url.pathname);
                        return route.fulfill({path:path.resolve('resources',file.endsWith('.css')?'css':'js',file)});
                    }
                    if (url.pathname.startsWith('/rest/')) {
                        logs.push(url.pathname);
                        if (url.pathname===api+'/basket/') {
                            await wait;
                            return route.fulfill({json:{data:{currency:'EUR',shippingCountryId:1,itemSum:0,itemQuantity:0,
                                sessionId:'test-session',customerId:saved.length?17:0,itemWishListIds:saved,
                                wishListOwner:{guest:!saved.length,shop:0,plenty:17,scope:'test-scope'}}}});
                        }
                        throw Error('Unexpected private request: '+url.pathname);
                    }
                    return route.fulfill({status:204});
                });
                try {
                    await page.goto('https://shop.invalid/category/',{waitUntil:'domcontentloaded'});
                    const cards=page.locator('.cat-item[data-eg-item]');
                    await expect(cards).toHaveCount(size);
                    const hearts=cards.locator('.eg-wish-heart');
                    await expect(hearts).toHaveCount(retail ? size : 0);
                    if (retail) {
                        await expect(hearts.first()).not.toHaveAttribute('aria-pressed',/./);
                        await expect(hearts.first().locator('svg')).toHaveCSS('visibility','hidden');
                    }
                    release();
                    await page.waitForFunction(()=>Array.isArray(window.egSessionData?.basket?.itemWishListIds));
                    const expected=new Set(saved);
                    const marked=Array.from({length:size},(_,i)=>expected.has((100+i)*10) || expected.has((100+i)*10+1));
                    if (retail) assert.deepEqual(await hearts.evaluateAll(nodes=>nodes.map(n=>n.getAttribute('aria-pressed')==='true')),marked);
                    await cards.first().evaluate((first,grid)=>{
                        const clone = new DOMParser().parseFromString(grid,'text/html').querySelector('.cat-item[data-eg-item]');
                        clone.setAttribute('data-cache-test-append','');
                        first.parentElement.append(clone);
                    },grid);
                    await expect(cards).toHaveCount(size+1);
                    if (retail) await expect(page.locator('[data-cache-test-append] .eg-wish-heart')).toHaveAttribute('aria-pressed',String(marked[0]));
                    await cards.first().hover();
                    await page.evaluate(()=>new Promise(requestAnimationFrame));
                    assert.deepEqual(logs,[api+'/basket/']);
                    assert.deepEqual(errors,[]);
                    assert.equal((await page.context().cookies()).length,0);
                    results.push({cards:size,saved:saved.length,hearts:retail?size:0,centralReads:logs.length,wishlistRequests:0,
                        sha256:require('node:crypto').createHash('sha256').update(grid).digest('hex')});
                } finally {release();await page.close();}
            }
        }
        fs.writeFileSync(path.join(out,'network.json'),JSON.stringify(results,null,2));
        console.log(results.length+' visitor/card combinations passed, one central read per cold page, zero wishlist requests.');
    } finally {await browser.close();}
})().catch(err=>{console.error(err);process.exitCode=1;});

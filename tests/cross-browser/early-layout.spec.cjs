const {test, expect} = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');

// Inspect the first paint before the deferred main stylesheet becomes available.
const head = fs.readFileSync(path.join(__dirname,'../../resources/views/PageDesign/Partials/Head.twig'),'utf8');
const branches = [...head.matchAll(/<style>([\s\S]*?)<\/style>/g)].slice(0,3).map(match=>match[1]);

test('Frühe Menü-, Filter- und Warenkorbgeometrie in den drei Head-Zweigen', async ({page}) => {
    expect(branches).toHaveLength(3);
    await page.route('**/*',route=>route.abort());
    for (const [index,css] of branches.entries()) {
        await page.setViewportSize({width:390,height:844});
        await page.setContent('<meta name="viewport" content="width=device-width,initial-scale=1"><style>'+css+'</style>'+
            '<div class="top-menu-wrapper"></div><div class="wrap-filter-mobile"></div>'+
            '<div class="row" style="width:960px;margin:0"><div class="col-lg-7" id="basket-column"></div></div>');
        const right = await page.locator('.top-menu-wrapper').evaluate(el=>el.getBoundingClientRect().right);
        expect(right,'closed menu in branch '+index).toBeLessThanOrEqual(1);
        if (index !== 0) {
            const left = await page.locator('.wrap-filter-mobile').evaluate(el=>getComputedStyle(el).left);
            expect(['100%','390px']).toContain(left);
        }
    }
    await page.setViewportSize({width:1440,height:900});
    await expect.poll(()=>page.locator('#basket-column').evaluate(el=>Math.round(el.getBoundingClientRect().width))).toBe(560);
});

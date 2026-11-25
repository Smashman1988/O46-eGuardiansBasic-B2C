const {test, expect} = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');

// Inspect the first paint before the deferred main stylesheet becomes available.
const head = fs.readFileSync(path.join(__dirname,'../../resources/views/PageDesign/Partials/Head.twig'),'utf8');
const branches = [...head.matchAll(/<style>([\s\S]*?)<\/style>/g)].slice(0,3).map(match=>match[1]);

test('Startseite und Warenkorb behalten ihre frühen responsiven Spaltenbreiten', async ({page}) => {
    const critical = [...head.matchAll(/<style data-eg-(?:home|basket)-layout-critical>([\s\S]*?)<\/style>/g)];
    expect(critical).toHaveLength(2);
    const main = fs.readFileSync(path.join(__dirname,'../../resources/css/main.min.css'),'utf8');
    await page.route('**/*', route=>route.abort());
    for (const width of [390,768,1440]) {
        await page.setViewportSize({width,height:900});
        await page.setContent('<meta name="viewport" content="width=device-width,initial-scale=1">'+
            '<style>'+branches[2]+critical.map(m=>m[1]).join('\n')+'</style>'+
            '<main id="page-body"><div class="container-max"><div class="row"><div class="col"><div id="content"></div></div></div></div>'+
            '<div class="row widget-grid widget-two-col" style="margin:0"><div class="widget-inner col-12 col-md-6 col-lg-7 col-xl-7 widget-stacked-mobile"></div>'+
            '<div class="widget-inner col-12 col-md-6 col-lg-5 col-xl-5"></div></div></main>');
        const geometry = () => page.evaluate(() => {
            const columns = Array.from(document.querySelectorAll('.widget-inner'));
            return {content:document.querySelector('#content').clientWidth, columns:columns.map(el=>el.clientWidth),
                padding: getComputedStyle(columns[0]).paddingBottom};
        });
        const early = await geometry();
        expect(early.columns).toEqual(width===390?[390,390]:width===768?[384,384]:[840,600]);
        await page.addStyleTag({content:main});
        expect(await geometry()).toEqual(early);
    }
});

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

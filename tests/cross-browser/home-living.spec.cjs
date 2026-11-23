const {test, expect} = require('./storefront.cjs');
const routes = require('../fixtures/home-living.json');

test('Home & Living opens its verified B2C destinations in all five languages', async ({page, shop, hasTouch}) => {
    for (const [lang, categories] of Object.entries(routes)) {
        await shop.open((lang === 'de' ? '' : '/' + lang) + '/category/');
        const mobile = await page.evaluate(() => matchMedia('(max-width: 991.98px)').matches);
        if (mobile) await page.locator('[aria-controls="o46-menu"]:visible').first().click();
        const entry = page.locator('.top-menu li.cat-4103').first();
        const main = entry.locator(':scope > a');
        await expect(main).toHaveAttribute('href', categories['4103']);
        if (mobile) {
            const opener = entry.locator('[data-eg-open-ddown]');
            await opener.scrollIntoViewIfNeeded();
            if (hasTouch) await opener.tap(); else await opener.click();
            await expect(entry).toHaveClass(/ddown-open/);
        } else {
            await main.hover();
        }
        const links = entry.locator('.level2 a[href^="/"]');
        await expect(links).toHaveCount(8);
        for (const item of await links.all()) {
            await item.scrollIntoViewIfNeeded();
            await expect(item).toBeInViewport();
            const box = await item.boundingBox();
            expect(box.x).toBeGreaterThanOrEqual(-1);
            expect(box.x + box.width).toBeLessThanOrEqual(await page.evaluate(() => innerWidth + 1));
        }
    }
});

const {test, expect} = require('./storefront.cjs');

async function basketState(page, amount, currency, quantity) {
    return page.locator('#page-header').evaluate((header, values) => {
        const sum = header.querySelector('[data-basket-sum]');
        const unit = sum.nextElementSibling;
        const link = sum.closest('a');
        const badge = link.querySelector('[data-eg-basket-quantity]');
        sum.textContent = values.amount;
        unit.textContent = values.currency;
        badge.textContent = String(values.quantity);
        badge.className = values.quantity < 10 ? 'header-quant-info' : 'header-quant-info2';
        function bounds(el, text = false) {
            const target = text ? document.createRange() : el;
            if (text) target.selectNodeContents(el);
            return target.getBoundingClientRect().toJSON();
        }
        return {sum: bounds(sum, true), unit: bounds(unit, true), badge: bounds(badge, true),
            icon: bounds(link.querySelector('.icon-online-shopping-cart')), logo: bounds(header.querySelector('.o-logo')),
            height: header.clientHeight, account: bounds(header.querySelector('.btn-login-open .mobile-btn-txt'), true)};
    }, {amount, currency, quantity});
}

test('Warenkorbsymbol, Menge und Betrag folgen dem ursprünglichen Desktop-Layout', async ({shop, page}, info) => {
    await page.setViewportSize({width:1440, height:900});
    await shop.open('/alle-artikel/');
    await page.evaluate(() => document.fonts.ready);
    for (const [amount, unit, quantity] of [['0,00','€',0], ['11,98','€',2], ['224,85','CHF',15], ['999999,99','HK$',105]]) {
        const r = await basketState(page, amount, unit, quantity);
        // The original label has 10 px leading padding and follows its actual text width.
        const center = r.icon.x + r.icon.width / 2;
        expect(r.icon.width).toBe(35);
        expect(Math.abs((r.sum.left + r.unit.right) / 2 - center - 5)).toBeLessThan(1);
        expect(Math.abs((r.badge.left + r.badge.right) / 2 - center - 2.5)).toBeLessThan(1);
        expect(r.badge.top).toBeGreaterThan(r.icon.top);
        expect(r.badge.bottom).toBeLessThan(r.icon.bottom);
        expect(Math.abs(r.sum.top - r.unit.top)).toBeLessThan(1);
        expect(r.unit.left - r.sum.right).toBeGreaterThanOrEqual(1);
        expect(r.unit.left - r.sum.right).toBeLessThanOrEqual(2);
        expect(r.unit.right).toBeLessThanOrEqual(1440);
    }
    const header = page.locator('#page-header');
    if (process.platform === 'win32' && info.project.name === 'chrome') {
        await expect(header).toHaveScreenshot('warenkorb-desktop.png', {maxDiffPixels:0});
    }
    await info.attach('Warenkorbheader', {body:await header.screenshot(), contentType:'image/png'});
});

test('Schmale Kopfzeile erhält das Logo und zeigt vollständige Beträge und Währungen', async ({shop, page}, info) => {
    await shop.open('/alle-artikel/');
    await page.evaluate(() => document.fonts.ready);
    for (const width of [320,360,375,390,414,430,575,576,767,768,992]) {
        await page.setViewportSize({width,height:844});
        const initial = await basketState(page, '0,00', '€', 0);
        for (const amount of ['11,98', '179,88', '1573,95']) {
            const current = await basketState(page, amount, '€', 12);
            if (amount !== '1573,95') expect(current.unit.top).toBe(current.sum.top);
            expect(current.sum.left).toBeGreaterThanOrEqual(current.account.right);
            expect(current.unit.right).toBeLessThanOrEqual(width);
            expect(current.logo).toEqual(initial.logo);
        }
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        const r = await basketState(page, '999999,99', 'AED', 105);
        expect(r.height).toBe(initial.height);
        expect(r.logo.width).toBe(initial.logo.width);
        expect(r.logo.height).toBe(initial.logo.height);
        expect(r.logo.left).toBe(initial.logo.left);
        expect(r.sum.right).toBeLessThanOrEqual(width + .05);
        expect(r.unit.right).toBeLessThanOrEqual(width + .05);
        expect(r.sum.left).toBeGreaterThanOrEqual(r.account.right - .05);
        expect(r.unit.left).toBeGreaterThanOrEqual(r.account.right - .05);
        expect(r.unit.bottom).toBeGreaterThanOrEqual(r.sum.bottom - 1);
        expect(r.badge.left).toBeGreaterThan(r.icon.left);
        expect(r.badge.right).toBeLessThan(r.icon.right);
        if (width === 320) {
            const header = page.locator('#page-header');
            if (process.platform === 'win32' && info.project.name === 'chrome') {
                await expect(header).toHaveScreenshot('warenkorb-mobil-aed.png', {maxDiffPixels:0});
            }
            await info.attach('Warenkorbheader-AED-320',{body:await header.screenshot(),contentType:'image/png'});
        }
    }
});

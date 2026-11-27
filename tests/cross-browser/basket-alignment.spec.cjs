const {test, expect} = require('./storefront.cjs');

async function basketStates(page, cases) {
    // Compare the amounts within the same layout frame, including after a responsive resize.
    return page.locator('#page-header').evaluate((header, cases) => {
        const sum = header.querySelector('[data-basket-sum]');
        const unit = sum.nextElementSibling;
        const link = sum.closest('a');
        const badge = link.querySelector('[data-eg-basket-quantity]');
        function bounds(el, text = false) {
            const target = text ? document.createRange() : el;
            if (text) target.selectNodeContents(el);
            return target.getBoundingClientRect().toJSON();
        }
        return cases.map(([amount, currency, quantity]) => {
            sum.textContent = amount;
            unit.textContent = currency;
            badge.textContent = String(quantity);
            badge.className = quantity < 10 ? 'header-quant-info' : 'header-quant-info2';
            return {sum: bounds(sum, true), unit: bounds(unit, true), badge: bounds(badge, true),
                icon: bounds(link.querySelector('.icon-online-shopping-cart')), logo: bounds(header.querySelector('.o-logo')),
                height: header.clientHeight, account: bounds(header.querySelector('.btn-login-open .mobile-btn-txt'), true)};
        });
    }, cases);
}

test('Warenkorbsymbol, Menge und Betrag folgen dem ursprünglichen Desktop-Layout', async ({shop, page}, info) => {
    await page.setViewportSize({width:1440, height:900});
    await shop.open('/alle-artikel/');
    await page.evaluate(() => document.fonts.ready);
    for (const r of await basketStates(page, [['0,00','€',0], ['11,98','€',2], ['224,85','CHF',15], ['999999,99','HK$',105]])) {
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
        // The breakpoint may activate a different font face after the first resize frame.
        await page.evaluate(async () => {
            await new Promise(requestAnimationFrame);
            await document.fonts.ready;
            await new Promise(requestAnimationFrame);
        });
        const [initial, small, medium, large, r] = await basketStates(page, [
            ['0,00','€',0], ['11,98','€',12], ['179,88','€',12], ['1573,95','€',12], ['999999,99','AED',105],
        ]);
        for (const current of [small, medium, large]) {
            if (current !== large) expect(current.unit.top).toBe(current.sum.top);
            expect(current.sum.left).toBeGreaterThanOrEqual(current.account.right);
            expect(current.unit.right).toBeLessThanOrEqual(width);
            expect(current.logo).toEqual(initial.logo);
        }
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
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

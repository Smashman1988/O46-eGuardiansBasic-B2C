const {test, expect} = require('./storefront.cjs');

test('Betrag und Währung stehen zusammen mittig unter dem sichtbaren Warenkorbsymbol', async ({shop, page}, info) => {
    await page.setViewportSize({width: 1440, height: 900});
    await shop.open('/alle-artikel/');
    const header = page.locator('#page-header');
    const number = header.locator('[data-basket-sum]');
    const unit = header.locator('[data-currency-symbol]').last();
    const icon = number.locator('xpath=ancestor::a').locator('.icon-online-shopping-cart');
    await page.evaluate(() => document.fonts.ready);
    for (const [amount, symbol] of [['0,00', '€'], ['11,98', '€'], ['224,85', 'CHF'], ['999999,99', 'HK$']]) {
        await number.evaluate((el, text) => { el.textContent = text; }, amount);
        await unit.evaluate((el, text) => { el.textContent = text; }, symbol);
        const ink = locator => locator.evaluate(el => {
            const range = document.createRange();
            range.selectNodeContents(el);
            const {left, right, top} = range.getBoundingClientRect();
            return {left, right, top};
        });
        const [value, currency, cart] = await Promise.all([ink(number), ink(unit), icon.boundingBox()]);
        expect(cart).toBeTruthy();
        expect(Math.abs((value.left + currency.right) / 2 - (cart.x + cart.width / 2)), amount + ' ' + symbol).toBeLessThanOrEqual(1.5);
        expect(currency.left - value.right).toBeGreaterThanOrEqual(1);
        expect(currency.left - value.right).toBeLessThanOrEqual(5);
        expect(Math.abs(currency.top - value.top)).toBeLessThanOrEqual(1);
        expect(currency.right).toBeLessThanOrEqual(1440);
    }
    // Reference images were visually checked with the Windows Chrome profile.
    if (process.platform === 'win32' && info.project.name === 'chrome') {
        await expect(header).toHaveScreenshot('warenkorb-desktop.png', {maxDiffPixels: 0});
    }
    await info.attach('Warenkorbheader', {body: await header.screenshot(), contentType: 'image/png'});
});

test('Langer Betrag bleibt auf schmalen Smartphones vollständig im sichtbaren Header', async ({shop, page}, info) => {
    await shop.open('/alle-artikel/');
    const header = page.locator('#page-header');
    const amount = header.locator('[data-basket-sum]');
    const unit = amount.locator('xpath=following-sibling::*[1]');
    await page.evaluate(() => document.fonts.ready);
    await amount.evaluate(el => { el.textContent = '999999,99'; });
    await unit.evaluate(el => { el.textContent = 'AED'; });
    for (const width of [320, 360, 375, 390, 414, 430, 575, 576, 767, 768, 992]) {
        await page.setViewportSize({width, height: 844});
        const result = await amount.evaluate(el => {
            const range = document.createRange();
            range.setStartBefore(el.firstChild);
            range.setEndAfter(el.nextElementSibling.lastChild);
            const box = range.getBoundingClientRect();
            const icon = el.closest('a').querySelector('.icon-online-shopping-cart').getBoundingClientRect();
            return {right: box.right, offset: (box.left + box.right - icon.left - icon.right) / 2};
        });
        expect(result.right, 'Währung bei ' + width + ' px').toBeLessThanOrEqual(width);
        expect(Math.abs(result.offset), 'Zentrierung bei ' + width + ' px').toBeLessThanOrEqual(1.5);
        if (width === 320) {
            if (process.platform === 'win32' && info.project.name === 'chrome') {
                await expect(header).toHaveScreenshot('warenkorb-mobil-aed.png', {maxDiffPixels: 0});
            }
            await info.attach('Warenkorbheader-AED-320', {body: await header.screenshot(), contentType: 'image/png'});
        }
    }
});

const { test, expect } = require('./storefront.cjs');

// Pixel references use Windows Chrome with fixed fonts and a fixed viewport.
// The other profiles exercise the same controls with their own rendering engine.
async function capture(page, target, name, info) {
    await expect(target).toBeVisible();
    await page.evaluate(() => document.fonts.ready);
    await target.evaluate(async element => {
        const animations = element.getAnimations({subtree:true}).filter(a =>
            a.effect && a.effect.getTiming().iterations !== Infinity);
        await Promise.all(animations.map(a => a.finished.catch(() => {})));
    });
    if (process.platform === 'win32' && info.project.name === 'chrome') {
        await expect(target).toHaveScreenshot(name + '.png', {animations:'disabled', maxDiffPixels:0});
    } else {
        const box = await target.boundingBox();
        expect(box.width).toBeGreaterThan(20);
        expect(box.height).toBeGreaterThan(18);
    }
}

test('Produktkarte und geöffnete Schnellansicht', async ({shop, page}, info) => {
    await page.setViewportSize({width:1024, height:850});
    await shop.open('/alle-artikel/');
    const card = page.locator('.cat-item').first();
    await card.scrollIntoViewIfNeeded();
    await expect.poll(() => card.locator('picture img').evaluate(img => img.naturalWidth)).toBeGreaterThan(0);
    await capture(page, card, 'produktkarte', info);
    await card.locator('.linkabso').click();
    const dialog = page.locator('.modal-cross .modal-content');
    await expect(dialog.locator('h1')).toContainText('10001');
    await capture(page, dialog, 'quickview', info);
});

for (const [name, label, id] of [['navigation','Menü öffnen','o46-menu'], ['filter','Filter öffnen','o46-filter']]) {
    test('Mobiler Bereich ' + name, async ({shop, page}, info) => {
        await page.setViewportSize({width:390, height:844});
        await shop.open('/category/');
        await page.getByRole('button', {name:label, exact:true}).click();
        const panel = page.locator('#' + id);
        await expect(panel.getByRole('button', {name:'Schließen'}).first()).toBeVisible();
        await capture(page, panel, name, info);
    });
}

for (const [name, attribute, content] of [['sprache','data-eg-language-switch','a'], ['waehrung','data-eg-currency','select']]) {
    test('Geöffneter Dialog ' + name, async ({shop, page}, info) => {
        await page.setViewportSize({width:390, height:844});
        await shop.open('/alle-artikel/');
        await page.locator('button[' + attribute + ']').click();
        const dialog = page.locator('.dynamicModal .modal-content');
        await expect(dialog.locator('.modal-body ' + content).first()).toBeVisible();
        if (name === 'waehrung') await expect(dialog.locator('option')).toHaveCount(13);
        else await expect(dialog.locator('.eg-language-choice')).toHaveCount(5);
        await capture(page, dialog, name, info);
    });
}

test('Galerie mit geladenem Hauptbild', async ({shop, page}, info) => {
    await page.setViewportSize({width:1024,height:850});
    await shop.open('/product-10001/');
    await expect(page.locator('#ItemImgZoom .mz-figure')).toBeVisible();
    await capture(page, page.locator('.eg-gallery-frame').first(), 'galerie', info);
});

test('Newsletter nach Erreichen der Scrollschwelle', async ({shop, page}, info) => {
    await page.setViewportSize({width:390,height:844});
    await shop.open('/alle-artikel/?long-page=1&newsletter=1');
    await page.evaluate(() => window.scrollTo(0, 2100));
    await capture(page, page.locator('#o46-newsletter'), 'newsletter', info);
});


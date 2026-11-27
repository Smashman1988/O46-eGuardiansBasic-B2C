const {test, expect, origin} = require('./storefront.cjs');

async function session(context, initial, customerId = 0) {
    const ids = new Set(initial);
    const writes = [];
    let reply, reads = 0;
    await context.route('**/rest/io/basket/', route => {
        reads++;
        return route.fulfill({json:{data:{itemQuantity:0, itemSum:0, currency:'EUR', shippingCountryId:1,
            sessionId:'wish-fixture-' + customerId, customerId, itemWishListIds:[...ids]}}});
    });
    await context.route('**/rest/io/itemWishList/**', async route => {
        const request = route.request();
        const remove = request.method() === 'DELETE';
        const id = remove ? Number(new URL(request.url()).pathname.split('/').pop()) :
            Number(new URLSearchParams(request.postData()).get('variationId'));
        writes.push([request.method(),id]);
        const ok = await new Promise(resolve => { reply = resolve; });
        if (ok && remove) ids.delete(id);
        else if (ok) ids.add(id);
        return route.fulfill({status: ok && !remove ? 201 : 200,
            json:{data:ok ? (remove || {variationId:id}) : false}});
    });
    return {ids, writes, get reads() { return reads; }, confirm(ok) { reply(ok); }};
}
const card = (page, id) => page.locator('.cat-item[data-eg-item="' + id + '"]').first();
const heart = product => product.locator('.eg-wish-heart');
async function state(button, pressed) {
    await expect(button).toHaveAttribute('aria-pressed', String(pressed));
}
async function paint(button, filled) {
    const colour = filled ? 'rgb(222, 68, 58)' : 'rgb(96, 155, 0)';
    await expect(button.locator('path')).toHaveCSS('stroke', colour);
    await expect(button.locator('path')).toHaveCSS('fill', filled ? colour : 'none');
}
async function pickerChoice(page, id) {
    await page.locator('[data-wish-id="' + id + '"]:visible').click();
    await page.locator('[data-wish-save]:visible').click();
}

test('Größen, Farben und Packungsgrößen zeigen nur verständliche und kaufbare Optionen', async ({context,page,shop}) => {
    const io = await session(context, []);
    await shop.open();
    const host = page.locator('[data-picker-test]');
    const modal = page.locator('#eg-wish-picker');
    async function addFixture(rows, names) {
        await page.evaluate(({rows,names})=>{
            document.querySelector('[data-picker-test]')?.remove();
            const node = document.createElement('div');
            node.setAttribute('data-picker-test','');
            node.dataset.wishProduct = JSON.stringify({rows,names});
            document.body.prepend(node);
        }, {rows,names});
        await host.locator('button').click();
    }
    const sizes = ['L','2XL','S/M','M','XS','S','XL','42 2/3','42.5','42','42 1/3'];
    const names = {1:{name:'Größe',values:Object.fromEntries(sizes.map((name,i)=>[i,name]))}};
    await addFixture(Object.fromEntries(sizes.map((name,i)=>[1449867+i,[true,{1:i},'1 Stück']])), names);
    await expect(modal.locator('legend')).toHaveText(['Größe']);
    await expect(modal.locator('[data-wish-id]')).toHaveText(['XS','S','S/M','M','L','XL','2XL','42','42 1/3','42.5','42 2/3']);
    await expect(modal.locator('[data-wish-id="1449867"]')).toHaveAccessibleName('L');
    await modal.locator('.close').click();
    await expect(modal).toBeHidden();
    await addFixture({901:[true,{1:1,2:1},'1 Stück'],902:[false,{1:1,2:2},'1 Stück'],
        903:[true,{1:2,2:2},'1 Stück'],904:[true,{1:2,2:2},'6 Stück']},
        {1:{name:'Farbe',values:{1:'Rot',2:'Blau'}},2:{name:'Größe',values:{1:'S',2:'L'}}});
    await modal.getByRole('button',{name:'Rot',exact:true}).click();
    await expect(modal.getByRole('button',{name:'L',exact:true})).toHaveCount(0);
    await modal.getByRole('button',{name:'Blau',exact:true}).click();
    await modal.getByRole('button',{name:'L',exact:true}).click();
    await expect(modal.locator('legend')).toHaveText(['Farbe','Größe','Packungsgröße']);
    await expect(modal.locator('[data-wish-id]')).toHaveText(['1 Stück','6 Stück']);
    await pickerChoice(page,904);
    await expect.poll(()=>io.writes).toEqual([['POST',904]]);
    io.confirm(true);
    await state(host.locator('button'),true);
});

test('Merklistenherz mit bestätigten Antworten, Hover, Touch und Wiederholung', async ({context,page,shop,hasTouch},info) => {
    const io = await session(context, []);
    await shop.open();
    const button = heart(card(page,10002));
    await state(button,false);
    await paint(button,false);
    const capture = async name => {
        if (info.project.name === 'chrome') await card(page,10002).screenshot({path:info.outputPath(name+'.png')});
    };
    await capture('01-kontur-gruen');
    const reads = io.reads;
    expect(reads).toBe(1);
    if (!hasTouch) {
        await button.hover(); await paint(button,true); await capture('02-hover-rot');
        await page.mouse.move(0,0); await paint(button,false);
        expect(io.writes).toEqual([]);
    }
    const press = () => hasTouch ? button.tap() : button.press('Enter');
    await press();
    await expect.poll(() => io.writes.length).toBe(1);
    if (hasTouch) {
        const box = await button.boundingBox();
        await page.touchscreen.tap(box.x + box.width/2, box.y + box.height/2);
    } else await press();
    await state(button,false);
    expect(io.writes).toEqual([['POST',110002]]);
    io.confirm(false);
    await expect(card(page,10002).getByRole('status')).toContainText(/Merkliste/);
    await state(button,false);
    expect(io.reads).toBe(reads);
    await press();
    await expect.poll(() => io.writes.length).toBe(2);
    io.confirm(true);
    await state(button,true);
    await paint(button,true);
    await capture('03-gespeichert-rot');
    if (!hasTouch) {
        await button.hover(); await paint(button,false); await capture('04-hover-gruen');
        await page.mouse.move(0,0); await paint(button,true);
    }
    await expect.poll(() => io.reads).toBe(2);
    await button.press('Space');
    await expect.poll(() => io.writes.length).toBe(3);
    expect(io.writes[2]).toEqual(['DELETE',110002]);
    io.confirm(true);
    await state(button,false);
    await paint(button,false);
    await expect(page).toHaveURL(origin+'/alle-artikel/');
    await expect.poll(() => io.reads).toBe(3);
    await info.attach('wishlist-network',{body:JSON.stringify({reads:io.reads,writes:io.writes}),contentType:'application/json'});
});

test('Varianten zwischen Karte, Schnellansicht, Produktseite und Verlauf', async ({context,page,shop}) => {
    const io = await session(context, [], 17);
    await shop.open();
    const first = card(page,10001), button = heart(first);
    await state(button,false);
    await button.click();
    const picker = page.locator('#eg-wish-picker');
    await expect(picker).toBeVisible();
    await expect(picker.locator('[data-wish-id="103"]')).toHaveCount(0);
    await pickerChoice(page,102);
    await expect.poll(() => io.writes).toEqual([['POST',102]]);
    io.confirm(true);
    await state(button,true);
    await expect(picker).toBeHidden();
    await first.locator('.linkabso').click();
    const quick = page.locator('.modal-cross');
    await state(heart(quick),true);
    // No selection and exactly one saved concrete ID.
    await heart(quick).click();
    await expect.poll(() => io.writes[1]).toEqual(['DELETE',102]);
    io.confirm(true);
    await state(button,false);
    await heart(quick).click();
    await expect(quick.locator('.eg-wish-content')).toBeVisible();
    await pickerChoice(page,101);
    await expect.poll(() => io.writes[2]).toEqual(['POST',101]);
    io.confirm(true);
    await state(heart(quick),true);
    await expect(quick.locator('h1')).toBeVisible();
    await quick.locator('[data-eg-value="2"]').click();
    await state(heart(quick),false);
    await heart(quick).click();
    await expect.poll(() => io.writes[3]).toEqual(['POST',102]);
    io.confirm(true);
    await state(heart(quick),true);
    await quick.locator('.close:visible').click();
    await button.click();
    await expect(picker.locator('[data-wish-id]')).toHaveCount(2);
    await expect(picker.locator('[data-wish-save]')).toBeDisabled();
    await pickerChoice(page,101);
    await expect.poll(() => io.writes[4]).toEqual(['DELETE',101]);
    io.confirm(true);
    await state(button,true);
    await expect.poll(() => [...io.ids]).toEqual([102]);
    await first.locator('a.thumb-title').click();
    const detail = heart(page.locator('article.single'));
    await state(detail,true);
    await page.reload();
    await state(detail,true);
    await page.goBack();
    await state(button,true);
    await page.goForward();
    await state(detail,true);
});

test('Beide Tabs verwerfen den alten Herzzustand während des Kontowechsels',async ({context,page,shop})=>{
    let customerId=0, delay, release;
    await context.route('**/rest/io/basket/',async route=>{
        if (delay) await delay;
        return route.fulfill({json:{data:{itemSum:0,itemQuantity:0,currency:'EUR',shippingCountryId:1,
            sessionId:'rotated-'+customerId,customerId,itemWishListIds:customerId ? [101] : [110002]}}});
    });
    await shop.open();
    const peer=await context.newPage();
    try {
        await peer.goto(origin+'/alle-artikel/');
        await Promise.all([page,peer].map(p=>state(heart(card(p,10002)),true)));
        customerId=7;
        delay=new Promise(resolve=>{release=resolve;});
        await page.evaluate(()=>{ O46Core.basketHeader.invalidate(); });
        for (const p of [page,peer]) {
            await expect(heart(card(p,10002))).not.toHaveAttribute('aria-pressed',/./);
            await expect(heart(card(p,10002)).locator('svg')).toHaveCSS('visibility','hidden');
        }
        release();
        for (const p of [page,peer]) {
            await state(heart(card(p,10002)),false);
            await state(heart(card(p,10001)),true);
        }
    } finally { release?.();await peer.close(); }
});

test('Nachgeladene und doppelte Karten übernehmen die Gastmerkliste tabübergreifend',async ({context,page,shop}) => {
    const io = await session(context,[110002]);
    await shop.open('/scroll/');
    const button = heart(card(page,10002));
    await state(button,true);
    const peer = await context.newPage();
    await peer.goto(origin+'/alle-artikel/');
    const peerHeart = heart(card(peer,10002));
    await state(peerHeart,true);
    const initialReads = io.reads;
    await card(page,10002).evaluate(node => node.after(node.cloneNode(true)));
    const duplicates = page.locator('[data-eg-item="10002"] .eg-wish-heart');
    await expect(duplicates).toHaveCount(2);
    await state(duplicates.nth(1),true);
    await page.locator('.view-more-button').first().evaluate(el => el.click());
    await expect(page.locator('[data-eg-item="20002"] .eg-wish-heart')).toHaveAttribute('aria-pressed','true');
    expect(io.reads).toBe(initialReads);
    await peerHeart.click();
    await expect.poll(() => io.writes).toEqual([['DELETE',110002]]);
    io.confirm(true);
    await state(peerHeart,false);
    await state(duplicates.first(),false);
    await state(duplicates.nth(1),false);
    await state(heart(card(page,20002)),false);
    await peer.close();
});

test('Währungs- und Sprachwechsel sowie getrennte Sitzungen', async ({context,page,shop,browser}) => {
    const guest = await session(context,[110002]);
    for (const [lang, label, currency] of [['en','Remove from wish list','CHF'],['fr','Retirer de la liste de souhaits','USD'],
        ['it','Rimuovi dalla lista dei desideri','EUR'],['es','Quitar de la lista de deseos','GBP']]) {
        await shop.open('/'+lang+'/category/?currency='+currency);
        const guestHeart = heart(card(page,10002));
        await state(guestHeart,true);
        await expect(guestHeart).toHaveAccessibleName(new RegExp(label));
    }
    const customer = await browser.newContext();
    try {
        // Share only the public page, its HTML and assets. Session endpoints stay private to each context.
        const html = await (await page.goto(origin+'/category/')).text();
        const documentUrl = origin+'/category/';
        await customer.route('**/*', async route => {
            const url = new URL(route.request().url());
            if (url.href === documentUrl) return route.fulfill({contentType:'text/html',body:html});
            if (url.pathname.startsWith('/assets/')) {
                const path = require('node:path');
                return route.fulfill({path:path.resolve('resources',url.pathname.endsWith('.css')?'css':'js',path.basename(url.pathname))});
            }
            return route.fulfill({status:204});
        });
        const member = await session(customer,[],27);
        const other = await customer.newPage();
        await other.goto(documentUrl);
        await state(heart(card(other,10002)),false);
        await state(heart(card(page,10002)),true);
        expect(member.writes).toEqual([]);
        expect(guest.writes).toEqual([]);
    } finally { await customer.close(); }
});

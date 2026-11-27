// Isolated browser profiles, private IO responses and the real production core.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium, expect} = require('@playwright/test');
const retail = require('../package.json').name.includes('b2c');
const theme = retail ? 'b2c' : 'b2b';
const origin = 'https://wishlist.invalid';
const backupKey = 'o46-guest-wish-' + theme + '-17-7';
const out = path.resolve('.cache/wishlist-isolation');
fs.mkdirSync(out,{recursive:true});

(async () => {
    const profile = fs.mkdtempSync(path.join(out,'profile-'));
    const guest = new Set(), a = new Set([41301]), b = new Set([58202]);
    let scope = 'one', contact = 0, reads = 0, hold, shop = 7, blocked = false, unavailable = false, rejectLogout = false;
    const restores = [];
    const current = () => contact === 31 ? a : contact === 42 ? b : guest;
    let context;
    async function launch() {
        context = await chromium.launchPersistentContext(profile,{headless:true,serviceWorkers:'block',executablePath:process.env.O46_BROWSER_EXECUTABLE});
        if (blocked) await context.addInitScript(()=>{
            Object.defineProperty(window,'localStorage',{get(){throw new DOMException('blocked','SecurityError');}});
        });
        await context.route('**/*', async route=>{
            const url = new URL(route.request().url());
            assert.equal(url.origin,origin);
            if (route.request().isNavigationRequest()) return route.fulfill({contentType:'text/html',body:'<!doctype html><html><body data-eg-wish-shop="'+shop+'"><a class="wishlist-link"></a><script src="/jquery.js"></script><script src="/core.js"></script><script>window.eGuardians={};O46Core.apiservice();O46Core.user();O46Core.basketHeader.start()</script></body></html>'});
            if (url.pathname==='/core.js' || url.pathname==='/jquery.js') return route.fulfill({path:path.resolve('resources/js',url.pathname==='/core.js'?'core.min.js':'jquery-3.7.1.min.js')});
            if (url.pathname==='/rest/io/customer/login/') {
                const email=new URLSearchParams(route.request().postData()).get('email');
                const owner={'a@shop.invalid':31,'b@shop.invalid':42}[email];
                if (!owner) return route.fulfill({status:401,json:{events:{AfterAccountAuthentication:{isSuccess:false}}}});
                contact=owner;guest.forEach(id=>current().add(id));guest.clear();
                return route.fulfill({json:{events:{AfterAccountAuthentication:{isSuccess:true}}}});
            }
            if (url.pathname==='/rest/io/customer/logout/') {
                if(rejectLogout) return route.fulfill({status:503,json:{error:true}});
                contact=0;return route.fulfill({json:{data:200}});
            }
            if (url.pathname==='/rest/io/basket/') {
                reads++;
                if (unavailable) return route.fulfill({status:503});
                const snapshot={currency:'EUR',shippingCountryId:1,itemQuantity:0,itemSum:0,sessionId:scope,customerId:contact,
                    itemWishListIds:[...current()],wishListOwner:{guest:contact===0,plenty:17,shop,scope:'token-'+contact}};
                if (hold) {const gate=hold;hold=null;gate.started();await gate.wait;}
                return route.fulfill({json:{data:snapshot}});
            }
            if (url.pathname==='/rest/guardiansbasic/wishlist/guest') {
                const input = route.request().postDataJSON();
                assert.equal(contact,0,'Browser tried to replay guest IDs during customer session');
                assert.equal(input.plenty,17);assert.equal(input.shop,shop);assert.equal(input.session,scope);
                restores.push(input.ids);
                input.ids.filter(id=>id!==999).forEach(id=>guest.add(id));
                return route.fulfill({json:{data:{available:input.ids.filter(id=>id!==999)}}});
            }
            return route.fulfill({status:204});
        });
        const page=context.pages()[0] || await context.newPage();
        await page.goto(origin+'/shop');
        return page;
    }
    const known = (p,ids)=>expect.poll(()=>p.evaluate(()=>window.egSessionData?.basket?.itemWishListIds?.map(Number).sort((a,b)=>a-b))).toEqual([...ids].sort((a,b)=>a-b));
    const local = p=>p.evaluate(()=>JSON.stringify({
        local:Object.fromEntries(Object.entries(localStorage)),
        session:Object.fromEntries(Object.entries(sessionStorage)),
        cookie:document.cookie
    }));
    const invalidate = p=>p.evaluate(()=>O46Core.basketHeader.invalidate());
    const login=(p,email)=>p.evaluate(email=>new Promise(resolve=>eGuardians.user.login(email,'fixture',(response,status)=>resolve(status))),email);
    const logout=p=>p.evaluate(()=>new Promise(resolve=>eGuardians.user.logout((response,status)=>resolve(status))));
    try {
        guest.add(10001); guest.add(999);
        let page=await launch();await known(page,guest);
        assert.deepEqual(JSON.parse(await page.evaluate(k=>localStorage.getItem(k),backupKey)).ids,[10001,999]);
        // A real browser-process restart; the IO cookie can select a new guest session.
        await context.close();scope='two';guest.clear();
        page=await launch();await known(page,[10001]);assert.deepEqual(restores,[[10001,999]]);
        const copied=await page.evaluate(async ()=>(await fetch('/rest/io/basket/')).json());
        copied.data.itemWishListIds=[41302];
        await page.evaluate(data=>O46Core.basketHeader.accept(data),copied.data);
        await known(page,guest);
        assert.doesNotMatch(await local(page),/41302/,'A copied basket event cannot persist IDs as guest');
        await page.evaluate(()=>O46Core.basketHeader.refresh());await known(page,guest);
        const peer=await context.newPage();await peer.goto(origin+'/shop');await known(peer,[10001]);
        const before=reads;await page.reload();await known(page,guest);assert.equal(reads,before+1);
        assert.equal(await login(page,'missing@shop.invalid'),401);
        await known(page,guest);await known(peer,guest);
        assert.equal(JSON.parse(await page.evaluate(k=>localStorage.getItem(k),backupKey)).loginPending,undefined);
        assert.equal(await login(page,'a@shop.invalid'),200);
        await known(page,a);await known(peer,a);
        assert.equal(await page.evaluate(k=>localStorage.getItem(k),backupKey),null);
        assert.doesNotMatch(await local(page),/41301/);
        a.add(41302);await page.evaluate(()=>O46Core.basketHeader.refreshAfterWrite(true));await known(peer,a);
        rejectLogout=true;
        assert.equal(await logout(page),503);await known(page,a);await known(peer,a);rejectLogout=false;
        let started,release;
        const oldRead=new Promise(r=>{started=r;});
        hold={started,wait:new Promise(r=>{release=r;})};
        await page.evaluate(()=>{O46Core.basketHeader.refresh();});await oldRead;
        scope='three';assert.equal(await logout(page),200);
        assert.equal(await page.evaluate(()=>window.egSessionData?.basket?.itemWishListIds),undefined);
        release();await known(page,[]);await known(peer,[]);
        unavailable=true;
        await invalidate(page);
        assert.equal(await page.evaluate(data=>O46Core.basketHeader.accept(data),copied.data),false);
        assert.equal(await page.evaluate(()=>window.egSessionData?.basket?.itemWishListIds),undefined);
        unavailable=false;await page.evaluate(()=>O46Core.basketHeader.refresh());await known(page,[]);
        for (const owner of [42,31,42,31]) {
            assert.equal(await login(page,owner===31?'a@shop.invalid':'b@shop.invalid'),200);
            await known(page,current());await known(peer,current());
            if(owner===42)b.add(58203);
            await page.evaluate(()=>O46Core.basketHeader.refreshAfterWrite(true));
            assert.doesNotMatch(await local(page),/41301|41302|58202|58203/);
            assert.equal(await logout(page),200);await known(page,[]);
        }
        // Corrupt storage, an expired server session and a different shop must never authorize personal data.
        await page.evaluate(k=>localStorage.setItem(k,'{bad'),backupKey);
        await page.reload();await known(page,[]);
        assert.equal(await page.evaluate(k=>localStorage.getItem(k),backupKey),null);
        guest.add(31415);await invalidate(page);await known(page,guest);
        assert.deepEqual(JSON.parse(await page.evaluate(k=>localStorage.getItem(k),backupKey)).ids,[31415]);
        await context.close();page=await launch();await known(page,[31415]);
        assert.equal(restores.length,1,'Surviving IO session uses no recovery writes');
        await context.close();scope='weeks-later';guest.clear();page=await launch();await known(page,[31415]);
        assert.deepEqual(restores,[[10001,999],[31415]],'Guest returns after the persisted IO session expires');
        guest.clear();for(let id=20000;id<25000;id++)guest.add(id);
        await page.evaluate(()=>O46Core.basketHeader.refresh());await known(page,guest);
        await context.close();scope='expired-large';guest.clear();page=await launch();
        const large=Array.from({length:5000},(_,i)=>20000+i);
        await known(page,large);assert.deepEqual(restores[2],large);
        await page.goto(origin+'/second');await known(page,large);
        await page.goBack();await known(page,large);await page.goForward();await known(page,large);
        assert.equal(restores.length,3,'Only one restore request for 5000 IDs');
        await context.close();shop=8;scope='four';guest.clear();
        page=await launch();await known(page,[]);assert.equal(restores.length,3,'Other shop must not replay this guest backup');
        await context.close();blocked=true;contact=31;
        page=await launch();await known(page,a);
        const other=await context.newPage();await other.goto(origin+'/shop');await known(other,a);
        contact=42;await invalidate(page);await known(page,b);await known(other,b);
        assert.equal(restores.length,3);
        console.log('Persistent browser restart, 5000 guest IDs, real login/logout handlers including failed login/logout, private accounts A/B, back/forward, late IO read, two tabs, storage failure and shop isolation passed.');
    } finally {
        if(context)await context.close();
        // mkdtempSync created this one disposable profile directly under the test output directory.
        if(path.dirname(profile)===out && path.basename(profile).startsWith('profile-'))fs.rmSync(profile,{recursive:true,force:true});
    }
})().catch(error=>{console.error(error);process.exitCode=1;});

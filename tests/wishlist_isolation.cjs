// Isolated browser profiles, private IO responses and the real production core.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium, expect} = require('@playwright/test');
const retail = require('../package.json').name.includes('b2c');
const theme = retail ? 'b2c' : 'b2b';
const origin = 'https://wishlist.invalid';
const api = '/rest/' + (retail ? 'guardiansbasic' : 'guardiansbasicb2b') + '/wishlist';
const backupKey = 'o46-guest-wish-' + theme + '-17-0';
const out = path.resolve('.cache/wishlist-isolation');
fs.mkdirSync(out,{recursive:true});

(async () => {
    const profile = fs.mkdtempSync(path.join(out,'profile-'));
    const guest = new Set(), a = new Set([41301]), b = new Set([58202]);
    let scope = 'one', contact = 0, reads = 0, hold, loginGate, silentlySkipped, shop = 0, blocked = false, unavailable = false, rejectLogout = false;
    let failDelete=false, failReplay=false, soldOut=0;
    const replays=[];
    const restores = [], imported = new Set(), customerRows = new Set();
    const current = () => contact === 31 ? a : contact === 42 ? b : guest;
    let context, writeGate, legacyProfile = true;
    let delayAddReply;
    const sequence = [];
    let documentReads = 0, hiddenDocument = 0, missingReads = 0;
    const sessionReads = new Map();
    async function launch() {
        context = await chromium.launchPersistentContext(profile,{headless:true,serviceWorkers:'block',executablePath:process.env.O46_BROWSER_EXECUTABLE});
        if (legacyProfile) {
            legacyProfile = false;
            await context.addInitScript(({theme,shop})=>{
                // Older releases kept IDs in the header summary before introducing a guest-owned backup.
                const record={scope:'a'.repeat(64),timestamp:Date.now(),change:'legacy',
                    basket:{itemQuantity:0,itemSum:0,currency:'EUR',shippingCountryId:1,itemWishListIds:[654321]}};
                localStorage.setItem('basketCache_header_v2',JSON.stringify(record));
                localStorage.setItem('basketCache_header_v3_'+theme+'-'+shop,JSON.stringify(record));
                localStorage.setItem('other-preexisting-setting','retained');
            },{theme,shop});
        }
        if (blocked) await context.addInitScript(()=>{
            Object.defineProperty(window,'localStorage',{get(){throw new DOMException('blocked','SecurityError');}});
        });
        await context.route('**/*', async route=>{
            const url = new URL(route.request().url());
            assert.equal(url.origin,origin);
            if (url.pathname==='/merkliste/') {
                return route.fulfill({contentType:'text/html',body:`<body data-eg-wish-shop="${shop}" data-eg-wish-api="${api}"><main data-wish-list></main>
                    <script src="/jquery.js"></script><script src="/core.js"></script><script>
                    window.eGuardians={};O46Core.apiservice();
                    const list={wishListIds:[],wishListItems:[],inactiveVariationIds:[],isWishListInitiallyLoading:true,isLoading:false};
                    window.ceresStore={state:{wishList:list},commit(type,value){
                        if(type==='setWishListItems'){
                            list.wishListItems=value;
                            document.querySelector('main').textContent=value.map(row=>row.id).join(',');
                        }
                        if(type==='setWishListIds') list.wishListIds=value.map(Number);
                        if(type==='setInactiveVariationIds') list.inactiveVariationIds=value;
                    }};
                    O46Core.basketHeader.start();</script></body>`});
            }
            if (url.pathname===api+'/items' && route.request().method()==='GET') {
                documentReads++;
                if (hiddenDocument && ++missingReads > 3) return route.fulfill({status:503});
                const pruned={};
                if(soldOut && current().delete(soldOut)){
                    pruned.wishListPruned=true;
                    pruned.wishListPrunedIds=[soldOut];
                    pruned.wishListPrunedOwner={guest:!contact,shop,plenty:17,scope:'token-'+contact,account:contact?String(contact).padStart(64,'0'):undefined};
                }
                return route.fulfill({json:{data:{...pruned,documents:[...current()].filter(id=>id!==hiddenDocument).map(id=>({id})),inactiveVariationIds:[]}}});
            }
            if (route.request().isNavigationRequest()) return route.fulfill({contentType:'text/html',body:'<!doctype html><html><body data-eg-wish-api="'+api+'" data-eg-wish-shop="'+shop+'"><a class="wishlist-link"></a><script src="/jquery.js"></script><script src="/core.js"></script><script>window.eGuardians={};O46Core.apiservice();O46Core.user();O46Core.basketHeader.start()</script></body></html>'});
            if (url.pathname==='/core.js' || url.pathname==='/jquery.js') return route.fulfill({path:path.resolve('resources/js',url.pathname==='/core.js'?'core.min.js':'jquery-3.7.1.min.js')});
            if (url.pathname === api+'/items/' && route.request().method() === 'POST') {
                const id = Number(new URLSearchParams(route.request().postData()).get('variationId'));
                const target = current();
                sequence.push('write-start');
                if (writeGate) await writeGate;
                target.add(id);
                sequence.push('write-end');
                if(delayAddReply){const reply=delayAddReply;delayAddReply=null;reply.stored();await reply.wait;}
                return route.fulfill({status:201,json:{data:{variationId:id}}});
            }
            if (url.pathname===api+'/login') {
                sequence.push('login');
                const email=new URLSearchParams(route.request().postData()).get('email');
                const owner={'a@shop.invalid':31,'b@shop.invalid':42}[email];
                if (!owner) return route.fulfill({status:401,json:{events:{AfterAccountAuthentication:{isSuccess:false}}}});
                if (loginGate) {const gate=loginGate;loginGate=null;gate.started();await gate.wait;}
                contact=owner;guest.forEach(id=>{if(!imported.has(id) && id!==silentlySkipped)current().add(id);});guest.clear();imported.clear();customerRows.clear();
                return route.fulfill({json:{events:{AfterAccountAuthentication:{isSuccess:true}}}});
            }
            if (url.pathname==='/rest/io/customer/logout/') {
                sequence.push('logout');
                if(rejectLogout) return route.fulfill({status:503,json:{error:true}});
                contact=0;return route.fulfill({json:{data:200}});
            }
            if (url.pathname===api+'/session') {
                assert.equal(url.searchParams.get('lastSeenVariationId'),'331');
                const basket={itemWishListIds:[...current()],wishListOwner:{guest:contact===0,plenty:17,shop}};
                return route.fulfill({json:{data:{basket},events:{AfterBasketChanged:{basket:{itemWishListIds:[]}}}}});
            }
            if (url.pathname===api+'/basket/') {
                reads++;
                const tab = route.request().frame().page();
                sessionReads.set(tab, (sessionReads.get(tab) || 0) + 1);
                if (unavailable) return route.fulfill({status:503});
                const snapshot={currency:'EUR',shippingCountryId:1,itemQuantity:0,itemSum:0,sessionId:scope,customerId:contact,
                    itemWishListIds:[...current()],wishListOwner:{guest:contact===0,plenty:17,shop,scope:'token-'+contact,account:contact?String(contact).padStart(64,'0'):undefined,imported:contact===0?[...imported]:undefined,customerIds:contact===0?[...customerRows].filter(id=>guest.has(id)):undefined}};
                if (hold) {const gate=hold;hold=null;gate.started();await gate.wait;}
                return route.fulfill({json:{data:snapshot}});
            }
            if (url.pathname===api+'/removals') {
                const input=route.request().postDataJSON();
                assert.equal(input.wishScope,'token-'+contact);
                assert.equal(input.account,contact ? String(contact).padStart(64,'0') : 'guest');
                replays.push([contact,input.ids]);
                if(failReplay) return route.fulfill({status:503});
                for(const id of input.ids) current().delete(id);
                return route.fulfill({json:{data:{removed:input.ids}}});
            }
            if (route.request().method()==='DELETE' && url.pathname.startsWith(api+'/items/')) {
                const id=Number(url.pathname.split('/').pop());
                if(failDelete) return route.fulfill({status:503,json:{data:false}});
                const success=current().delete(id);
                if(!contact){imported.delete(id);customerRows.delete(id);}
                return route.fulfill({json:{data:success}});
            }
            if (url.pathname===api+'/guest') {
                const input = route.request().postDataJSON();
                assert.equal(contact,0,'Browser tried to replay guest IDs during customer session');
                assert.equal(input.plenty,17);assert.equal(input.shop,shop);assert.equal(input.session,scope);
                restores.push(input.ids);
                input.imported.forEach(id=>imported.add(id));
                input.customerIds.forEach(id=>customerRows.add(id));
                input.ids.filter(id=>id!==999).forEach(id=>guest.add(id));
                return route.fulfill({json:{data:{available:input.ids.filter(id=>id!==999)}}});
            }
            return route.fulfill({status:204});
        });
        const page=context.pages()[0] || await context.newPage();
        await page.goto(origin+'/shop');
        return page;
    }
    const known = async (p,ids)=>{await expect.poll(()=>p.evaluate(()=>window.egSessionData?.basket?.itemWishListIds?.map(Number).sort((a,b)=>a-b))).toEqual([...ids].sort((a,b)=>a-b));
        await expect.poll(()=>p.locator('.wishlist-link').evaluate(link=>link.classList.contains('noWishListItems'))).toBe([...ids].length===0);
    };
    const local = p=>p.evaluate(()=>JSON.stringify({
        local:Object.fromEntries(Object.entries(localStorage)),
        session:Object.fromEntries(Object.entries(sessionStorage)),
        cookie:document.cookie
    }));
    const backupFor = (page,key=backupKey)=>page.evaluate(key=>JSON.parse(localStorage.getItem(key)),key);
    const outsideBackup = p=>p.evaluate(()=>JSON.stringify([
        Object.entries(localStorage).filter(([key])=>!key.startsWith('o46-guest-wish-')),
        Object.entries(sessionStorage), document.cookie
    ]));
    const invalidate = p=>p.evaluate(()=>O46Core.basketHeader.invalidate());
    const login=(p,email)=>p.evaluate(email=>new Promise(resolve=>eGuardians.user.login(email,'fixture',(response,status)=>resolve(status))),email);
    const logout=p=>p.evaluate(()=>new Promise(resolve=>eGuardians.user.logout((response,status)=>resolve(status))));
    try {
        // A guest deletion applies to its original account only after that account signs back in.
        shop=11;
        a.add(80002);a.add(80003);b.add(80002);
        let probe=await launch();await known(probe,[]);
        assert.equal(await login(probe,'a@shop.invalid'),200);await known(probe,a);
        assert.equal(await logout(probe),200);await known(probe,a);
        const remove=async (tab,id)=>tab.evaluate(id=>new Promise(resolve=>{
            eGuardians.apiservice.delete('/rest/io/itemWishList/'+id,O46Core.basketHeader.wishParams({}),async (response,code)=>{
                if(code===200 && response.data===true) await O46Core.basketHeader.refreshAfterWrite(true);
                resolve(code);
            });
        }),id);
        const probeKey=backupKey.replace('-0','-11');
        const backupBefore=await backupFor(probe,probeKey);
        failDelete=true;assert.equal(await remove(probe,80002),503);failDelete=false;
        assert.deepEqual(await backupFor(probe,probeKey),backupBefore);
        const observer=await context.newPage();await observer.goto(origin+'/shop');await known(observer,a);
        soldOut=80003;
        await probe.evaluate(api=>new Promise(resolve=>jQuery.get(api+'/items').always(resolve)),api);
        await expect.poll(async()=>(await backupFor(probe,probeKey)).customerIds).toEqual([41301,80002]);
        assert.ok(a.has(soldOut),'Confirmed stock cleanup in guest IO defers the corresponding personal removal');
        soldOut=0;
        for(const id of [...guest]) assert.equal(await remove(probe,id),200);
        await known(probe,[]);await known(observer,[]);
        const empty=await backupFor(probe,probeKey);
        assert.deepEqual(empty?.ids,[],'The confirmed empty list is durable');
        assert.deepEqual(empty?.customerIds||[],[]);assert.deepEqual(empty?.imported||[],[]);
        const accountA = String(31).padStart(64,'0');
        const accountReceipts = empty.pendingRemovals[accountA];
        assert.deepEqual(Object.keys(accountReceipts).sort(), ['41301','80002','80003']);
        assert.deepEqual([...a],[41301,80002,80003],'No account changes while signed out');
        await context.close();scope='fresh-guest-after-deletes';probe=await launch();await known(probe,[]);
        assert.equal(await login(probe,'b@shop.invalid'),200);await known(probe,b);
        assert.ok(b.has(80002));assert.equal(replays.length,0,'B never receives a request for A');
        assert.deepEqual((await backupFor(probe,probeKey)).pendingRemovals[accountA], accountReceipts);
        assert.equal(await logout(probe),200);await known(probe,b);
        failReplay=true;assert.equal(await login(probe,'a@shop.invalid'),200);await known(probe,[]);
        assert.equal(a.size,3,'Failed account replay must retain the receipt and original IO account rows');
        assert.deepEqual((await backupFor(probe,probeKey)).pendingRemovals[accountA], accountReceipts);
        failReplay=false;await probe.evaluate(()=>O46Core.basketHeader.refresh());await known(probe,[]);
        assert.deepEqual([...a],[]);assert.ok(b.has(80002));
        assert.equal((await backupFor(probe,probeKey)).pendingRemovals?.[accountA], undefined);
        assert.deepEqual(replays.map(([owner,ids])=>[owner,[...ids].sort()]),[[31,[41301,80002,80003]],[31,[41301,80002,80003]]]);
        await probe.reload();await known(probe,[]);assert.equal(replays.length,2);
        assert.equal(await logout(probe),200);await known(probe,[]);
        await context.close();a.add(41301);b.delete(80002);guest.clear();imported.clear();customerRows.clear();
        restores.length=0;scope='one';legacyProfile=true;
        shop=12;probe=await launch();await known(probe,[]);
        assert.equal(await login(probe,'a@shop.invalid'),200);await known(probe,a);
        assert.equal(await logout(probe),200);await known(probe,a);
        let releaseOld, markOld;
        const oldSnapshot=new Promise(resolve=>{markOld=resolve;});
        const staleTab=await context.newPage();await staleTab.goto(origin+'/shop');await known(staleTab,a);
        hold={started:markOld,wait:new Promise(resolve=>{releaseOld=resolve;})};
        await staleTab.evaluate(()=>{O46Core.basketHeader.refresh();});await oldSnapshot;
        assert.equal(await remove(probe,41301),200);releaseOld();await known(staleTab,[]);
        // An explicit, successful re-add reverses the earlier guest removal.
        await probe.evaluate(()=>new Promise(resolve=>eGuardians.apiservice.post('/rest/io/itemWishList/',{variationId:41301},async (r,status)=>{
            if(status===201) await O46Core.basketHeader.refreshAfterWrite(true);resolve();
        })));
        await known(probe,[41301]);await known(staleTab,[41301]);
        const callsBeforeLogin=replays.length;
        assert.equal(await login(probe,'a@shop.invalid'),200);await known(probe,[41301]);
        assert.equal(replays.length,callsBeforeLogin);assert.ok(a.has(41301));
        await logout(probe);await known(probe,a);
        // IO accepted a duplicate add first, but its HTTP reply arrives after a newer confirmed removal.
        let acceptedAdd, releaseReply;
        const wasAdded=new Promise(resolve=>{acceptedAdd=resolve;});
        delayAddReply={stored:acceptedAdd,wait:new Promise(resolve=>{releaseReply=resolve;})};
        const lateAdd=probe.evaluate(()=>new Promise(resolve=>eGuardians.apiservice.post('/rest/io/itemWishList/',{variationId:41301},async (r,status)=>{
            if(status===201) await O46Core.basketHeader.refreshAfterWrite(true);resolve();
        })));
        await wasAdded;await known(staleTab,[41301]);
        assert.equal(await remove(staleTab,41301),200);releaseReply();await lateAdd;await known(probe,[]);
        assert.equal(await login(probe,'a@shop.invalid'),200);await known(probe,[]);
        assert.equal(a.size,0,'An older POST reply cannot undo a newer guest deletion');
        await logout(probe);await known(probe,[]);await context.close();a.add(41301);
        guest.clear();imported.clear();customerRows.clear();restores.length=0;legacyProfile=true;
        // Start with a fresh shop and add a variation only after successful account authentication.
        shop=9;
        let client=await launch();await known(client,[]);
        assert.equal(await login(client,'a@shop.invalid'),200);await known(client,a);
        await client.evaluate(()=>new Promise((resolve,reject)=>{
            eGuardians.apiservice.post('/rest/io/itemWishList/',{variationId:61701},(response,status)=>{
                if(status!==201) reject(new Error('Customer write failed'));
                else O46Core.basketHeader.refreshAfterWrite(true).then(resolve,reject);
            });
        }));
        const expectedA=[...a];
        const visible=async (tab,expected)=>{
            await tab.goto(origin+'/merkliste/');
            await expect.poll(()=>tab.locator('main').innerText()).toBe(expected.join(','));
        };
        let listing=await context.newPage();await visible(listing,expectedA);await listing.close();
        assert.equal(await logout(client),200);await known(client,expectedA);
        assert.deepEqual([...imported],expectedA,'Every account-origin guest row must be blocked for native IO migration');
        await visible(client,expectedA);
        const count=restores.length;
        await context.close();client=await launch();await known(client,expectedA);
        assert.equal(restores.length,count,'Matching IO rows need no restore after browser restart');
        await visible(client,expectedA);
        await context.close();guest.clear();imported.clear();customerRows.clear();
        client=await launch();await known(client,expectedA);
        assert.equal(restores.length,count+1,'An empty guest session with the same basket ID is recovered once');
        await context.close();scope='expired-account-copy';guest.clear();imported.clear();customerRows.clear();
        client=await launch();await known(client,expectedA);assert.equal(restores.length,count+2);
        assert.equal(await login(client,'a@shop.invalid'),200);await known(client,expectedA);
        a.delete(41301);await client.evaluate(()=>O46Core.basketHeader.refreshAfterWrite(true));
        assert.equal(await logout(client),200);await known(client,[61701]);
        guest.add(61702);await client.evaluate(()=>O46Core.basketHeader.refreshAfterWrite(true));
        assert.equal(await login(client,'b@shop.invalid'),200);await known(client,[58202,61702]);
        assert.ok(!b.has(61701) && !b.has(41301));assert.deepEqual([...a],[61701]);
        assert.equal(await logout(client),200);await known(client,[61702,58202]);
        await context.close();guest.clear();imported.clear();customerRows.clear();restores.length=0;
        a.clear();a.add(41301);b.delete(61702);shop=0;scope='one';legacyProfile=true;
        guest.add(10001); guest.add(999);
        let page=await launch();await known(page,guest);
        assert.deepEqual(JSON.parse(await page.evaluate(k=>localStorage.getItem(k),backupKey)).ids,[10001,999]);
        assert.equal(await page.evaluate(()=>localStorage.getItem('basketCache_header_v2')),null);
        assert.doesNotMatch(await local(page),/654321/,'A legacy header with customer IDs cannot seed the guest backup');
        assert.equal(await page.evaluate(()=>localStorage.getItem('other-preexisting-setting')),'retained');
        // A real browser-process restart; the IO cookie can select a new guest session.
        await context.close();scope='two';guest.clear();
        page=await launch();await known(page,[10001]);assert.deepEqual(restores,[[10001,999]]);
        // Two old profile tabs can load the first empty guest snapshot after expiry concurrently.
        await context.close();scope='two-tabs-after-expiry';guest.clear();
        let releaseEmpty, markEmpty;
        const emptyStarted=new Promise(resolve=>{markEmpty=resolve;});
        hold={started:markEmpty,wait:new Promise(resolve=>{releaseEmpty=resolve;})};
        page=await launch();await emptyStarted;
        const recovering=await context.newPage();await recovering.goto(origin+'/shop');await known(recovering,[10001]);
        releaseEmpty();
        await known(page,[10001]);await recovering.close();
        assert.deepEqual(JSON.parse(await page.evaluate(k=>localStorage.getItem(k),backupKey)).ids,[10001]);
        const listPage=await context.newPage();await listPage.goto(origin+'/merkliste/');
        await expect(listPage.locator('main')).toHaveText('10001');
        const docsBefore=documentReads;
        const authorBefore=sessionReads.get(page), peerBefore=sessionReads.get(listPage);
        guest.add(11001);await page.evaluate(()=>O46Core.basketHeader.refreshAfterWrite(true));
        await expect(listPage.locator('main')).toHaveText('10001,11001');
        guest.delete(11001);await page.evaluate(()=>O46Core.basketHeader.refreshAfterWrite(true));
        await expect(listPage.locator('main')).toHaveText('10001');
        assert.equal(documentReads,docsBefore+1,'One document GET for an unknown addition, zero for removal');
        assert.equal(sessionReads.get(page)-authorBefore,2);
        assert.equal(sessionReads.get(listPage)-peerBefore,2);
        console.log('Two confirmed changes: 2 private session reads in the writing tab, 2 in the list tab, 1 document GET for the new row.');
        // A temporarily unavailable document can coexist with a valid IO ID. Do not poll the server.
        hiddenDocument=12001;guest.add(hiddenDocument);
        await page.evaluate(()=>O46Core.basketHeader.refreshAfterWrite(true));
        await expect.poll(()=>missingReads).toBeGreaterThan(0);
        await listPage.evaluate(()=>O46Core.basketHeader.pendingRead);
        await listPage.evaluate(()=>O46Core.basketHeader.refresh());
        assert.equal(missingReads,1,'Unchanged authoritative IDs must not loop on an absent document');
        guest.delete(hiddenDocument);hiddenDocument=0;
        await page.evaluate(()=>O46Core.basketHeader.refreshAfterWrite(true));
        await expect.poll(()=>listPage.evaluate(()=>window.egSessionData.basket.itemWishListIds)).toEqual([...guest]);
        await expect(listPage.locator('main')).toHaveText('10001');
        await listPage.close();
        const central=await page.evaluate(()=>new Promise((resolve,reject)=>jQuery.ajax('/rest/io/session?lastSeenVariationId=331',
            {dataType:'json'}).done(reply=>resolve(reply.events.AfterBasketChanged.basket)).fail(reject)));
        assert.equal(central.wishListOwner.shop,0);assert.deepEqual(central.itemWishListIds,[10001]);
        const copied=await page.evaluate(async api=>(await fetch(api+'/basket/')).json(), api);
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
        let startLogin, finishLogin;
        const loginStarted=new Promise(resolve=>{startLogin=resolve;});
        loginGate={started:startLogin,wait:new Promise(resolve=>{finishLogin=resolve;})};
        const signingIn=login(page,'a@shop.invalid');
        await loginStarted;
        await peer.evaluate(()=>O46Core.basketHeader.refresh());
        assert.deepEqual([...imported],[],'A concurrent guest read cannot suppress the first native transfer');
        finishLogin();
        assert.equal(await signingIn,200);assert.equal(a.has(10001),true);
        await known(page,a);await known(peer,a);
        assert.deepEqual(JSON.parse(await page.evaluate(k=>localStorage.getItem(k),backupKey)).imported,[10001]);
        assert.deepEqual(new Set((await backupFor(page)).customerIds),a);
        assert.doesNotMatch(await outsideBackup(page),/41301/);
        a.add(41302);await page.evaluate(()=>O46Core.basketHeader.refreshAfterWrite(true));await known(peer,a);
        rejectLogout=true;
        assert.equal(await logout(page),503);await known(page,a);await known(peer,a);rejectLogout=false;
        let started,release;
        const oldRead=new Promise(r=>{started=r;});
        hold={started,wait:new Promise(r=>{release=r;})};
        await page.evaluate(()=>{O46Core.basketHeader.refresh();});await oldRead;
        assert.equal(await logout(page),200); // IO may retain its basket session ID across logout.
        assert.equal(await page.evaluate(()=>window.egSessionData?.basket?.itemWishListIds),undefined);
        release();await known(page,a);await known(peer,a);
        assert.deepEqual(guest,a);assert.deepEqual(imported,a);
        unavailable=true;
        await invalidate(page);
        assert.equal(await page.evaluate(data=>O46Core.basketHeader.accept(data),copied.data),false);
        assert.equal(await page.evaluate(()=>window.egSessionData?.basket?.itemWishListIds),undefined);
        unavailable=false;await page.evaluate(()=>O46Core.basketHeader.refresh());await known(page,a);
        // Losing browser metadata during an active guest session cannot make an old import eligible again.
        await page.evaluate(k=>localStorage.removeItem(k),backupKey);await page.evaluate(()=>O46Core.basketHeader.refresh());
        assert.deepEqual(JSON.parse(await page.evaluate(k=>localStorage.getItem(k),backupKey)).imported,[10001]);
        assert.deepEqual(new Set((await backupFor(page)).customerIds),new Set([41301,41302]));
        for (const owner of [42,31,42,31]) {
            assert.equal(await login(page,owner===31?'a@shop.invalid':'b@shop.invalid'),200);
            await known(page,current());await known(peer,current());
            if(owner===42){assert.equal(b.has(10001),false,'B must never automatically import the restored guest ID');b.add(58203);}
            await page.evaluate(()=>O46Core.basketHeader.refreshAfterWrite(true));
            assert.deepEqual(new Set((await backupFor(page)).customerIds),current());
            assert.doesNotMatch(await outsideBackup(page),/41301|41302|58202|58203/);
            assert.ok(!b.has(41301) && !b.has(41302));
            const visibleAfterLogout = new Set(current());
            assert.equal(await logout(page),200);await known(page,visibleAfterLogout);await known(peer,visibleAfterLogout);
        }
        // Explicit guest removal updates both the IO list and its recovery record.
        guest.delete(10001); imported.delete(10001); await page.evaluate(()=>O46Core.basketHeader.refreshAfterWrite(true));
        await known(page,[41301,41302]);await known(peer,[41301,41302]);
        assert.deepEqual((await backupFor(page)).ids,[]);
        guest.clear();imported.clear();customerRows.clear();
        await page.evaluate(()=>O46Core.basketHeader.refreshAfterWrite(true));
        await known(page,[]); await known(peer,[]);
        assert.deepEqual((await backupFor(page)).ids,[]);
        assert.ok(a.has(41301) && a.has(41302),'Guest removal must leave the personal account intact');
        const loginRestores = restores.length;
        // Corrupt storage, an expired server session and a different shop must never authorize personal data.
        await page.evaluate(k=>localStorage.setItem(k,'{bad'),backupKey);
        await page.reload();await known(page,[]);
        assert.equal(await page.evaluate(k=>localStorage.getItem(k),backupKey),null);
        guest.add(31415);await invalidate(page);await known(page,guest);
        assert.deepEqual(JSON.parse(await page.evaluate(k=>localStorage.getItem(k),backupKey)).ids,[31415]);
        await context.close();page=await launch();await known(page,[31415]);
        assert.equal(restores.length,loginRestores,'Surviving IO session uses no recovery writes');
        await context.close();scope='weeks-later';guest.clear();page=await launch();await known(page,[31415]);
        assert.deepEqual(restores.slice(loginRestores),[[31415]],'Guest returns after the persisted IO session expires');
        guest.clear();for(let id=20000;id<25000;id++)guest.add(id);
        await page.evaluate(()=>O46Core.basketHeader.refresh());await known(page,guest);
        await context.close();scope='expired-large';guest.clear();page=await launch();
        const large=Array.from({length:5000},(_,i)=>20000+i);
        await known(page,large);assert.deepEqual(restores.at(-1),large);
        await page.goto(origin+'/second');await known(page,large);
        await page.goBack();await known(page,large);await page.goForward();await known(page,large);
        assert.equal(restores.length,loginRestores+2,'Only one restore request for 5000 IDs');
        await context.close();shop=8;scope='four';guest.clear();
        page=await launch();await known(page,[]);assert.equal(restores.length,loginRestores+2,'Other shop must not replay this guest backup');
        await context.close();blocked=true;contact=31;
        page=await launch();await known(page,a);
        const other=await context.newPage();await other.goto(origin+'/shop');await known(other,a);
        contact=42;await invalidate(page);await known(page,b);await known(other,b);
        assert.equal(restores.length,loginRestores+2);
        contact=0;guest.add(777);await invalidate(page);await known(page,new Set([...b,777]));
        assert.equal(await login(page,'a@shop.invalid'),200);await known(page,a);
        assert.equal(await logout(page),200);await known(page,new Set([...a,777]));
        assert.deepEqual(await page.evaluate(k=>JSON.parse(sessionStorage.getItem(k)).ids,backupKey.replace('-0','-8')),[777]);
        guest.add(778);guest.add(779);silentlySkipped=779;
        await page.evaluate(()=>O46Core.basketHeader.refresh());await known(page,new Set([...a,777,778,779]));
        assert.equal(await login(page,'a@shop.invalid'),200);await known(page,a);
        const backup=await page.evaluate(k=>JSON.parse(sessionStorage.getItem(k)),backupKey.replace('-0','-8'));
        assert.deepEqual(backup.ids,[777,778,779]);
        assert.deepEqual(backup.imported.sort((a,b)=>a-b),[777,778],'An unconfirmed write must not be recorded as imported');
        assert.equal(await logout(page),200);await known(page,new Set([...a,777,778,779]));
        // Start auth while the native write and its private reconciliation are outstanding.
        // Run under the same basket session ID for both owners.
        for (const [id, auth] of [[781, 'login'], [782, 'logout']]) {
            let releaseWrite;
            writeGate = new Promise(resolve => { releaseWrite = resolve; });
            sequence.length = 0;
            await page.evaluate(id => {
                eGuardians.apiservice.post('/rest/io/itemWishList/', {variationId:id}, (response, status) => {
                    if (status === 201) O46Core.basketHeader.refreshAfterWrite(true);
                });
            }, id);
            await expect.poll(() => sequence[0]).toBe('write-start');
            const authenticating = auth === 'login' ? login(page,'a@shop.invalid') : logout(page);
            // The browser acknowledges the auth callback setup before releasing the HTTP response.
            await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
            releaseWrite();
            assert.equal(await authenticating,200);
            assert.deepEqual(sequence, ['write-start','write-end',auth]);
            await known(page, auth === 'login' ? a : new Set([...a,777,778,779,781]));
            const retained = await page.evaluate(k=>JSON.parse(sessionStorage.getItem(k)),backupKey.replace('-0','-8'));
            assert.ok(retained.ids.includes(781));
            assert.ok(!retained.ids.includes(782));
        }
        assert.ok(a.has(781) && a.has(782));
        assert.ok(guest.has(781) && guest.has(782) && !b.has(782));
        console.log('Persistent browser restart, 5000 guest IDs, real login/logout handlers including failed login/logout, private accounts A/B, back/forward, late IO read, two tabs, storage failure and shop isolation passed.');
    } finally {
        if(context)await context.close();
        // mkdtempSync created this one disposable profile directly under the test output directory.
        if(path.dirname(profile)===out && path.basename(profile).startsWith('profile-'))fs.rmSync(profile,{recursive:true,force:true});
    }
})().catch(error=>{console.error(error);process.exitCode=1;});

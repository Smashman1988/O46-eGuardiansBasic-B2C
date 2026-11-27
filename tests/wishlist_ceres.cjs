// node tests/wishlist_ceres.cjs <reference Ceres directory> <rendered PDP HTML>
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require(process.env.O46_PLAYWRIGHT_MODULE || 'playwright');
const dist = path.join(process.argv[2], 'resources/js/dist');
const base = fs.readFileSync(path.join(dist,'ceres-base.js'),'utf8');
const startup = 'return __webpack_require__(__webpack_require__.s = "./resources/js/src/base.js");';
assert.ok(base.includes(startup));
const chunks = fs.readdirSync(path.join(dist,'chunks')).filter(name => /^ceres-\d+\.js$/.test(name))
    .map(name => fs.readFileSync(path.join(dist,'chunks',name),'utf8')).join('\n');
const template = fs.readFileSync('resources/views/WishList/Components/WishList.twig','utf8').split('{% verbatim %}')[1].split('{% endverbatim %}')[0];
const controls = fs.readFileSync(process.argv[3],'utf8').match(/<template id="eg-wish-control">[\s\S]*?<div id="eg-wish-picker"[\s\S]*?<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/)[0];
function row(id) {
    return {id,data:{variation:{id,intervalOrderQuantity:1,availability:{id:1,names:{name:'Available'}}},item:{id:45},properties:[],attributes:[],unit:{unitOfMeasurement:'C62',content:1},
        texts:{name1:'Fixture'},prices:{default:{unitPrice:{value:5}}},images:{},filter:{isSalable:true}}};
}
(async () => {
    const browser = await chromium.launch({executablePath:process.env.O46_BROWSER_EXECUTABLE});
    try {
        await checkMount(false);
        await checkMount(true);
        console.log('Ceres wishlist: both mount orders, HTTP 503, retry, hidden missing and unsaleable variants, remote addition, cross-currency removal and request counts passed.');
    } finally { await browser.close(); }

    async function checkMount(coreFirst) {
        const page = await browser.newPage(), errors = [], writes = [];
        const listPaths = [];
        page.setDefaultTimeout(6000);
        page.on('pageerror', e => errors.push(e.message));
        const ids = new Set([101,102,103,106,999]);
        let accept = false, reads = 0, pruneOnRead = false;
        const basket = () => ({itemQuantity:0,itemSum:0,currency:'EUR',shippingCountryId:1,sessionId:'customer-session-fixture',customerId:17,
            itemWishListIds:[...ids],wishListOwner:{guest:false,shop:0,plenty:17,scope:'customer-scope'}});
        await page.route('**/*', route => {
            const u = new URL(route.request().url());
            if (u.pathname === '/') return route.fulfill({contentType:'text/html',body:
                '<body id="isVueView" data-eg-wish-api="/rest/guardiansbasic/wishlist" data-eg-wish-shop="0"><header id="page-header" data-eg-basket-currency="EUR"></header><main id="page-body"><div id="app"><wish-list></wish-list></div></main>'+controls+'</body>'});
            if (['/rest/io/itemWishList','/rest/guardiansbasic/wishlist/items'].includes(u.pathname)) {
                listPaths.push(u.pathname);
                reads++;
                const pruned = pruneOnRead && ids.delete(105);
                const documents = [...ids].filter(id=>id!==999).map(row);
                for (const item of documents) {
                    item.data.filter.isSalable = item.id !== 103;
                    item.data.stock = {net: item.id === 106 ? 0 : 2};
                }
                return route.fulfill({json:{data:{documents,inactiveVariationIds:ids.has(999)?[999]:[],wishListPruned:pruned}}});
            }
            if (route.request().method()==='DELETE') {
                const id = Number(u.pathname.split('/').pop()); writes.push(id);
                if (accept) ids.delete(id);
                return route.fulfill({status:accept?200:503,json:{data:accept,error:!accept}});
            }
            if (u.pathname==='/rest/guardiansbasic/wishlist/basket/') return route.fulfill({json:{data:basket()}});
            return route.fulfill({status:204});
        });
        await page.goto('https://fixture.test/');
        await page.addScriptTag({content:base.replace(startup,'window.ceresRequire = __webpack_require__;')});
        await page.addScriptTag({content:chunks});
        await page.evaluate(()=>{
            window.App={language:'de',templateType:'wish-list',config:{log:{data:[]}}};
            window.$=window.jQuery=ceresRequire('./node_modules/jquery/dist/jquery.js');
            ceresRequire('./node_modules/bootstrap/dist/js/bootstrap.js');
        });
        if (coreFirst) await page.addScriptTag({path:'resources/js/core.min.js'});
        await page.evaluate(({template,basket})=>{
            const Vue=ceresRequire('./node_modules/vue/dist/vue.js');
            const Vuex=ceresRequire('./node_modules/vuex/dist/vuex.esm.js').default;
            Vue.use(Vuex);
            Vue.config.errorHandler = error => { setTimeout(()=>{throw error;}); };
            Vue.prototype.$translate=key=>key;
            Vue.prototype.$ceres={config:{global:{shippingCostsCategoryId:0}}};
            for(const [name,fn] of Object.entries({itemImages:()=>[],itemImage:()=>'',itemURL:()=>'/item/',itemName:()=>'Fixture',
                currency:String,hasItemDefaultPrice:()=>true})) Vue.filter(name,fn);
            for (const component of ['quantity-input','add-to-basket','loading-animation']) Vue.component(component,{render:h=>h('span')});
            const mod=name=>ceresRequire('./resources/js/src/app/store/modules/'+name+'Module.js').default;
            window.ceresStore=new Vuex.Store({modules:{basket:mod('Basket'),wishList:mod('WishList')}});
            ceresStore.commit('setBasket',basket);
            ceresStore.commit('setIsBasketInitiallyLoaded');
            const WishList={...ceresRequire('./resources/js/src/app/components/wishList/WishList.vue').default,...Vue.compile(template)};
            new Vue({store:ceresStore,render:h=>h(WishList)}).$mount('#app');
        },{template,basket:basket()});
        if (!coreFirst) await page.addScriptTag({path:'resources/js/core.min.js'});
        await page.addScriptTag({path:'resources/js/main.min.js'});
        await page.evaluate(()=>O46Core.basketHeader.start());
        const control=id=>page.locator('[data-wish-entry="'+id+'"] .eg-wish-heart');
        await control(101).waitFor();
        assert.equal(await control(103).count(),0);
        assert.equal(await control(106).count(),0);
        assert.equal(await control(999).count(),0);
        assert.equal(await control(101).getAttribute('aria-pressed'),'true');
        await control(101).click();
        await page.waitForFunction(()=>document.querySelector('[data-wish-entry="101"] [role=status]').textContent.length>0);
        assert.deepEqual(await page.evaluate(()=>ceresStore.state.wishList.wishListItems.map(i=>i.id)),[101,102,103,106]);
        accept=true;
        await control(101).press('Space'); await control(101).waitFor({state:'detached'});
        assert.deepEqual(writes,[101,101]);
        assert.equal(reads,1);
        ids.delete(999); ids.delete(103); ids.delete(106); ids.add(104);
        await page.evaluate(()=>O46Core.basketHeader.refresh());
        await control(104).waitFor();
        assert.equal(reads,2);
        assert.deepEqual(await page.evaluate(()=>ceresStore.state.wishList.wishListIds),[102,104]);
        // A peer only signals the write. The recipient reads its own current IO session.
        ids.delete(102);
        await page.evaluate(() => {
            const key='o46-wish-change-b2c-0';
            const message={kind:'wish',nonce:'peer-wish-delete'};
            dispatchEvent(new StorageEvent('storage',{key,newValue:JSON.stringify(message)}));
        });
        await control(102).waitFor({state:'detached'});
        assert.equal(await page.evaluate(()=>window.egSessionData.basket.currency),'EUR');
        assert.equal(await control(104).getAttribute('aria-pressed'),'true');
        assert.equal(reads,2);
        pruneOnRead = true;
        ids.add(105);
        await page.evaluate(()=>O46Core.basketHeader.refresh());
        await page.waitForFunction(()=>ceresStore.state.wishList.wishListIds.join(',')==='104');
        assert.deepEqual([...ids],[104]);
        assert.equal(await control(105).count(),0);
        assert.equal(reads,3);
        await Promise.all([
            page.waitForResponse(response=>new URL(response.url()).pathname==='/rest/guardiansbasic/wishlist/items'),
            page.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})))
        ]);
        assert.equal(reads,4);
        assert.deepEqual(errors,[]);
        assert.equal(listPaths[0],coreFirst ? '/rest/guardiansbasic/wishlist/items' : '/rest/io/itemWishList');
        assert.ok(listPaths.slice(1).every(path=>path==='/rest/guardiansbasic/wishlist/items'));
        await page.close();
    }
})().catch(error=>{console.error(error);process.exitCode=1;});

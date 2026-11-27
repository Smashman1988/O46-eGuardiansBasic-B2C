// Real Twig cards and complete shipped bundles; only local HTTP fixture data.
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {chromium}=require(process.env.O46_PLAYWRIGHT_MODULE||'playwright');
const photoDir=path.resolve(process.argv[3]);
const D=path.resolve(process.argv[2]),root=path.resolve(__dirname,'..'),rows=[];
const read=p=>fs.readFileSync(p,'utf8');
// Resolve the Twig resource path before parsing CSS, so the braces cannot truncate a font rule.
const fonts=[...read(root+'/resources/views/PageDesign/Partials/Head.twig').replaceAll("{{ plugin_path('GuardiansBasic') }}",'/theme').replaceAll('{{ headThemePath }}','/theme').matchAll(/@font-face\{[^}]+\}/g)].slice(0,3).map(x=>x[0]).join('');
const dialog = read(path.join(D,'wish-controls.html'));
let origin,requests=[];
const server=http.createServer((req,res)=>{
 const u=new URL(req.url,origin);requests.push({path:u.pathname+u.search,method:req.method});
 const photo=u.pathname.match(/^\/images\/product-(\d+)\.jpg(\.avif)?$/);
 if(photo){const file=path.join(photoDir,((Number(photo[1])-100)%12)+(photo[2]||'.jpg'));res.setHeader('Content-Type',photo[2]?'image/avif':'image/jpeg');return fs.createReadStream(file).pipe(res);}
 if(u.pathname.endsWith('/images/vendors/11-project.jpg')){res.setHeader('Content-Type','image/jpeg');return fs.createReadStream(path.join(root,'resources/images/vendors/11-project.jpg')).pipe(res);}
 if(u.pathname==='/baseline/cat.min.js'){res.setHeader('Content-Type','application/javascript');return res.end(read(D+'/baseline/cat.min.js'));}
 if(u.pathname.startsWith('/visual/')){const file=D+u.pathname.replace(/\.avif$/,'');if(fs.existsSync(file))return res.end(fs.readFileSync(file));}
 if(u.pathname.startsWith('/assets/')){res.setHeader('Content-Type','application/javascript');return res.end(read(root+'/resources/js/'+path.basename(u.pathname)));}
 if(/\.woff2?$/.test(u.pathname))return res.end(fs.readFileSync(root+'/resources/documents/fonts/'+path.basename(u.pathname)));
 if(u.pathname==='/rest/io/basket/'){res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({data:{itemQuantity:0,itemSum:0,currency:'EUR',shippingCountryId:1,itemWishListIds:[]}}));}
 if(u.pathname==='/rest/io/session/'){res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({data:{basket:{itemQuantity:0,itemSum:0,currency:'EUR',shippingCountryId:1,customerId:0,itemWishListIds:[]}}}));}
 res.setHeader('Content-Type','text/html');
 if(u.searchParams.get('quickViewItem')==='true'&&!u.searchParams.has('page'))return res.end('<article class="single"><h2 data-local-quickview>Local quickview</h2></article>');
 const before=u.pathname==='/before/';
 const next=u.searchParams.get('page')==='2';
 const cards=read(D+'/cards-de-'+(before?'before':next?'next':'after')+'.html');
 const listing='<main id="page-body"><section class="categoriegrid page-content container"><div class="row"><div class="col-12">'+cards+'</div></div></section></main>';
 res.end(`<!doctype html><html lang="de"><head><meta charset="utf-8"><title>Card flow fixture</title><style>${fonts+read(before?D+'/baseline/main.min.css':root+'/resources/css/main.min.css')}</style></head><body><script type="application/json" id="app-data-custom">{"templateType":"category.item","language":"de","currency":"EUR"}</script>${listing}${before?'':dialog}${next?'':'<a class="pagination__next" href="'+u.pathname+'?page=2&amp;quickViewItem=true">Next</a>'}<button id="totop" hidden>Top</button><div class="modal fade modal-cross" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">Close</button></div><div class="modal-body"></div></div></div></div><script src="/assets/jquery-3.7.1.min.js"></script>${before?'<script src="/baseline/cat.min.js" defer></script>':'<script src="/assets/core.min.js" data-eg-magiczoom-src="/assets/magiczoom.min.js" defer></script><script src="/assets/cat.min.js" defer></script>'}<script>window.activeLang='de';window.messageServiceTranslations={searchexample:'Search'};addEventListener('load',()=>{eGuardians.initialize();window.O46Core?.basketHeader.start();window.fixtureReady=true})</script></body></html>`);
});
(async()=>{
 await new Promise(r=>server.listen(0,'127.0.0.1',r));origin='http://127.0.0.1:'+server.address().port;
 const browser=await chromium.launch({executablePath:process.env.O46_BROWSER_EXECUTABLE});
 try{
   if (fs.existsSync(D+'/cards-de-before.html')) {
     const metrics = [];
     for (const width of [390,1440]) for (const [version,route] of [['vorher','/before/'],['nachher','/category/']]) {
       const page=await browser.newPage({viewport:{width,height:900}});
       await page.route('**/*',r=>r.request().url().startsWith(origin)?r.continue():r.abort());
       const jsErrors=[]; page.on('pageerror',error=>jsErrors.push(error.message));
       await page.goto(origin+route);
       if(jsErrors.length) throw Error(version+': '+jsErrors.join(' / '));
       await page.waitForFunction(()=>window.fixtureReady,{},{timeout:5000});
       await page.locator('.cat-item picture img').first().evaluate(img=>img.decode());
       await page.evaluate(()=>document.fonts.ready);
       await page.screenshot({path:path.join(D,version+'-'+width+'.png')});
       const numbers=await page.locator('.category-product-list').evaluate(grid=>({cards:grid.querySelectorAll('.cat-item').length,nodes:grid.querySelectorAll('*').length,htmlBytes:new TextEncoder().encode(grid.outerHTML).length}));
       assert.equal(numbers.cards,12);
       metrics.push({width,version,...numbers});
       await page.close();
     }
     fs.writeFileSync(D+'/visual-comparison.json',JSON.stringify(metrics,null,2));
   }
   for(const route of ['/category/','/search/'])for(const width of [320,390,430,768,991,992,1024,1440,1774]){
   requests=[];const page=await browser.newPage({viewport:{width,height:900}}),errors=[];
   page.on('pageerror',e=>errors.push(e.message));
   await page.route('**/*',r=>r.request().url().startsWith(origin)?r.continue():r.abort());
   await page.goto(origin+route);await page.waitForFunction(()=>window.fixtureReady);
   assert.equal(await page.locator('.cat-item').count(),12);
   const geometry=()=>page.locator('.cat-item').evaluateAll(es=>es.map(e=>[e.getBoundingClientRect().width,e.getBoundingClientRect().height,e.querySelector('.cat-item-image').getBoundingClientRect().height,e.querySelector('.linkabso').getBoundingClientRect().height]));
   const first=await geometry();
   const withoutHearts=await page.addStyleTag({content:'.eg-card-wish{display:none}'});
   assert.deepEqual(await geometry(),first,'Wishlist overlays must preserve every card, image and purchase button dimension');
   await withoutHearts.evaluate(style=>style.remove());
   const hearts=page.locator('.eg-card-wish button');
   assert.equal(await hearts.count(),12);
   for (const host of await page.locator('.cat-item-image').all()) {
     const fits = await host.evaluate(image => {
       const box=image.getBoundingClientRect(), button=image.querySelector('.eg-wish-heart').getBoundingClientRect();
       return button.width>=44 && button.height>=44 && button.right<=box.right && button.bottom<=box.bottom &&
         [...image.querySelectorAll('.cat-badges,.cat-logo,.item-action2')].every(el=>{
           const r=el.getBoundingClientRect(); return r.right<=button.left || r.bottom<=button.top || r.left>=button.right;
         });
     });
     assert.ok(fits,'Wishlist target overlaps an existing badge at '+width);
   }
   if(route==='/category/' && [390,1440].includes(width)){
     await page.locator('.cat-item picture img').first().evaluate(img=>img.decode());
     await page.screenshot({path:path.join(D,'karten-'+width+'.png')});
   }
   if(route==='/category/' && [390,1440].includes(width)){
     const logo=page.locator('.cat-logo').first();
     const original=await logo.getAttribute('src');
     for(const [w,h] of [[600,60],[24,48]]) {
       await logo.evaluate(async (img,[w,h])=>{
         const svg='<svg xmlns="http://www.w3.org/2000/svg" width="'+w+'" height="'+h+'"><rect width="100%" height="100%" fill="#333"/></svg>';
         img.src='data:image/svg+xml,'+encodeURIComponent(svg); await img.decode();
       },[w,h]);
       assert.ok(await logo.evaluate(img=>img.getBoundingClientRect().bottom <
         img.closest('.cat-item-image').querySelector('.eg-wish-heart').getBoundingClientRect().top));
       assert.deepEqual(await geometry(),first,'Logo aspect ratio changed the card geometry');
     }
     await logo.evaluate((img,src)=>{img.src=src;},original);
   }
   if(width===1440){
     const button=page.locator('.cat-item button.linkabso').first();
     const gradient=el=>getComputedStyle(el).backgroundImage;
     const restingGradient=await button.evaluate(gradient);
     assert.match(restingGradient,/^linear-gradient\(/);
     await button.hover();
     assert.equal(await button.evaluate(gradient),restingGradient,'Hover must keep the gradient painted while the darker green fades in');
   }
   if(width<992){
     const bounds=await page.locator('.cat-item').evaluateAll(cards=>cards.slice(0,2).map(card=>card.getBoundingClientRect().toJSON()));
     const [left,right]=bounds;
     assert.equal(left.top,right.top,'Two cards per row');
     assert.equal(left.width,right.width,'Equal mobile columns');
     assert.equal(left.x,5);
     assert.equal(width-right.right,5);
     assert.equal(right.x-left.right,6);
     assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth),width);
     const footers=await page.locator('.cat-item').evaluateAll(cards=>cards.map(card=>{
       const action=card.querySelector('.linkabso'),box=card.getBoundingClientRect(),button=action.getBoundingClientRect();
       return [button.left-box.left,box.right-button.right,box.bottom-button.bottom,button.height,getComputedStyle(action).borderRadius];
     }));
     for(const [left,right,bottom,height] of footers){assert.ok(left>=0&&left<=2&&right>=0&&right<=2&&bottom>=0&&bottom<=2,'Button follows the card edges');assert.equal(height,48,'Mobile action height');}
   }
   await page.evaluate(()=>InfiniteScroll.data(document.querySelector('.category-product-list')).loadNextPage());
   await page.waitForFunction(()=>document.querySelectorAll('.cat-item').length===24);
   assert.equal(await hearts.count(),24);
   if(route==='/category/' && width===390) {
     const appended=page.locator('.cat-item').nth(12);
     await appended.scrollIntoViewIfNeeded();
     // IntersectionObserver assigns the lazy picture source after scrolling.
     // Calling decode() on its initial empty URL rejects before that callback.
     await page.waitForFunction(() => {
       const image = document.querySelectorAll('.cat-item picture img')[12];
       return image?.currentSrc && image.complete && image.naturalWidth > 0;
     });
     await appended.locator('picture img').evaluate(img=>img.decode());
     await page.screenshot({path:path.join(D,'nachgeladen-390.png')});
   }
   for(const n of [12,13,18,23]){const photo=page.locator('.cat-item picture img').nth(n);await photo.scrollIntoViewIfNeeded();await photo.evaluate(i=>new Promise((resolve,reject)=>{if(i.complete&&i.naturalWidth)return resolve();i.onload=resolve;i.onerror=()=>reject(Error('Appended image failed'));}));}
   const ids=await page.locator('[data-eg-item]').evaluateAll(cards=>cards.map(card=>card.dataset.egItem));
   assert.equal(new Set(ids).size,24,'No duplicate items after append');
   const all=await geometry();assert.deepEqual(all.slice(12),first,'Appended lazy cards match initial card geometry');
   if(process.argv.includes('--bottom-align')){
     const cards=await page.locator('.cat-item').evaluateAll(es=>es.map(e=>({y:Math.round(e.getBoundingClientRect().y),bottoms:['.cat-item-name','.cat-card-price','.cat-card-price>.outlet-green','.linkabso','.cat-card-price>.font-12'].map(s=>e.querySelector(s)?.getBoundingClientRect().bottom??null)})));
     for(const y of new Set(cards.map(c=>c.y))){const line=cards.filter(c=>c.y===y);for(let part=0;part<5;part++){const bottoms=line.map(c=>c.bottoms[part]).filter(v=>v!==null);if(bottoms.length>1)assert.ok(Math.max(...bottoms)-Math.min(...bottoms)<2,'Initial and appended names, RRP, sale prices and buttons align');}}
     await page.evaluate(()=>scrollTo(0,0));
     const clip=await page.locator('.cat-item').nth(12).evaluate((e,width)=>{const r=e.getBoundingClientRect();return {x:0,y:r.y-12,width,height:r.height+24}},width);
     await page.screenshot({path:D+'/append-'+route.replaceAll('/','')+'-'+width+'.png',fullPage:true,clip});
   }
   assert.equal(requests.filter(r=>r.path===route+'?page=2&quickViewItem=true').length,1,'One next-page request');
   const button=page.locator('.cat-item .linkabso').nth(12);await button.click();
   await page.waitForFunction(()=>!!document.querySelector('.modal-cross.show [data-local-quickview]'));
   assert.equal(requests.filter(r=>r.path.includes('quickViewItem=true')&&!r.path.includes('page=2')).length,1);
   assert.equal(requests.filter(r=>r.method!=='GET').length,0,'No basket writes');assert.deepEqual(errors,[]);
   rows.push({route,width,status:'PASS',initialCards:12,afterAppend:24,matchedCards:12,quickview:true,errors,requests});await page.close();
 }}finally{await browser.close();server.close();fs.writeFileSync(D+'/card-flow.json',JSON.stringify(rows,null,2));}
 console.log(JSON.stringify({status:'PASS',cases:rows.length,appendedCards:rows.length*12,quickviews:rows.length}));
})().catch(e=>{console.error(e);server.close();process.exitCode=1});

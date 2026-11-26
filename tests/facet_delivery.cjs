// node tests/facet_delivery.cjs <facet_delivery.php output> <JSON receipt>
const fs=require('node:fs'),assert=require('node:assert/strict'),path=require('node:path');
const {chromium}=require('playwright-core');
const data=JSON.parse(fs.readFileSync(process.argv[2]));
const bundle=fs.readFileSync(path.join(__dirname,'../resources/js/facets.min.js'),'utf8');
const contents='<script type="application/json" data-eg-facet-data>{"selected":[],"values":{},"texts":{}}</script><div data-eg-facet-group="1" data-eg-facet-rendered="1"><div class="eg-dropdown"><button data-eg-facet-toggle aria-expanded="false">Größe</button></div></div>';
async function main(){
    const browser=await chromium.launch(),results=[];
    try{
        for(const mode of ['absent','SSR','insert','progressive','remount']){
            const ctx=await browser.newContext({serviceWorkers:'block'}),page=await ctx.newPage();let requests=0;
            const errors=[];page.on('pageerror',e=>errors.push(e.message));
            const root='<section data-eg-compact-facets>'+contents+'</section>';
            await ctx.route('**/*',r=>{
                const u=new URL(r.request().url());
                assert.equal(u.origin,'https://fixture.test');
                if(u.pathname==='/')return r.fulfill({contentType:'text/html',body:'<body><main>'+(mode==='SSR'||mode==='remount'?root:'')+'</main>'+data.loader+'</body>'});
                assert.match(u.pathname,/\/facets\.min\.js$/);requests++;
                return r.fulfill({contentType:'application/javascript',body:bundle});
            });
            await page.goto('https://fixture.test/');
            if(mode==='insert')await page.locator('main').evaluate((m,markup)=>m.innerHTML=markup,root);
            if(mode==='progressive'){
                await page.locator('main').evaluate(m=>m.innerHTML='<section data-eg-compact-facets></section>');
                await page.locator('section').evaluate((s,markup)=>s.innerHTML=markup,contents);
            }
            if(mode==='absent'){
                await page.locator('main').evaluate(m=>m.append(document.createElement('form')));
                await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(resolve)));
                assert.equal(requests,0);
            }else{
                const button=page.locator('[data-eg-facet-toggle]');
                await page.waitForFunction(()=>!!window.egFacets);
                if(mode==='remount')await page.locator('main').evaluate((m,markup)=>m.innerHTML=markup,root);
                await button.click();
                assert.equal(await button.getAttribute('aria-expanded'),'true');
                assert.equal(requests,1);
            }
            assert.deepEqual(errors,[]);results.push({mode,requests,errors});await ctx.close();
        }
        fs.writeFileSync(process.argv[3],JSON.stringify(results,null,2));console.log(results.length+' browser delivery scenarios passed');
    } finally{await browser.close();}
}
main().catch(e=>{console.error(e);process.exitCode=1});

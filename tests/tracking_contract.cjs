// Usage: node tests/tracking_contract.cjs <before.json> <after.json> <receipt.json>
const fs = require('node:fs');
const assert = require('node:assert/strict');
const {chromium} = require('playwright-core');
const [beforeFile, afterFile, receipt] = process.argv.slice(2);
const before = JSON.parse(fs.readFileSync(beforeFile));
const after = JSON.parse(fs.readFileSync(afterFile));
const targets = {34:'1650743749',46:'1963991560',47:'1968260932',48:'1970752041'};
const programs = {34:1650746450,46:1964449345,47:1968267155,48:1970752346};
const fields = ['title','firstName','lastName','company','street','houseNumber','additionalAddressLine','postalCode','city','country','email','combinedPhone','birthdate','birthplace','nationality'];
const names = ['Été & "Öl" + 50% / 帽子',"Crème d'été",'Leinen'];
async function main() {
    const browser = await chromium.launch();
    const output = {twig:after.twig, cases:{}, externalTransmissions:0, providerConversions:0};
    try {
        const context = await browser.newContext({serviceWorkers:'block'});
        const page = await context.newPage();
        let body, requests, errors;
        await context.route('**/*', route => {
            const url = route.request().url();
            if (url === 'https://fixture.test/case') return route.fulfill({contentType:'text/html; charset=utf-8',body});
            requests.push(url);
            // Only a local probe answers. It records the object as seen when this script executes.
            const target=new URL(url);
            const adtraction = ['pin.outlet46.de','to.outlet46.de','go.outlet46.de','at.outlet46.de'].includes(target.hostname)
                && target.pathname==='/jsTag';
            const salenti = target.hostname==='salenti.de' && target.pathname.endsWith('/init.js');
            if(route.request().resourceType()==='script' && (adtraction || salenti)){
                const record = adtraction ? 'window.ADT?.Tag ?? null' : 'window.customer ?? null';
                return route.fulfill({contentType:'application/javascript',
                    body:'window.fixtureReceipts.push({target:document.currentScript.src,data:structuredClone('+record+')});'});
            }
            return route.abort();
        });
        page.on('pageerror', error => errors.push(error.message));
        async function capture(input, key) {
            requests=[]; errors=[];
            const setup='<!doctype html><html><head><meta charset="utf-8"><script data-fixture-setup>window.fixtureOriginal=window.customer={};window.fixtureReceipts=[];</script>';
            const inBody=key.startsWith('salenti-')||key.startsWith('legacy-');
            body=setup+(inBody?'</head><body>'+input.html:input.html+'</head><body>')+'</body></html>';
            await page.goto('https://fixture.test/case',{waitUntil:'load'});
            if (key.startsWith('salenti') && input.html.includes('data-record')) {
                await page.waitForFunction(()=>window.fixtureReceipts.length===1);
            }
            const state=await page.evaluate(()=>{
                const events=(window.dataLayer||[]).map(entry=>Array.from(entry).map(x=>x instanceof Date?'[Date]':x));
                const scripts=[...document.scripts].filter(s=>!s.hasAttribute('data-fixture-setup')).map(s=>({
                    kind:s.src?'external':'inline',src:s.src||null,defer:s.defer,
                    consent:s.getAttribute('data-cookie-consent'),record:s.getAttribute('data-record'),target:s.getAttribute('data-target')
                }));
                return {scripts,events,receipts:window.fixtureReceipts,adt:window.ADT?.Tag||null,customer:window.customer,
                    sameObject:window.fixtureOriginal===window.customer,n2g:(window.n2g?.q||[]).map(q=>Array.from(q)),
                    pixel:document.querySelector('img')?.src||null};
            });
            return {...state,blocked:[...requests],errors:[...errors],variationCalls:input.calls,contextCalls:input.access};
        }
        assert.deepEqual(Object.keys(before.cases),Object.keys(after.cases));
        for(const [key,data] of Object.entries(after.cases)){
            const old=await capture(before.cases[key],key);
            const next=await capture(data,key);
            assert.deepEqual(next.errors,[],key+' errors');
            assert.deepEqual(next.contextCalls,old.contextCalls);
            assert.deepEqual(next.events,old.events);
            assert.deepEqual(next.n2g,old.n2g);
            if(key.startsWith('idealo-clean-')){
                const a=new URL(old.pixel), b=new URL(next.pixel);
                assert.equal(a.origin+a.pathname,b.origin+b.pathname);
                assert.deepEqual([...a.searchParams],[...b.searchParams]);
                assert.deepEqual(JSON.parse('['+a.searchParams.get('bsk')+']'),JSON.parse('['+b.searchParams.get('bsk')+']'));
                assert.deepEqual(old.blocked,[old.pixel]);
                assert.deepEqual(next.blocked,[next.pixel]);
                assert.deepEqual(old.variationCalls,[101,202,101]);assert.deepEqual(next.variationCalls,[101,202]);
            } else if(key.startsWith('idealo-')){
                const url=new URL(next.pixel), params=url.searchParams;
                assert.equal(url.host,'marketing.net.idealo-partner.com');
                assert.equal(url.pathname,'/ts/i5619682/tsa');
                assert.deepEqual([...params.keys()],['typ','trc','ctg','sid','cid','orv','orc','bsk']);
                const positions=JSON.parse('['+params.get('bsk')+']');
                assert.deepEqual(positions.map(p=>p.prn),names);
                assert.deepEqual(positions.map(p=>p.pid),['101','202','101']);
                assert.deepEqual(positions.map(p=>p.qty),['2','1','1']);
                assert.deepEqual(positions.map(p=>p.pri),['19.95','15.6','10']);
                for(const p of positions)assert.equal(p.brn,'Marque & Fils '+p.pid);
                const oldParams=new URL(old.pixel).searchParams;
                assert.throws(()=>JSON.parse('['+oldParams.get('bsk')+']'),SyntaxError);
                for(const k of ['typ','trc','ctg','sid','cid','orc','orv'])assert.equal(params.get(k),oldParams.get(k));
                assert.equal(params.get('orc'),'EUR');assert.equal(params.get('orv'),'60.5');
                assert.deepEqual(old.variationCalls,[101,202,101]);assert.deepEqual(next.variationCalls,[101,202]);
                assert.equal(next.blocked.length,1);
            } else if(key==='voucher-only'){
                for(const version of [old,next]){
                    assert.equal(new URL(version.pixel).searchParams.get('bsk'),'');
                    assert.equal(new URL(version.pixel).searchParams.get('orv'),'0');
                    assert.deepEqual(version.variationCalls,[]);
                }
            } else if(key==='mixed-networks'){
                assert.ok(next.pixel);
                assert.deepEqual(next.scripts,old.scripts);
                assert.equal(next.scripts.filter(s=>s.src).length,1);
                assert.equal(new URL(next.scripts.find(s=>s.src).src).searchParams.get('ap'),targets[34]);
                assert.equal(next.adt,null);
                assert.deepEqual(next.receipts,old.receipts);
            } else if(key.startsWith('adtraction-')){
                const [,id,mode,discount]=key.split('-');
                const different=mode.startsWith('different');
                const ext=v=>v.scripts.filter(s=>s.src);
                const expected=mode==='same'?old.scripts.slice(0,-1):old.scripts;
                assert.deepEqual(next.scripts,expected,key+' order, sources and consent');
                assert.deepEqual(next.adt,old.adt);
                const previous=mode==='same'?old.receipts.slice(0,1):old.receipts;
                assert.deepEqual(next.receipts,previous,key+' data at simulated partner');
                assert.equal(next.receipts.length,different?2:1);
                if(mode==='same')assert.deepEqual(old.receipts,[...next.receipts,...next.receipts]);
                for(const receipt of next.receipts)assert.deepEqual(receipt.data,next.adt);
                assert.equal(ext(next).length,different?2:1);
                assert.equal(new URL(ext(next)[0].src).searchParams.get('ap'),targets[id]);
                if(different){
                    const otherPartner=mode.slice('different'.length);
                    assert.equal(new URL(ext(next)[1].src).searchParams.get('ap'),targets[otherPartner]);
                }
                if(mode==='url')assert.equal(next.adt,null);
                else{
                    assert.equal(next.scripts[0].kind,'inline');
                    assert.deepEqual(next.adt,{t:3,c:({34:'EUR',46:'CHF',47:'USD',48:'EUR'})[id],tp:programs[id],
                        am:discount==='1'?12.1:65.5,cpn:discount==='1'?'SOMMER-5':'',ti:'42'});
                }
            } else if(key.startsWith('salenti-')){
                assert.deepEqual(next.scripts,old.scripts);
                assert.ok(next.sameObject);
                if(['salenti-other-category','salenti-order-param'].includes(key))assert.deepEqual(next.customer,{});
                else {
                    assert.deepEqual(Object.keys(next.customer),fields);
                    assert.equal(next.customer.firstName,key==='salenti-absent'?'':key==='salenti-unicode'?"Zoë & D'Art\n試":'Anna');
                    assert.equal(next.customer.postalCode,key==='salenti-absent'?'':'00123');
                    if(key==='salenti-unicode'){
                        assert.ok(old.errors.length>0);
                        assert.equal(next.customer.lastName,'Muster "Été"');
                    }
                    assert.equal(next.customer.country,'DE');
                    assert.equal(next.scripts.at(-1).record,'customer');
                    assert.equal(next.scripts.at(-1).target,'salenti');
                    assert.deepEqual(next.receipts[0].data,next.customer);
                    if(key!=='salenti-unicode'){
                        assert.deepEqual(next.customer,old.customer);
                        assert.deepEqual(next.receipts,old.receipts);
                    }
                }
            } else {
                assert.equal(before.cases[key].html,data.html);
                assert.deepEqual(next.events,old.events);assert.deepEqual(next.n2g,old.n2g);
                const buys=next.events.filter(x=>x[0]==='event'&&x[1]==='purchase');
                assert.equal(buys.length,/legacy-(EUR|CHF|USD)/.test(key)?2:0);
                if(buys.length){assert.notEqual(buys[0][2].send_to,buys[1][2].send_to);assert.equal(buys[0][2].tax,16);}
            }
            output.cases[key]={before:old,after:next};
        }
        const anonymous = await browser.newContext({serviceWorkers:'block'});
        try {
            const isolated = await anonymous.newPage();
            await anonymous.route('**/*', route => route.abort());
            await Promise.all([
                capture(after.cases['salenti-unicode'],'salenti-unicode'),
                isolated.setContent(after.cases['salenti-absent'].html)
            ]);
            assert.equal(await page.evaluate(()=>window.customer.firstName),"Zoë & D'Art\n試");
            assert.equal(await isolated.evaluate(()=>window.customer.firstName),'');
            output.sessionIsolation = 'Two isolated browser contexts retained distinct synthetic addresses.';
        } finally {
            await anonymous.close();
        }
        fs.writeFileSync(receipt,JSON.stringify(output,null,2));
        console.log(Object.keys(output.cases).length+' browser comparisons passed; all non-fixture requests intercepted');
    } finally {await browser.close();}
}
main().catch(error=>{console.error(error);process.exitCode=1;});

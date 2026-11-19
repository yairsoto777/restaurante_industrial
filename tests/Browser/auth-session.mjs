// Browser event checks without a server, database, dependencies or real credentials.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source=fs.readFileSync('public/js/auth-session.js','utf8');
const settle=()=>new Promise(resolve=>setImmediate(resolve));
function browser(fetchImpl) {
    const events={}, windowEvents={}, token={value:'expired-token'}, button={disabled:false};
    let postedToken=null,reloaded=false,replaced=null;
    const form={
        action:'https://nutriliado.example.test/login',
        addEventListener:(name,callback)=>{events[name]=callback;},
        querySelector:()=>token,
        requestSubmit:submitter=>{
            let prevented=false;
            events.submit({submitter,preventDefault:()=>{prevented=true;}});
            if(!prevented) postedToken=token.value;
        },
    };
    vm.runInNewContext(source,{
        document:{querySelector:()=>form},fetch:fetchImpl,
        window:{addEventListener:(name,callback)=>{windowEvents[name]=callback;},location:{reload:()=>{reloaded=true;},replace:url=>{replaced=url;}}},
    });
    return {submit:()=>form.requestSubmit(button),pageshow:persisted=>windowEvents.pageshow({persisted}),button,get postedToken(){return postedToken;},get reloaded(){return reloaded;},get replaced(){return replaced;}};
}

let resolveFetch,requests=0;
const page=browser((url,options)=>{
    requests++;
    assert.equal(options.method,'GET');
    assert.equal(options.credentials,'same-origin');
    assert.equal(options.cache,'no-store');
    assert.equal(options.redirect,'error');
    assert.equal(options.body,undefined);
    return new Promise(resolve=>{resolveFetch=resolve;});
});
page.submit();page.submit();
assert.equal(requests,1);
assert.equal(page.postedToken,null);
resolveFetch({ok:true,json:async()=>({csrf_token:'current-session-token'})});
await settle();
assert.equal(page.postedToken,'current-session-token');
assert.equal(page.button.disabled,false);
page.pageshow(false);assert.equal(page.reloaded,false);
page.pageshow(true);assert.equal(page.reloaded,true);

const failed=browser(async()=>{throw new Error('offline');});
failed.submit();await settle();
assert.equal(failed.postedToken,null);
assert.equal(failed.replaced,'https://nutriliado.example.test/login');
assert.equal(failed.button.disabled,false);
console.log('PASS: fresh token before POST, duplicate-submit protection, restored-page reload and safe network failure.');

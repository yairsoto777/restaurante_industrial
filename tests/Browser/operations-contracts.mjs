// Compare the presentation work with the local pre-redesign snapshot. Read-only.
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
const baseline='storage/app/private/ui-redesign-baseline';
if(!fs.existsSync(baseline))throw new Error('Local pre-redesign snapshot required');
const walk=dir=>fs.readdirSync(dir,{withFileTypes:true}).flatMap(entry=>entry.isDirectory()?walk(path.join(dir,entry.name)):[path.join(dir,entry.name)]);
const canonical=value=>value.replace(/\s+/g,' ').trim();
let views=0,forms=0,fields=0,scripts=0;
const changed=[];
for(const source of walk(path.join(baseline,'controllers'))){
    const target=path.join('app/Http/Controllers',path.relative(path.join(baseline,'controllers'),source));
    assert.equal(fs.readFileSync(target,'utf8'),fs.readFileSync(source,'utf8'),target);
}
assert.equal(fs.readFileSync('routes/web.php','utf8'),fs.readFileSync(path.join(baseline,'web.php'),'utf8'));
assert.equal(fs.readFileSync('resources/js/app.js','utf8'),fs.readFileSync(path.join(baseline,'app.js'),'utf8'));
const waiterProtection='storage/app/private/waiter-protected-hashes.json';
if(fs.existsSync(waiterProtection)){
    const {createHash}=await import('node:crypto');
    const entries=JSON.parse(fs.readFileSync(waiterProtection,'utf8').replace(/^\uFEFF/,''));
    for(const entry of entries)assert.equal(createHash('sha256').update(fs.readFileSync(entry.Path)).digest('hex').toUpperCase(),entry.Hash,entry.Path+' changed during waiter redesign');
    console.log(`PASS: ${entries.length} Captain/Cashier/backend source files unchanged during waiter redesign.`);
}
for(const source of walk(path.join(baseline,'views'))){
    const target=path.join('resources/views',path.relative(path.join(baseline,'views'),source));
    assert.ok(fs.existsSync(target),target+' missing');
    const old=fs.readFileSync(source,'utf8');
    let current=fs.readFileSync(target,'utf8');
    if(target.replaceAll('\\','/').endsWith('mesero/index.blade.php'))current+='\n'+fs.readFileSync('resources/views/mesero/partials/assigned-tables.blade.php','utf8');
    if(old===current)continue;
    changed.push(target);views++;
    const tagPattern=/<(form|input|select|textarea)\b((?:[^>"']|"[^"]*"|'[^']*')*)>/g;
    const signatures=text=>[...text.matchAll(tagPattern)].map(([,tag,attributes])=>{
        const attrs=[...attributes.matchAll(/\b(name|action|method|type|min|max|step|minlength|maxlength|pattern)\s*=\s*"([^"]*)"/g)].map(([,key,value])=>key+'='+canonical(value)).sort();
        if(!attrs.length)return null;
        return tag+' '+attrs.join(' ');
    }).filter(Boolean);
    const available=signatures(current);
    for(const signature of signatures(old)){
        const index=available.indexOf(signature);
        assert.ok(index>=0,target+' lost field/form contract: '+signature);
        available.splice(index,1);
        signature.startsWith('form ')?forms++:fields++;
    }
    for(const token of ['@csrf','@method('])assert.ok(current.split(token).length>=old.split(token).length,target+' lost '+token);
    const oldScripts=[...old.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(match=>match[1].replace(/\s/g,''));
    const newScripts=[...current.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(match=>match[1].replace(/\s/g,''));
    for(const script of oldScripts){assert.ok(newScripts.includes(script),target+' changed original inline JS');scripts++;}
}
console.log(`PASS: ${views} changed original views; ${forms} forms, ${fields} field contracts and ${scripts} inline scripts preserved. Controllers, routes and original app.js unchanged.`);
console.log(changed.join('\n'));

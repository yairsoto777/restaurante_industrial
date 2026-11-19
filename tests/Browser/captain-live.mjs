// New live-layout refresh contract, isolated from browser sessions and application data.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source=fs.readFileSync('public/js/captain-workspace.js','utf8');
const events={},updates=[],timestamp={textContent:''};
const search={value:''},zone={value:''};
let poll,version=1,selection,pressed,unavailable=false;
const button=()=>({dataset:{cwPick:'17'},hasAttribute:name=>name==='data-cw-pick',setAttribute:(name,value)=>{if(name==='aria-pressed')pressed=value;}});
const table=()=>({classList:{toggle:(name,active)=>{if(name==='is-selected')selection=active;}},querySelector:()=>({content:{cloneNode:()=>({table:17,version})}})});
let currentTable=table();
const panel={replaceChildren:detail=>{panel.detail=detail;}};
const section=selector=>({replaceWith:()=>{updates.push(selector);if(selector==='.cw-live-layout'){version=2;currentTable=table();}}});
const sections=Object.fromEntries(['.lv-service-facts','.cw-live-banner','.cw-live-layout'].map(selector=>[selector,section(selector)]));
const document={
    hidden:false,
    addEventListener:(type,handler)=>{events[type]=handler;},
    createTextNode:text=>({text}),
    getElementById:()=>null,
    querySelector(selector){
        if(selector==='.cw-page')return {dataset:{cwModule:'comedor'}};
        if(selector==='.lv-workspace')return {};
        if(selector==='[data-cw-search]')return search;
        if(selector==='[data-cw-zone-filter]')return zone;
        if(selector==='[data-cw-selected]')return panel;
        if(selector==='[data-cw-updated]')return timestamp;
        if(selector.startsWith('[data-cw-table-id='))return currentTable;
        return sections[selector] || null;
    },
    querySelectorAll(selector){if(selector==='.cw-map-table')return [currentTable];if(selector==='.lv-table-pick')return [button()];return [];},
};
vm.runInNewContext(source,{
    document,CSS:{escape:String},setInterval:handler=>{poll=handler;},location:{href:'https://nutriliado.example.test/capitan?modulo=comedor&id_servicio=4'},
    fetch:async()=>({ok:!unavailable,redirected:false,text:async()=>'<isolated-live-response>'}),
    DOMParser:class{parseFromString(){return {querySelector:selector=>selector==='.lv-workspace'?{}:({section:selector})};}},
});
events.click({target:{closest:()=>button()}});
assert.equal(selection,true);assert.equal(pressed,'true');assert.equal(panel.detail.version,1);
await poll();
assert.deepEqual(updates,['.lv-service-facts','.cw-live-banner','.cw-live-layout']);
assert.equal(panel.detail.table,17);assert.equal(panel.detail.version,2);
assert.equal(selection,true);assert.equal(pressed,'true');
assert.match(timestamp.textContent,/Actualizado/);
assert.equal(search.value,'');assert.equal(zone.value,'');
unavailable=true;await poll();
assert.equal(updates.length,3);assert.equal(panel.detail.version,2);
assert.match(timestamp.textContent,/Sin actualizar/);
console.log('PASS: service facts, metrics and live layout refresh; selected table survives; failed refresh preserves the last view.');

// Focused browser-event simulation: no server, real users, database or external packages.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source=fs.readFileSync('public/js/captain-assignments.js','utf8');
const element=()=>({hidden:false,disabled:false,value:'',textContent:'',listeners:{},dataset:{},classList:{toggle(){},add(){},remove(){}},addEventListener(name,fn){this.listeners[name]=fn;},replaceChildren(){},focus(){},setAttribute(){},querySelector(){return null;}});
const option=(value,name,dataset={})=>({value,textContent:name,dataset});
const select=options=>{
    const result=element();result.options=options;result.value=options[0]?.value || '';
    result.replaceChildren=()=>{result.options=[];result.value='';};
    result.append=items=>{result.options.push(...(Array.isArray(items)?items:[items]));if(!result.value)result.value=result.options[0]?.value || '';};
    Object.defineProperty(result,'selectedOptions',{get:()=>result.options.filter(item=>item.value===result.value)});
    return result;
};
async function scenario({rejectNew=false,rejectRetire=false,confirm=true}={}){
    const calls=[],confirmed=[],feedback=element(),refresh=element();refresh.hidden=true;
    const zone=select([option('1','Zona A')]);
    const current=select([]),waiter=select([option('','Selecciona'),option('9','Mesero nuevo')]);
    const assign=element(),change=element(),remove=element();
    const assignForm={...element(),action:'https://nutriliado.example.test/capitan/asignaciones',reportValidity:()=>true,kind:'assign'};
    const retireForm={...element(),action:'',kind:'retire'};
    const oldOption=option('15','Mesero anterior',{retireUrl:'https://nutriliado.example.test/capitan/asignaciones/15/retirar',personName:'Mesero anterior'});
    const templates={
        'data-av-zone-options:1':{content:{cloneNode:()=>[oldOption]}},
        'data-av-zone-profiles:1':{content:{querySelector:()=>({cloneNode:()=>({})})}},
        'data-av-waiter-profile:9':{content:{cloneNode:()=>({})}},
    };
    const card={...element(),dataset:{avZoneCard:'1'}};
    const nodes={'[data-av-assign-form]':assignForm,'[data-av-retire-form]':retireForm,'[data-av-selected-zone]':zone,'[data-av-current]':current,'[data-av-waiter]':waiter,'[data-av-feedback]':feedback,'[data-av-assign]':assign,'[data-av-change]':change,'[data-av-remove]':remove,'[data-av-refresh]':refresh,'[data-av-current-profile]':element(),'[data-av-new-profile]':element(),'[data-av-zone-filter]':element(),'[data-av-search]':element(),'[data-av-empty-filter]':element()};
    const root={dataset:{avEditable:'1',avFeedbackKey:'isolated-check'},querySelector(selector){const match=selector.match(/^template\[([^=]+)="([^"]*)"\]$/);return match?templates[match[1]+':'+match[2]] || null:nodes[selector] || null;},querySelectorAll:()=>[card]};
    class FormDataProbe{
        constructor(form){this.values=form.kind==='assign'?{id_zona:zone.value,id_usuario_mesero:waiter.value,id_servicio:'33',_token:'isolated'}:{_method:'PATCH',id_servicio:'33',_token:'isolated'};}
        get(name){return this.values[name];}
    }
    let destination;
    vm.runInNewContext(source,{
        document:{querySelector:()=>root,createElement:()=>element()},CSS:{escape:String},URL,FormData:FormDataProbe,
        sessionStorage:{getItem:()=>null,setItem(){},removeItem(){}},
        window:{confirm:text=>{confirmed.push(text);return confirm;},location:{hash:'',assign:url=>{destination=url;},reload(){}}},
        fetch:async(url,options)=>{
            calls.push({url,...options});const failure=calls.length===1?rejectNew:rejectRetire;
            return {ok:!failure,status:failure?422:200,url:'https://nutriliado.example.test/capitan?modulo=asignaciones&id_servicio=33',json:async()=>({message:'Asignación incompatible'})};
        },
    });
    assert.equal(assign.disabled,true);
    assert.equal(remove.disabled,false);
    waiter.value='9';waiter.listeners.change();
    assert.equal(assign.disabled,false);assert.equal(change.disabled,false);
    change.listeners.click();
    await new Promise(resolve=>setImmediate(resolve));
    return {calls,confirmed,feedback,refresh,assign,change,remove,destination};
}
const success=await scenario();
assert.equal(success.calls.length,2);
assert.ok(success.calls[0].url.endsWith('/asignaciones'));
assert.ok(success.calls[1].url.endsWith('/15/retirar'));
assert.equal(success.calls[0].body.get('id_zona'),'1');
assert.equal(success.calls[0].body.get('id_usuario_mesero'),'9');
assert.equal(success.calls[0].body.get('id_servicio'),'33');
assert.equal(success.calls[1].body.get('_method'),'PATCH');
assert.ok(success.destination.endsWith('#av-zone-1'));
assert.equal(success.confirmed.length,1);
const blocked=await scenario({rejectNew:true});
assert.equal(blocked.calls.length,1);assert.equal(blocked.destination,undefined);assert.match(blocked.feedback.textContent,/incompatible/);
const partial=await scenario({rejectRetire:true});
assert.equal(partial.calls.length,2);assert.equal(partial.destination,undefined);assert.match(partial.feedback.textContent,/nuevo mesero quedó asignado/);assert.equal(partial.refresh.hidden,false);assert.equal(partial.change.disabled,true);
const cancelled=await scenario({confirm:false});assert.equal(cancelled.calls.length,0);
console.log('PASS: jornada IDs, conditional actions, confirmation, replacement-before-retirement, rejection safety and partial-operation feedback.');

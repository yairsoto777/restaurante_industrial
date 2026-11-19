import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

// Exercise only this form's new behavior, without a browser/server or real records.
const source=fs.readFileSync('public/js/captain-incidents.js','utf8');
const option=(value='',zoneId='')=>({value,dataset:{zoneId},textContent:'',cloneNode(){return option(this.value,this.dataset.zoneId);}});
const zone={value:'1',addEventListener(type,handler){this.change=handler;}};
const table={value:'11',options:[],disabled:false,replaceChildren(...items){this.options=items;},append(item){this.options.push(item);}};
const catalog={content:{querySelectorAll:()=>[option('11','1'),option('12','1'),option('21','2')]}};
const errors={hidden:true,items:[],replaceChildren(){this.items=[];},append(item){this.items.push(item);},focus(){this.focused=true;}};
const evidence={name:'silla.jpg',type:'image/jpeg'};
const button={disabled:false};
const dialog={open:false,hasAttribute:()=>true,showModal(){this.open=true;}};
let submit,request,redirect;
const form={
    action:'/capitan/incidencias',description:'Una silla requiere revisión',evidence,
    querySelector:selector=>({'[data-cw-incident-zone]':zone,'[data-cw-incident-table]':table,'[data-cw-incident-table-options]':catalog,'[data-cw-incident-errors]':errors})[selector],
    closest:()=>dialog,addEventListener(type,handler){if(type==='submit')submit=handler;},
};
class FormDataSnapshot{
    constructor(form){this.entries={descripcion:form.description,id_zona:zone.value,evidencia:form.evidence};if(!table.disabled)this.entries.id_mesa=table.value;}
    get(name){return this.entries[name];}
}
let response={status:422,ok:false,json:async()=>({errors:{id_mesa:['Selecciona nuevamente la mesa.']}})};
vm.runInNewContext(source,{
    document:{querySelector:()=>form,createElement:tag=>tag==='option'?option():{textContent:''}},
    FormData:FormDataSnapshot,fetch:async(url,options)=>{request={url,...options};return response;},
    window:{location:{assign:url=>{redirect=url;}}},
});
assert.equal(dialog.open,true);
assert.deepEqual(table.options.map(item=>item.value),['','11','12']);
assert.equal(table.value,'11');
zone.value='2';zone.change();
assert.equal(table.value,'');
assert.deepEqual(table.options.map(item=>item.value),['','21']);
table.value='21';
await submit({preventDefault(){},submitter:button});
assert.equal(request.body.get('id_zona'),'2');
assert.equal(request.body.get('id_mesa'),'21');
assert.equal(request.body.get('evidencia'),evidence);
assert.equal(errors.hidden,false);
assert.equal(errors.items[0].textContent,'Selecciona nuevamente la mesa.');
assert.equal(form.description,'Una silla requiere revisión');
assert.equal(form.evidence,evidence);
assert.equal(table.value,'21');
assert.equal(redirect,undefined);
assert.equal(button.disabled,false);
response={status:201,ok:true,json:async()=>({redirect:'/capitan?modulo=incidencias'})};
await submit({preventDefault(){},submitter:button});
assert.equal(redirect,'/capitan?modulo=incidencias');
zone.value='';zone.change();
assert.equal(table.value,'');assert.equal(table.disabled,true);
assert.deepEqual(table.options.map(item=>item.value),['']);
console.log('PASS: tables filtered by zone, stale selection cleared, IDs submitted, inline errors retain file/input, general incident and successful redirect.');

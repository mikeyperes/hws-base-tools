const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
let checks = 0;
const check = (ok, name) => { assert.ok(ok, name); checks++; };
const script = fs.readFileSync(__dirname + '/../assets/newsletter/newsletter.js', 'utf8');
function setup() {
    const classes = new Set(), attrs = {}, status = {textContent: '', classList: {add: c => classes.add(c), remove: c => classes.delete(c)}};
    const button = {disabled: false}; let submit, resets = 0, calls = [], responses = [];
    const form = {dataset: {endpoint: '/newsletter', error: '<img onerror=alert(1)>Failure'}, querySelector: s => s.includes('status') ? status : s.includes('button') ? button : null,
        addEventListener: (s, fn) => submit = fn, setAttribute: (k,v) => attrs[k]=v, removeAttribute: k => delete attrs[k], reset: () => resets++};
    const sandbox = {document: {querySelectorAll: () => [form]}, location: {href: 'https://example.com/form'}, FormData: class {set(){}}, fetch: async (...args) => {calls.push(args); const r=responses.shift(); if(r instanceof Error) throw r; return r;}};
    vm.runInNewContext(script, sandbox);
    const response = (status, message, ok=true) => ({ok, json: async () => ({status, message, nonce:'fresh'})});
    return {classes, attrs, status, button, form, calls, responses, response, run: () => submit({preventDefault(){}}), resets: () => resets};
}
(async () => {
 const s=setup();
 for (const [state, cls, text] of [['created','success','Welcome'],['duplicate','duplicate','Already subscribed'],['error','error','Specific field error']]) {
   s.responses.push(s.response('', ''), s.response(state,text,state!=='error')); await s.run();
   check(s.status.textContent===text,'result text '+state);
   check(s.classes.size===1&&s.classes.has('hws-newsletter__status--'+cls),'one status class '+state);
   check(s.form.dataset.state===state,'form state '+state);
   check(!s.button.disabled&&!s.attrs['aria-busy'],'recovery '+state);
 }
 check(s.resets()===1,'only success resets form');
 s.responses.push(new Error('raw transport internals'));await s.run();
 check(s.status.textContent===s.form.dataset.error,'network failure uses configured text');
 check(s.classes.has('hws-newsletter__status--error'),'network error class');
 s.responses.push(s.response('', 'server internals',false));await s.run();
 check(s.status.textContent===s.form.dataset.error,'nonce fetch failure uses per-form text');
 s.responses.push({ok:true,json:async()=>{throw new SyntaxError('bad json');}});await s.run();
 check(s.status.textContent===s.form.dataset.error,'malformed response uses configured text');
 let release; s.responses.push(new Promise(resolve=>release=resolve));
 // Hold the first fetch: verify repeat clicks cannot start a second request.
 const pending=s.run(), before=s.calls.length; await s.run();
 check(s.calls.length===before&&s.button.disabled,'overlapping submission suppressed');
 check(s.classes.size===0&&!s.form.dataset.state,'pending clears stale result state');
 release(s.response('',''));s.responses.push(s.response('created','Recovered'));await pending;
 check(s.status.textContent==='Recovered'&&!s.button.disabled,'error to success recovery');
 check(s.calls[0][1].cache==='no-store','nonce bypasses cache');
 console.log(`PASS ${checks} JavaScript message assertions`);
})().catch(e=>{console.error(e);process.exit(1);});

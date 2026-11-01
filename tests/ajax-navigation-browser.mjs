import { createServer } from 'node:http';
import { readFileSync,mkdtempSync,rmSync,writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
const root=new URL('../',import.meta.url);
const js=readFileSync(new URL('assets/ajax-navigation/navigation.js',root)),turbo=readFileSync(new URL('assets/ajax-navigation/vendor/turbo.js',root));
const wav=Buffer.alloc(44+8000*120*2);wav.write('RIFF');wav.writeUInt32LE(wav.length-8,4);wav.write('WAVEfmt ',8);wav.writeUInt32LE(16,16);wav.writeUInt16LE(1,20);wav.writeUInt16LE(1,22);wav.writeUInt32LE(8000,24);wav.writeUInt32LE(16000,28);wav.writeUInt16LE(2,32);wav.writeUInt16LE(16,34);wav.write('data',36);wav.writeUInt32LE(wav.length-44,40);
const server=createServer((req,res)=>{
 const u=new URL(req.url,'http://localhost');
 if(['/navigation.js','/turbo.js'].includes(u.pathname)){res.setHeader('Content-Type','application/javascript');res.end(u.pathname==='/navigation.js'?js:turbo);return}
 if(u.pathname==='/audio.wav'){res.setHeader('Content-Type','audio/wav');res.end(wav);return}
 if(u.pathname==='/frame'){res.setHeader('Content-Type','text/html');res.end('<!doctype html><script>window.epoch=Math.random();</script><p>Permanent frame</p>');return}
 res.setHeader('Content-Type','text/html');
 const code='('+browserTest.toString()+')();';
 res.end('<!doctype html><html><head><title>'+u.pathname+u.search+'</title><meta name="hws-ajax-navigation" content="1"><meta name="turbo-cache-control" content="no-cache"><script data-turbo-eval="false">document.addEventListener("hws:page-fallback",e=>sessionStorage.fixtureFailure=e.detail.reason);window.addEventListener("load",()=>{if(sessionStorage.fixtureFailure){document.body.dataset.result="fail";document.querySelector("#result").textContent="fallback: "+sessionStorage.fixtureFailure;}});</script><script src="/navigation.js"  data-turbo-eval="false"></script><script src="/turbo.js" data-turbo-eval="false"></script></head><body><header><a href="/one">one</a><a href="/two">two</a><a href="/three">three</a></header><main id="content"><h1>'+u.pathname+u.search+'</h1><form action="/search" method="get"><input name="s" value="Craig"><button>search</button></form><form id="post" method="post" data-turbo="true"><button>post</button></form></main><aside id="player" data-turbo-permanent><audio src="/audio.wav"></audio><iframe src="/frame"></iframe></aside><pre id="result"></pre>'+(u.pathname==='/one'?'<script data-turbo-eval="false">'+code+'</script>':'')+'</body></html>');
});
function browserTest(){
 if(window.fixtureStarted)return;window.fixtureStarted=true;
 window.addEventListener('load',async()=>{
 const delay=ms=>new Promise(r=>setTimeout(r,ms));
 const assert=(v,m)=>{if(!v)throw Error(m)};
 const wait=()=>new Promise((resolve,reject)=>{let t=setTimeout(()=>reject(Error('page-changed timeout')),8000);document.addEventListener('hws:page-changed',()=>{clearTimeout(t);resolve()},{once:true})});
 const visit=async path=>{const done=wait();document.querySelector('a[href="'+path+'"]').click();await done};
 try{
 const bar=document.querySelector('#player'),audio=bar.querySelector('audio'),frame=bar.querySelector('iframe'),win=frame.contentWindow,epoch=win.epoch,doc=document;
 await audio.play();await delay(100);let position=audio.currentTime;
 const check=async path=>{await delay(150);assert(document===doc,'document replaced');assert(document.querySelector('#player')===bar,'permanent node replaced');assert(frame.contentWindow===win&&win.epoch===epoch,'iframe browsing context reloaded');assert(!audio.paused&&audio.currentTime>position,'audio stopped/reset');position=audio.currentTime;assert(document.querySelector('h1').textContent===path,'wrong destination content: '+document.querySelector('h1').textContent);assert(location.pathname+location.search===path,'wrong destination URL: '+location.href)};
 await visit('/two');await check('/two');history.replaceState(null,'',location.href);await visit('/three');await check('/three');await visit('/one');await check('/one');
 let done=wait();history.back();await done;await check('/three');
 done=wait();history.back();await done;await check('/two');
 done=wait();history.forward();await done;await check('/three');
 done=wait();history.forward();await done;await check('/one');
 done=wait();document.querySelector('form[method=get]').requestSubmit();await done;await check('/search?s=Craig');
 const form=document.querySelector('#post');let native=false;form.addEventListener('submit',event=>{native=form.dataset.turbo==='false';event.preventDefault()});form.requestSubmit();assert(native,'POST was allowed through Turbo');
 document.body.dataset.result='pass';document.querySelector('#result').textContent='PASS: three clicks, GET search, native POST, empty-state and normal back/forward, connected iframe and advancing real audio';
 }catch(error){document.body.dataset.result='fail';document.querySelector('#result').textContent=error.stack||String(error)}
 },{once:true});
}
await new Promise(r=>server.listen(0,'127.0.0.1',r));
const require=createRequire(import.meta.url);
let browser;
try {
 const puppeteer=require(process.env.HWS_PUPPETEER_PATH || 'puppeteer-core');
 browser=await puppeteer.launch({executablePath:process.env.CHROME_BIN||'/usr/bin/google-chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--mute-audio','--autoplay-policy=no-user-gesture-required']});
 const page=await browser.newPage();
 page.on('pageerror',error=>console.error('PAGE ERROR',error.message));
 await page.goto('http://127.0.0.1:'+server.address().port+'/one');
 await page.waitForFunction(()=>document.body.dataset.result,{timeout:30000});
 console.log(await page.$eval('#result',node=>node.textContent));
 if(await page.$eval('body',node=>node.dataset.result)!=='pass')process.exitCode=1;
} finally { await browser?.close();server.close(); }

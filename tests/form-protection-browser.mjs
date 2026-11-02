import fs from 'node:fs';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
import {launchIsolated} from '/home/hexawebsystems/public_html/code.hexawebsystems.com/packages/hexawebsystems/laravel-hexa-package-playwright/node/index.mjs';
const domain=process.env.HWS_FORM_TEST_DOMAIN;
if(!domain||!/^[a-z0-9.-]+$/.test(domain))throw new Error('Set HWS_FORM_TEST_DOMAIN to the authorized HWS-owned site.');
const root=process.env.HWS_FORM_PLUGIN_PATH;
if(!root||!root.endsWith('/wp-content/plugins/hws-base-tools'))throw new Error('Set the canonical HWS_FORM_PLUGIN_PATH.');
function wp(args){
 const r=spawnSync('hexa-run',[domain,'--','wp',...args],{encoding:'utf8'});
 if(r.status!==0)throw new Error('Fixture WP command failed: '+r.stderr+' '+r.stdout);
 return r.stdout;
}
const fixture=JSON.parse(wp(['option','get','hws_form_protection_acceptance_fixture','--format=json']));
const out='/tmp/forms-1-artifacts';fs.mkdirSync(out,{recursive:true,mode:0o700});
const browser=await launchIsolated();let evidence={};let failure;
try{
 const context=await browser.newContext({viewport:{width:1440,height:900}});
 const page=await context.newPage();
 const form=()=>page.locator('form').filter({has:page.locator('input[name="_wpcf7"][value="'+fixture.form_id+'"]')});
 const load=async()=>{
  const response=await page.goto(fixture.url,{waitUntil:'networkidle',timeout:60000});
  if(response.status()!==200)throw new Error('Acceptance page did not load.');
  await form().locator('input[name="hws_form_token"]').waitFor({state:'attached'});
  await page.waitForFunction(id=>document.querySelector('form input[name="_wpcf7"][value="'+id+'"]').closest('form').querySelector('input[name="hws_form_token"]').value.length>20,fixture.form_id);
 };
 const fill=async()=>{
  await form().locator('[name="your-name"]').fill('HWS Acceptance');
  await form().locator('[name="your-email"]').fill('acceptance@example.invalid');
  await form().locator('[name="your-message"]').fill('Controlled acceptance test, capture only, no external email.');
 };
 const submit=async()=>{
  const response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().includes('/contact-form-7/v1/contact-forms/'+fixture.form_id+'/feedback'),{timeout:30000});
  await form().getByRole('button',{name:'Send acceptance test',exact:true}).click();
  return (await response).json();
 };
 await load();await fill();
 await form().locator('[name^="hws_form_honeypots["]').evaluate(el=>{el.value='bot-filled-field';});
 const bot=await submit();if(bot.status!=='spam')throw new Error('Bot-style post was not blocked: '+JSON.stringify(bot));
 await page.screenshot({path:path.join(out,'form-protection-bot.png'),fullPage:false});
 await load();await page.waitForTimeout(3300);await fill();
 const normal=await submit();if(normal.status!=='mail_sent')throw new Error('Normal form did not reach captured mail: '+JSON.stringify(normal));
 const receipt=JSON.parse(wp(['option','get','hws_form_protection_acceptance_receipt','--format=json']));
 if(!receipt.captured||receipt.subject!==fixture.subject)throw new Error('Normal mail was not captured by the bounded fixture.');
 const blocked=JSON.parse(wp(['hws','forms','blocked','--limit=10']));
 if(!blocked.attempts.some(r=>r.form_key==='contact-form-7:'+fixture.form_id&&r.reason==='honeypot'))throw new Error('Blocked attempt was not readable by scanner command.');
 await page.screenshot({path:path.join(out,'form-protection-normal.png'),fullPage:false});
 evidence={passed:true,site:new URL(fixture.url).origin,form_id:fixture.form_id,bot_status:bot.status,normal_status:normal.status,mail_capture:receipt,external_email_sent:false,blocked_log_readable:true};
}catch(error){failure=error;evidence={passed:false,error:error.message};}
finally{
 await browser.close();
 try{
  const raw=wp(['eval-file',root+'/tests/fixtures/form-protection-cleanup.php']);
  evidence.cleanup=JSON.parse(raw.slice(raw.indexOf('HWS_ACCEPTANCE:')+15));
  if(!evidence.cleanup.cleaned||evidence.cleanup.protection_enabled)throw new Error('Fixture cleanup did not restore protection off.');
 }catch(error){failure=error;evidence.cleanup_error=error.message;}
 fs.writeFileSync(path.join(out,'form-protection-acceptance.json'),JSON.stringify(evidence,null,2));
 console.log(JSON.stringify(evidence));
}
if(failure)throw failure;

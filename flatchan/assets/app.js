/* FlatChan front-end: theme switcher, custom CSS, quoting, modals (no native dialogs), rich-text editor. */
(function(){
'use strict';
function $(s,r){return (r||document).querySelector(s)}
function $$(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s))}
function modal(msg,buttons){
 var m=document.createElement('div');m.className='fc-modal';var b=document.createElement('div'),p=document.createElement('p');p.textContent=msg;b.appendChild(p);
 buttons.forEach(function(x){var k=document.createElement('button');k.type='button';k.textContent=x[0];k.onclick=function(){m.remove();if(x[1])x[1]()};b.appendChild(k)});
 m.appendChild(b);document.body.appendChild(m);var f=b.querySelector('button');if(f)f.focus();
}
window.fcNotice=function(msg){modal(msg,[['OK']])};
document.addEventListener('DOMContentLoaded',function(){
 /* theme dropdown */
 var sel=$('#theme-sel');
 if(sel){var last=sel.value;sel.addEventListener('change',function(){
  if(sel.value==='__custom'){sel.value=last;openCss();return}
  last=sel.value;document.cookie='theme='+encodeURIComponent(sel.value)+';path=/;max-age=31536000;samesite=lax';
  var l=$('#theme-css');if(l)l.href='themes/'+sel.value+'.css';});}
 function openCss(){
  if($('.fc-css'))return;var d=document.createElement('div');d.className='fc-css';
  d.innerHTML='<b>Custom CSS</b> <small>(saved in this browser, applied on every page)</small><textarea spellcheck="false" placeholder="body{background:#fafcff}"></textarea><p><button type="button" data-a="save">Save</button> <button type="button" data-a="clear">Clear</button> <button type="button" data-a="close">Close</button></p>';
  var t=$('textarea',d);try{t.value=localStorage.getItem('fc_css')||''}catch(e){}
  d.addEventListener('click',function(e){var a=e.target.getAttribute&&e.target.getAttribute('data-a');if(!a)return;
   if(a==='close'){d.remove();return}
   var v=a==='clear'?'':t.value;if(a==='clear')t.value='';
   try{if(v)localStorage.setItem('fc_css',v);else localStorage.removeItem('fc_css')}catch(x){fcNotice('Your browser blocked local storage; custom CSS cannot be saved.');return}
   var s=$('#ucss');if(!s){s=document.createElement('style');s.id='ucss';document.head.appendChild(s)}s.textContent=v;});
  document.body.appendChild(d);
 }
 /* quoting */
 function quote(id){var t=$('#body');if(!t)return false;t.value+=(t.value&&!/\n$/.test(t.value)?'\n':'')+'>>'+id+'\n';t.focus();return true}
 document.addEventListener('click',function(e){
  var q=e.target.closest&&e.target.closest('a.qr');if(q&&$('#body')&&$('#postform input[name=t]')){if(quote(q.getAttribute('data-q')))e.preventDefault()}
  var sp=e.target.closest&&e.target.closest('.spoiler');if(sp)sp.classList.toggle('show');
  var c=e.target.closest&&e.target.closest('img.cimg');if(c)c.src=c.src.replace(/&r=\d+/,'&r='+Date.now());
  var th=e.target.closest&&e.target.closest('a.thumb');
  if(th&&!e.ctrlKey&&!e.metaKey&&!e.shiftKey){e.preventDefault();var im=$('img',th);if(im.getAttribute('data-ex')){im.src=th.getAttribute('data-thumb');im.removeAttribute('data-ex');im.style.maxWidth='';im.style.width='';im.style.height=''}
   else{im.setAttribute('data-ex','1');im.src=th.getAttribute('data-full');im.style.width='auto';im.style.height='auto';im.style.maxWidth='100%'}}
 });
 var m=location.hash.match(/^#q(\d+)$/);if(m&&$('#body')&&$('#postform input[name=t]')){quote(m[1]);history.replaceState(null,'',location.pathname+location.search+'#p'+m[1])}
 /* deletion password stored locally and re-used */
 $$('input[data-gen]').forEach(function(i){var k='fc_pw';try{var v=localStorage.getItem(k);if(!v){v=Math.random().toString(36).slice(2,12);localStorage.setItem(k,v)}i.value=v}catch(e){}});
 $$('input[data-pw]').forEach(function(i){try{i.value=localStorage.getItem('fc_pw')||''}catch(e){}});
 /* custom confirmation modal instead of confirm() */
 document.addEventListener('click',function(e){
  var b=e.target.closest&&e.target.closest('[data-confirm]');if(!b||b.getAttribute('data-ok'))return;e.preventDefault();
  modal(b.getAttribute('data-confirm'),[['Yes, continue',function(){b.setAttribute('data-ok','1');if(b.form&&b.form.requestSubmit)b.form.requestSubmit(b);else b.click()}],['Cancel']]);
 },true);
 /* rich-text rules editor */
 var rte=$('#rte');
 if(rte){
  $$('.rte-bar [data-cmd]').forEach(function(b){b.addEventListener('mousedown',function(e){e.preventDefault()});b.addEventListener('click',function(){
   var c=b.getAttribute('data-cmd');rte.focus();
   if(c==='link'){var u=$('#rte-url').value.trim();if(/^(https?:\/\/|\/|#|mailto:)/i.test(u))document.execCommand('createLink',false,u);else fcNotice('Type a link starting with http://, https://, / or mailto: first.');return}
   if(c.indexOf('formatBlock:')===0){document.execCommand('formatBlock',false,c.split(':')[1]);return}
   document.execCommand(c,false,null);});});
  $('#rulesform').addEventListener('submit',function(){$('#rte-out').value=rte.innerHTML});
 }
});
})();

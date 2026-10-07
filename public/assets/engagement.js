'use strict';
let crmReminderFilter='due';
const crm=()=>books.crm;
const crmClock=()=>crm().now;
const crmDateTime=()=>crmClock().slice(0,16).replace(' ','T');
function crmButton(label,action,id=''){return '<button class="btn small-btn" data-crm-action="'+esc(action)+'" data-id="'+esc(id)+'">'+esc(label)+'</button>';}
function crmText(label,name,value='',required=true){return '<label class="field">'+esc(label)+'<textarea aria-label="'+esc(label)+'" name="'+name+'" rows="4" '+(required?'required':'')+'>'+esc(value)+'</textarea></label>';}
function crmTaskRows(tasks){return tasks.map(t=>'<tr><td>'+esc(t.title)+'<small>'+esc(t.assignee)+(t.priority==='high'?' · High priority':'')+'</small></td><td><button class="link-btn" data-estate-deal="'+t.deal_id+'">'+esc(t.deal_reference)+'</button></td><td>'+esc(t.due_at.slice(0,16))+'<small>Remind '+esc(t.remind_at.slice(0,16))+'</small></td><td>'+badge(t.status==='pending'&&t.due_at<crmClock()?'Overdue':t.status==='pending'?'Pending':t.status==='done'?'Done':'Cancelled')+'</td><td>'+(books.r!=='Viewer'?crmButton('Update','crm_task_status',t.id)+(t.status==='pending'?crmButton('Reschedule','crm_task_reschedule',t.id):''):'')+'</td></tr>');}
function crmDealSection(d){
 const c=crm(),events=[{at:d.deal_date,title:'Deal agreed',detail:d.reference+' · '+(d.buyer_party_name||d.buyer_name)+' / '+(d.seller_party_name||d.seller_name)}];
 estateData().movements.filter(m=>String(m.deal_id)===String(d.id)).forEach(m=>{events.push({at:m.entry_date,title:estateKinds[m.kind]+' · '+moneyText(m.amount),detail:m.note,journal:m.journal_id});if(m.reversed_by)events.push({at:m.reversal_date,title:'Reversed: '+estateKinds[m.kind],detail:moneyText(m.amount),journal:m.reversed_by});});
 estateTotals(d.id).invoices.forEach(i=>{
  events.push({at:i.invoice_date,title:'Commission agreed · '+moneyText(i.total),detail:i.number+' · '+i.party,invoice:i.id});
  books.payments.filter(p=>String(p.invoice_id)===String(i.id)).forEach(p=>events.push({at:p.payment_date,title:'Commission received · '+moneyText(p.amount),detail:p.account_name,journal:p.journal_id}));
  if(i.status==='void'){const j=books.journals.find(j=>String(j.reversal_of)===String(i.journal_id));events.push({at:j?.entry_date||i.invoice_date,title:'Commission receivable reversed',detail:i.number,journal:j?.id});}
 });
 const direct=estateData().direct_settlements.filter(s=>String(s.deal_id)===String(d.id));
 direct.forEach(s=>{events.push({at:s.settled_date,title:'Buyer paid seller directly · '+moneyText(s.amount),detail:s.reference+(s.note?' · '+s.note:'')});if(s.reversed_at)events.push({at:s.reversed_at,title:'Direct settlement corrected',detail:s.reversal_reason||s.reference});});
 c.events.filter(e=>String(e.deal_id)===String(d.id)).forEach(e=>events.push({at:e.event_date+' '+e.created_at.slice(11),title:e.title,detail:e.detail+' · '+e.author}));
 events.sort((a,b)=>b.at.localeCompare(a.at));const tasks=c.tasks.filter(t=>String(t.deal_id)===String(d.id)),docs=c.documents.filter(x=>String(x.deal_id)===String(d.id));
 return '<section class="crm-section"><h3>Direct buyer–seller settlements · '+moneyText(estateTotals(d.id).direct)+'</h3>'+table(['Date','Reference / note','Amount',''],direct.map(s=>'<tr><td>'+esc(s.settled_date)+'</td><td>'+esc(s.reference)+'<small>'+esc(s.note)+'</small>'+(s.reversed_at?'<small>Corrected: '+esc(s.reversal_reason)+'</small>':'')+'</td><td class="num">'+fmt(s.amount)+'</td><td>'+(books.r!=='Viewer'&&d.status==='active'&&!s.reversed_at?estateButton('Correct','estate_direct_reverse',d.id,'',s.id):'')+'</td></tr>'))+'<h3 id="crm-tasks-heading">Follow-up tasks</h3>'+table(['Task / assigned to','Deal','Due / reminder','Status',''],crmTaskRows(tasks))+'<h3 id="crm-documents-heading">Documents</h3>'+table(['Document','Added','Size',''],docs.map(x=>'<tr><td>'+esc(x.title)+'<small>'+esc(x.filename)+'</small></td><td>'+esc(x.created_at)+'</td><td>'+Math.ceil(x.file_size/1024)+' KB</td><td><a class="btn small-btn" href="documents.php?company='+encodeURIComponent(selectedCompany)+'&id='+x.id+'">Download</a></td></tr>'))+'<h3 id="crm-timeline-heading">Deal timeline <span class="muted small">Latest first</span></h3><ol class="crm-timeline">'+events.map(e=>'<li><time>'+esc(e.at.slice(0,16))+'</time><div><strong>'+esc(e.title)+'</strong><p>'+esc(e.detail)+'</p>'+(e.journal?'<button class="link-btn" data-journal="'+e.journal+'">View journal</button>':'')+(e.invoice?'<button class="link-btn" data-invoice="'+e.invoice+'">View invoice</button>':'')+'</div></li>').join('')+'</ol></section>';
}
function crmView(){
 const c=crm(),now=crmClock(),day=now.slice(0,10),pending=c.tasks.filter(t=>t.status==='pending'),due=pending.filter(t=>t.remind_at<=now),late=pending.filter(t=>t.due_at<now);
 let tasks=c.tasks.filter(t=>crmReminderFilter==='all'||(crmReminderFilter==='done'?t.status!=='pending':t.status==='pending'&&(crmReminderFilter==='upcoming'?t.remind_at>now:t.remind_at<=now)));
 tasks=tasks.filter(t=>matches(t.title+' '+t.assignee+' '+t.deal_reference));
 const settlements=estateData().deals.filter(d=>d.status==='active'&&d.due_date<=day),ids=new Set(estateData().invoice_links.map(l=>String(l.invoice_id)));
 const invoices=books.invoices.filter(i=>ids.has(String(i.id))&&i.status==='posted'&&Number(i.total)>Number(i.paid)&&i.due_date<=day);
 return estateStats([['Follow-ups ready',due.length,'Reminder time reached'],['Overdue tasks',late.length,'Past the task due time'],['Settlements due',settlements.length,'Active deals due today or earlier'],['Commission receivable',moneyText(sum(invoices,i=>i.total-i.paid)),'Unpaid commission invoices due']])+'<p class="form-note">Times are in '+esc(c.timezone)+'. This inbox refreshes while the app is open.</p><div class="crm-tabs">'+[['due','Ready now'],['upcoming','Upcoming'],['all','All tasks'],['done','Done / cancelled']].map(([v,l])=>'<button class="btn '+(crmReminderFilter===v?'primary':'')+'" data-crm-filter="'+v+'">'+l+'</button>').join('')+'</div>'+panel('Follow-up inbox',listToolbar()+table(['Task / assigned to','Deal','Due / reminder','Status',''],crmTaskRows(tasks),'No tasks in this filter.'))+panel('Settlement dates',table(['Deal','Client','Due',''],settlements.map(d=>'<tr><td>'+esc(d.reference)+'</td><td>'+esc(d.buyer_party_name||d.buyer_name)+'</td><td>'+esc(d.due_date)+'</td><td><button class="btn small-btn" data-estate-deal="'+d.id+'">Open deal</button></td></tr>'),'No settlement dates due.'))+panel('Commission receivables due',table(['Invoice','Customer','Due','Status','Total','Outstanding',''],invoiceRows(invoices),'No commission receivables due.'));
}
function crmForm(action,extra={}){
 const c=crm(),deal=estateData().deals.find(d=>String(d.id)===String(extra.id));let title='',body='',submit='Save';
 const dealSelect=()=>select('Deal','deal_id',estateData().deals.map(d=>[d.id,d.reference+' · '+d.property_title]),deal?.id||'');
 const timeNote='<p class="small muted">All times: '+esc(c.timezone)+'</p>';
 if(action==='crm_note'){
  title='Add deal activity';body=dealSelect()+select('Activity type','kind',[['note','Note'],['milestone','Milestone'],['call','Phone call'],['meeting','Meeting']])+field('Activity date','date','date',isoToday())+field('Title','title')+crmText('Details','detail','',false);
 }else if(action==='crm_document'){
  if(!estateData().deals.length){toast('Create a deal first.');return;}
  modal('Attach deal document','<div class="form-body">'+dealSelect()+field('Document title','title')+'<label class="field">File (PDF, PNG, JPG or TXT, up to 2 MB)<input type="file" name="document" accept=".pdf,.png,.jpg,.jpeg,.txt" required></label><p class="form-note">Documents can be downloaded only by users with access to this workspace.</p></div>',action,'Attach document');$('#entry-form').id='crm-upload';return;
 }else if(action==='crm_task'){
  if(!estateData().deals.length){toast('Create a deal first.');return;}
  title='Add follow-up task';body=dealSelect()+field('Task title','title')+crmText('Task details','detail','',false)+select('Assign to','assigned_to',c.assignees.map(x=>[x.id,x.name]),session.user.id)+select('Priority','priority',[['normal','Normal'],['high','High']])+'<div class="form-grid">'+field('Due at','due_at','datetime-local',crmDateTime())+field('Remind at','remind_at','datetime-local',crmDateTime())+'</div>'+timeNote;
 }else if(action==='crm_task_status'||action==='crm_task_reschedule'){
  const t=c.tasks.find(t=>String(t.id)===String(extra.id));if(!t)return;title=action==='crm_task_status'?'Update follow-up':'Reschedule follow-up';body='<input type="hidden" name="task_id" value="'+t.id+'"><p class="form-note">'+esc(t.title)+' · '+esc(t.deal_reference)+'</p>'+(action==='crm_task_status'?select('Status','status',[['pending','Pending'],['done','Done'],['cancelled','Cancelled']],t.status):'<div class="form-grid">'+field('Due at','due_at','datetime-local',t.due_at.slice(0,16).replace(' ','T'))+field('Remind at','remind_at','datetime-local',t.remind_at.slice(0,16).replace(' ','T'))+'</div>'+timeNote)+crmText('Update / reason','reason');
 }else return;
 modal(title,'<div class="form-body">'+body+'</div>',action,submit,true);
}
document.addEventListener('click',event=>{
 const button=event.target.closest('button');if(!button)return;const d=button.dataset;
 if(d.crmJump){document.getElementById(d.crmJump)?.scrollIntoView({block:'start'});return;}
 if(d.crmFilter){crmReminderFilter=d.crmFilter;redraw();return;}
 if(d.crmAction){if(busy)return;$('#modal').close();openForm(d.crmAction,{id:d.id});}
});
document.addEventListener('submit',async event=>{
 const form=event.target;if(form.id!=='crm-upload')return;event.preventDefault();if(busy)return;
 const data=new FormData(form);data.set('company',selectedCompany);form.dataset.requestKey ||= crypto.randomUUID();data.set('request_key',form.dataset.requestKey);
 const error=form.querySelector('.form-error'),button=form.querySelector('[type=submit]');error.textContent='';busy=true;button.disabled=true;
 try{const response=await fetch('documents.php',{method:'POST',headers:{'X-CSRF-Token':session.csrf},body:data});const result=await response.json();if(!response.ok||result.error)throw Error(result.error||'Upload failed.');$('#modal').close();await boot();toast('Document attached.');}catch(e){error.textContent=e.message;}finally{busy=false;button.disabled=false;}
});
setInterval(async()=>{
 if(!session?.user||!selectedCompany||busy||document.hidden||$('#modal').open||page!=='Reminders')return;
 const company=selectedCompany;try{const fresh=await api(null,'action=books&company='+encodeURIComponent(company));if(selectedCompany===company&&!busy&&!$('#modal').open){books=fresh;redraw();}}catch{}
},60000);

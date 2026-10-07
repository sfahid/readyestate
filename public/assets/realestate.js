'use strict';
const estateKinds={token:'Token received',biana:'Biana received',receipt:'Client receipt',seller_payment:'Seller / landlord payout',refund:'Client refund'};
function estateData(){return books.estate;}
function estateTotals(id){
 const ms=estateData().movements.filter(m=>String(m.deal_id)===String(id)&&!m.reversed_by);
 const received=sum(ms.filter(m=>['token','biana','receipt'].includes(m.kind)),m=>m.amount);
 const paid=sum(ms.filter(m=>m.kind==='seller_payment'),m=>m.amount),refunded=sum(ms.filter(m=>m.kind==='refund'),m=>m.amount);
 const ids=new Set(estateData().invoice_links.filter(l=>String(l.deal_id)===String(id)).map(l=>String(l.invoice_id)));
 const invoices=books.invoices.filter(i=>ids.has(String(i.id))),active=invoices.filter(i=>i.status==='posted');
 const direct=sum(estateData().direct_settlements.filter(s=>String(s.deal_id)===String(id)&&!s.reversed_at),s=>s.amount);
 return {received,paid,refunded,direct,held:received-paid-refunded,invoices,billed:sum(active,i=>i.total),outstanding:sum(active,i=>i.total-i.paid)};
}
function estateButton(label,action,id='',kind='',movement=''){
 return '<button class="btn small-btn" data-estate-action="'+action+'" data-id="'+esc(id)+'" data-kind="'+esc(kind)+'" data-movement="'+esc(movement)+'">'+esc(label)+'</button>';
}
function estateStats(stats){return '<div class="stats">'+stats.map(([name,value,note])=>'<section class="stat"><div class="stat-top">'+esc(name)+icon('bank')+'</div><p>'+value+'</p><span class="muted small">'+esc(note)+'</span></section>').join('')+'</div>';}
function estatePartyOptions(){return estateData().parties.map(p=>[p.id,p.name+(p.phone?' · '+p.phone:'')]);}
function estateAssetDetail(id){
 const a=estateData().assets.find(x=>String(x.id)===String(id));if(!a)return;
 const events=estateData().asset_events.filter(e=>String(e.asset_id)===String(id)),writable=books.r!=='Viewer';
 modal(esc(a.property_reference)+' · Company asset',estateStats([['Purchase cost',moneyText(a.cost),'Purchased '+a.acquired_date],['Sale proceeds',a.status==='sold'?moneyText(a.sale_proceeds):'—',a.status==='sold'?a.sold_date:'Still owned'],['Gain / loss',a.status==='sold'?moneyText(a.sale_proceeds-a.cost):'—','Sale proceeds less cost']])+'<p class="form-note">'+esc(a.property_title)+' · '+esc(a.status)+'</p><div class="detail-actions">'+(writable&&a.status==='owned'?estateButton('Sell property','estate_asset_sell',id):'')+(writable&&a.status!=='void'?estateButton('Reverse latest transaction','estate_asset_reverse',id):'')+'</div>'+table(['Date','Event','Journal'],events.map(e=>'<tr><td>'+esc(e.entry_date)+'</td><td>'+esc(e.kind)+'</td><td><button class="link-btn" data-journal="'+e.journal_id+'">'+esc(e.journal_number)+'</button></td></tr>')),'','',true);
}
function estateDealRows(deals){return deals.map(d=>{const t=estateTotals(d.id);return '<tr><td><button class="link-btn" data-estate-deal="'+d.id+'">'+esc(d.reference)+'</button><small>'+esc(d.property_title)+'</small></td><td>'+esc(d.buyer_party_name||d.buyer_name)+'</td><td>'+esc(d.seller_party_name||d.seller_name)+'</td><td>'+badge(esc(d.status))+'</td><td>'+esc(d.due_date)+'</td><td class="num">'+fmt(t.held)+'</td><td class="num">'+fmt(t.outstanding)+'</td></tr>';});}
function estateView(){
 const e=estateData(),writable=books.r!=='Viewer';
 if(page==='Parties')return panel('Independent buyers, sellers and owners',listToolbar()+table(['Party','Phone','Address','Notes',''],e.parties.filter(p=>matches(p.name+' '+p.phone+' '+p.address)).map(p=>'<tr><td>'+esc(p.name)+'</td><td>'+esc(p.phone)+'</td><td>'+esc(p.address)+'</td><td>'+esc(p.notes)+'</td><td>'+(writable?estateButton('Edit','estate_party',p.id):'')+'</td></tr>'),'Add a buyer, seller or property owner.'));
 if(page==='Properties')return panel('Property listings',listToolbar()+table(['Reference / property','Type / area','Location','Independent owner','Asking price','Status',''],e.properties.filter(p=>matches(p.reference+' '+p.title+' '+p.location+' '+p.owner_name)).map(p=>'<tr><td>'+esc(p.reference)+'<small>'+esc(p.title)+'</small></td><td>'+esc(p.property_type)+'<small>'+esc(p.area)+'</small></td><td>'+esc(p.location)+'</td><td>'+esc(p.owner_party_name||p.owner_name)+'<small>'+esc(p.owner_party_phone||p.owner_phone)+'</small></td><td class="num">'+fmt(p.asking_price)+'</td><td>'+badge(esc(p.status))+'</td><td>'+(writable?estateButton('Edit','estate_property',p.id):'')+'</td></tr>'),'Add a property listing to begin.'));
 const deals=e.deals.filter(d=>matches(d.reference+' '+d.property_title+' '+d.buyer_name+' '+d.seller_name));
 if(page==='Deals')return panel('Brokerage deals',listToolbar()+table(['Deal / property','Buyer / tenant','Seller / landlord','Status','Settlement due','Client funds held','Commission receivable'],estateDealRows(deals),'Create a deal to record an agreement and its commission receivable.'));
 if(page==='Company assets')return panel('Company-owned properties',listToolbar()+table(['Property','Status','Purchased','Cost','Sale proceeds',''],e.assets.filter(a=>matches(a.property_reference+' '+a.property_title)).map(a=>'<tr><td><button class="link-btn" data-estate-asset="'+a.id+'">'+esc(a.property_reference)+'</button><small>'+esc(a.property_title)+'</small></td><td>'+badge(esc(a.status))+'</td><td>'+esc(a.acquired_date)+'</td><td class="num">'+fmt(a.cost)+'</td><td class="num">'+(a.status==='sold'?fmt(a.sale_proceeds):'—')+'</td><td>'+(writable&&a.status==='owned'?estateButton('Sell','estate_asset_sell',a.id):'')+'</td></tr>'),'Record a company purchase to create a property asset.'));
 const active=e.deals.filter(d=>d.status==='active'),ts=e.deals.map(d=>estateTotals(d.id));
 return estateStats([
  ['Active brokerage deals',active.length,'Independent buyers and sellers'],
  ['Client funds held',moneyText(sum(ts,t=>t.held)),'Money held for clients'],
  ['Commission receivable',moneyText(sum(ts,t=>t.outstanding)),'Income recorded at agreement'],
  ['Company property assets',e.assets.filter(a=>a.status==='owned').length,'Owned separately from listings']
 ])+'<div class="quick-actions">'+(writable?actionButton('Add party','estate_party')+actionButton('Add property','estate_property')+actionButton('New deal','estate_deal')+actionButton('Buy company property','estate_asset_buy'):'')+'</div>'+panel('Active deals',table(['Deal / property','Buyer / tenant','Seller / landlord','Status','Settlement due','Client funds held','Commission receivable'],estateDealRows(active),'No active deals.'));
}
function estateDetail(id){
 const d=estateData().deals.find(d=>String(d.id)===String(id));if(!d)return;
 const t=estateTotals(id),writable=books.r!=='Viewer',active=d.status==='active';
 const ms=estateData().movements.filter(m=>String(m.deal_id)===String(id));
 let actions='';
 if(writable&&active)actions=['token','biana','receipt','seller_payment','refund'].map(k=>estateButton(estateKinds[k],'estate_movement',id,k)).join('')+estateButton('Record direct buyer–seller payment','estate_direct_settlement',id)+(Number(d.commission)>0&&t.billed===0?estateButton('Restore commission receivable','estate_reissue_commission',id):'');
 if(writable)actions+=estateButton('Change deal status','estate_status',id)+crmButton('Add activity','crm_note',id)+crmButton('Add follow-up','crm_task',id)+crmButton('Attach document','crm_document',id);
 modal(esc(d.reference)+' · '+esc(d.property_title),'<div class="detail-meta"><span>'+esc(d.kind==='rental'?'Rental brokerage':'Sale brokerage')+' · '+esc(d.deal_date)+'</span>'+badge(esc(d.status))+'</div><div class="invoice-parties"><div><span class="eyebrow">BUYER / TENANT</span><h3>'+esc(d.buyer_party_name||d.buyer_name)+'</h3><p>'+esc(d.buyer_party_phone||d.buyer_phone)+'</p></div><div><span class="eyebrow">SELLER / LANDLORD</span><h3>'+esc(d.seller_party_name||d.seller_name)+'</h3><p>'+esc(d.seller_party_phone||d.seller_phone)+'</p></div></div>'+estateStats([['Agreed transaction value',moneyText(d.deal_value),'Settlement due '+d.due_date],['Client funds held',moneyText(t.held),'Client liability'],['Paid to seller',moneyText(t.paid),'Refunded '+moneyText(t.refunded)],['Commission receivable',moneyText(t.outstanding),'Income recorded at agreement']])+(d.notes?'<p class="form-note">'+esc(d.notes)+'</p>':'')+'<div class="detail-actions">'+actions+'</div><div class="crm-tabs"><button class="btn" data-crm-jump="crm-timeline-heading">Timeline</button><button class="btn" data-crm-jump="crm-tasks-heading">Follow-ups</button><button class="btn" data-crm-jump="crm-documents-heading">Documents</button></div><h3>Client money history</h3>'+table(['Date','Action / note','Amount','Journal',''],ms.map(m=>'<tr><td>'+esc(m.entry_date)+'</td><td>'+esc(estateKinds[m.kind])+'<small>'+esc(m.note)+'</small>'+(m.reversed_by?'<small>Reversed '+esc(m.reversal_date)+'</small>':'')+'</td><td class="num">'+fmt(m.amount)+'</td><td><button class="link-btn" data-journal="'+m.journal_id+'">'+esc(m.journal_number)+'</button>'+(m.reversed_by?'<small><button class="link-btn" data-journal="'+m.reversed_by+'">Reversing entry</button></small>':'')+'</td><td>'+(writable&&active&&!m.reversed_by?estateButton('Reverse','estate_reverse',id,'',m.id):'')+'</td></tr>'))+'<h3>Commission invoices</h3>'+table(['Invoice','Billed to','Due','Status','Total','Outstanding',''],invoiceRows(t.invoices))+crmDealSection(d)+'<div class="detail-actions"><button class="btn" data-do="print">Print deal statement</button></div>','','',true);
}
function estateForm(action,extra={}){
 const e=estateData(),date=isoToday(),d=e.deals.find(d=>String(d.id)===String(extra.id)),asset=e.assets.find(a=>String(a.id)===String(extra.id));
 let title='',body='',submit='Save';
 if(action==='estate_party'){
  const p=e.parties.find(x=>String(x.id)===String(extra.id))||{};title=p.id?'Edit independent party':'Add independent party';body='<input type="hidden" name="party_id" value="'+(p.id||'')+'">'+field('Name','name','text',p.name||'')+field('Phone','phone','tel',p.phone||'',false)+field('Address','address','text',p.address||'',false)+field('Notes','notes','text',p.notes||'',false)+'<p class="form-note">A party can be a buyer, seller or property owner. This does not make them a company member or their property a company asset.</p>';
 }else if(action==='estate_property'){
  if(!e.parties.length){toast('Add an independent owner in Parties first.');return;}
  const p=e.properties.find(p=>String(p.id)===String(extra.id))||{};title=p.id?'Edit property':'Add property';
  body='<input type="hidden" name="property_id" value="'+(p.id||'')+'"><div class="form-grid">'+field('Property reference','reference','text',p.reference||'')+field('Property title','title','text',p.title||'')+'</div>'+select('Property type','property_type',['Plot','House','Apartment','Commercial','Land'].map(x=>[x,x]),p.property_type)+field('Location / address','location','text',p.location||'')+field('Area (include unit)','area','text',p.area||'',false)+select('Independent owner','owner_party_id',estatePartyOptions(),p.owner_party_id||'')+field('Asking price ('+currency()+')','asking_price','number',((p.asking_price||0)/100).toFixed(2))+select('Listing status','status',[['available','Available'],['inactive','Inactive']],p.status||'available')+field('Notes','notes','text',p.notes||'',false);
 }else if(action==='estate_deal'){
  const available=e.properties.filter(p=>p.status==='available'&&!e.deals.some(d=>String(d.property_id)===String(p.id)&&d.status==='active')&&!e.assets.some(a=>String(a.property_id)===String(p.id)&&a.status==='owned'));
  if(!available.length){toast('Add an available property before creating a deal.');return;}
  if(e.parties.length<2){toast('Add separate buyer and seller parties first.');return;}
  title='New brokerage deal';submit='Agree deal and record commission';
  body=select('Property','property_id',available.map(p=>[p.id,p.reference+' · '+p.title]))+field('Deal reference','reference','text')+select('Deal type','kind',[['sale','Sale brokerage'],['rental','Rental brokerage']])+'<div class="form-grid">'+select('Independent buyer / tenant','buyer_party_id',estatePartyOptions())+select('Independent seller / landlord','seller_party_id',estatePartyOptions(),available[0].owner_party_id||'')+field('Agreement date','date','date',date)+field('Settlement due','due','date',date)+'</div>'+field('Agreed transaction value ('+currency()+')','deal_value','number')+field('Agreed brokerage commission ('+currency()+')','commission','number','0')+select('Commission payer','commission_payer_party_id',estatePartyOptions())+field('Notes','notes','text','',false)+'<p class="form-note">On agreement, commission becomes company income and a receivable. Receiving payment clears the receivable without recording income twice. Client money stays separate.</p>';
 }else if(action==='estate_asset_buy'){
  const available=e.properties.filter(p=>!e.assets.some(a=>String(a.property_id)===String(p.id)&&a.status==='owned')&&!e.deals.some(d=>String(d.property_id)===String(p.id)&&d.status==='active'));
  if(!available.length){toast('Add a property with no active brokerage deal first.');return;}
  title='Buy property for company';submit='Record purchase';body=select('Property','property_id',available.map(p=>[p.id,p.reference+' · '+p.title]))+select('Seller','seller_party_id',estatePartyOptions(),available[0].owner_party_id||'')+field('Purchase date','date','date',date)+field('Purchase cost ('+currency()+')','amount','number')+select('Paid from','account_id',cashOptions())+'<p class="form-note">Purchase cost becomes a company property asset and cash or bank decreases. This property is excluded from brokerage deals.</p>';
 }else if(action==='estate_asset_sell'||action==='estate_asset_reverse'){
  if(!asset)return;body='<input type="hidden" name="asset_id" value="'+asset.id+'"><p class="form-note">'+esc(asset.property_reference)+' · '+esc(asset.property_title)+'</p>'+field('Date','date','date',date);
  if(action==='estate_asset_sell'){title='Sell company property';submit='Record sale';body+=select('Independent buyer','buyer_party_id',estatePartyOptions())+field('Sale proceeds ('+currency()+')','amount','number')+select('Received into','account_id',cashOptions())+'<p class="form-note">Asset cost is removed from the books. The difference becomes a gain or loss.</p>';}
  else{title='Reverse latest asset transaction';submit='Post reversal';body+=field('Reason','reason');}
 }else{
  if(!d)return;body='<input type="hidden" name="deal_id" value="'+d.id+'"><div class="payment-summary"><strong>'+esc(d.reference)+' · '+esc(d.property_title)+'</strong><span>Client funds held: '+moneyText(estateTotals(d.id).held)+'</span></div>';
  if(action==='estate_direct_settlement'){
   title='Record direct buyer–seller payment';submit='Record direct settlement';body+=field('Settlement date','date','date',date)+field('Amount ('+currency()+')','amount','number')+field('Payment reference','reference')+field('Note','note','text','',false)+'<p class="form-note">The buyer paid the seller directly. This is deal history only; no company cash, income or journal entry is created.</p>';
  }else if(action==='estate_direct_reverse'){
   title='Correct direct settlement';submit='Mark as reversed';body+='<input type="hidden" name="settlement_id" value="'+esc(extra.movement)+'">'+field('Reason','reason')+'<p class="form-note">The original direct payment remains visible in the deal history.</p>';
  }else if(action==='estate_movement'){
   const k=extra.kind;if(!estateKinds[k])return;title=estateKinds[k];submit='Record payment';
   body+='<input type="hidden" name="kind" value="'+k+'">'+field('Date','date','date',date)+field('Amount ('+currency()+')','amount','number')+select(['refund','seller_payment'].includes(k)?'Pay from':'Receive into','account_id',cashOptions())+field('Receipt reference / note','note')+'<p class="form-note">'+esc(k==='seller_payment'?'Payee: '+d.seller_name:k==='refund'?'Refund to: '+d.buyer_name:'Received from: '+d.buyer_name)+'. This records client money, separate from your commission.</p>';
  }else if(action==='estate_reissue_commission'){
   title='Restore commission receivable';submit='Restore invoice';body+=field('Invoice date','date','date',date)+'<p class="form-note">Use this when the original commission invoice was voided in error.</p>';
  }else if(action==='estate_reverse'){
   title='Reverse deal payment';submit='Post reversal';body+='<input type="hidden" name="movement_id" value="'+esc(extra.movement)+'">'+field('Reversal date','date','date',date)+field('Reason','reason')+'<p class="form-note">The original record remains in the history. A reversing entry corrects the balance. Use Refund for an actual repayment to a client.</p>';
  }else if(action==='estate_status'){
   title='Change deal status';body+=select('Status','status',[['active','Active'],['completed','Completed'],['cancelled','Cancelled']],d.status)+field('Effective date','date','date',date)+field('Reason / settlement note','reason')+'<p class="form-note">Completion may leave commission unpaid as a receivable. Cancellation reverses an unpaid commission invoice after client funds are settled.</p>';
  }else return;
 }
 modal(title,'<div class="form-body">'+body+'</div>',action,submit,true);
}
document.addEventListener('change',event=>{
 if(event.target.name!=='property_id'||event.target.closest('form')?.dataset.action!=='estate_deal')return;
 const p=estateData().properties.find(p=>String(p.id)===event.target.value);if(!p)return;
 const form=event.target.closest('form');if(p.owner_party_id)form.elements.seller_party_id.value=p.owner_party_id;
});

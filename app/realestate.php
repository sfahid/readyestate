<?php
declare(strict_types=1);

function estate_optional(array $b, string $key, int $max): string {
 $v=$b[$key]??''; need(is_string($v)&&mb_strlen($v)<=$max,"$key is too long."); return trim($v);
}
function estate_account(int $c,string $key,string $name,string $type,string $code):int {
 $a=one('SELECT id FROM accounts WHERE company_id=? AND system_key=?',[$c,$key]);
 if($a)return (int)$a['id'];
 while(one('SELECT id FROM accounts WHERE company_id=? AND code=?',[$c,$code]))$code=(string)((int)$code+1);
 query('INSERT INTO accounts(company_id,code,name,type,system_key) VALUES(?,?,?,?,?)',[$c,$code,$name,$type,$key]);
 return (int)db()->lastInsertId();
}
function estate_deal(int $c,int $id):array {
 $d=one('SELECT * FROM re_deals WHERE company_id=? AND id=?',[$c,$id]);
 need($d!==null,'Deal not found in this company.');return $d;
}
function estate_party(int $c,int $id):array {
 $p=one('SELECT p.* FROM re_parties p JOIN re_party_access a ON a.party_id=p.id WHERE a.company_id=? AND p.id=?',[$c,$id]);
 need($p!==null,'Choose an independent party in this workspace.');return $p;
}
function estate_issue_commission(int $c,int $u,array $d):int {
 $n=(int)$d['commission'];need($n>0,'No commission was agreed.');
 $co=one('SELECT next_invoice FROM companies WHERE id=?',[$c]);$number='INV-'.str_pad((string)$co['next_invoice'],6,'0',STR_PAD_LEFT);
 query('UPDATE companies SET next_invoice=next_invoice+1 WHERE id=?',[$c]);
 $revenue=estate_account($c,'re_commission','Real estate brokerage commission','Revenue','4100');
 $j=post_journal($c,$u,$d['deal_date'],$number.' · Agreed commission · '.$d['reference'],'invoice',pair(system_account($c,'receivable'),$revenue,$n));
 query("INSERT INTO invoices(company_id,number,kind,party,invoice_date,due_date,notes,total,journal_id,created_by) VALUES(?,?,'sale',?,?,?,?,?,?,?)",[$c,$number,$d['commission_party'],$d['deal_date'],$d['due_date'],'Agreed brokerage commission for '.$d['reference'],$n,$j,$u]);
 $id=(int)db()->lastInsertId();query('INSERT INTO invoice_items(invoice_id,description,quantity_units,unit_price,total) VALUES(?,?,10000,?,?)',[$id,'Brokerage commission · '.$d['reference'],$n,$n]);
 query('INSERT INTO re_deal_invoices(company_id,deal_id,invoice_id) VALUES(?,?,?)',[$c,$d['id'],$id]);
 audit($c,$u,'estate.commission',$d['reference'].' · '.$number);return $id;
}
function estate_reverse_journal(int $c,int $u,int $journal,string $date,string $reason):int {
 $original=one('SELECT * FROM journals WHERE company_id=? AND id=?',[$c,$journal]);need($original!==null,'Journal not found.');
 need(!one('SELECT id FROM journals WHERE reversal_of=?',[$journal]),'This journal is already reversed.');
 need($date>=$original['entry_date'],'Reversal date cannot precede the original.');
 $lines=rows('SELECT account_id,credit AS debit,debit AS credit FROM journal_lines WHERE journal_id=?',[$journal]);
 foreach($lines as &$l){$l['debit']=(int)$l['debit'];$l['credit']=(int)$l['credit'];}unset($l);
 return post_journal($c,$u,$date,'Reversal · '.$original['number'].' · '.$reason,'reversal',$lines,$journal);
}
function estate_balance(int $c,int $id):array {
 $totals=['received'=>0,'paid'=>0,'refunded'=>0,'held'=>0];
 foreach(rows('SELECT m.kind,m.amount FROM re_movements m LEFT JOIN journals r ON r.reversal_of=m.journal_id WHERE m.company_id=? AND m.deal_id=? AND r.id IS NULL',[$c,$id]) as $m){
  $key=in_array($m['kind'],['token','biana','receipt'],true)?'received':($m['kind']==='refund'?'refunded':'paid');
  $totals[$key]+=(int)$m['amount'];
 }
 $totals['held']=$totals['received']-$totals['paid']-$totals['refunded'];return $totals;
}
function estate_date(int $c,array $d,mixed $value):string {
 $date=day($value);need($date>=$d['deal_date'],'Date cannot precede the deal date.');
 $last=one('SELECT MAX(GREATEST(m.entry_date,COALESCE(r.entry_date,m.entry_date))) last_date FROM re_movements m LEFT JOIN journals r ON r.reversal_of=m.journal_id WHERE m.company_id=? AND m.deal_id=?',[$c,$d['id']]);
 need(!$last['last_date']||$date>=$last['last_date'],'Use a date on or after the latest deal movement.');return $date;
}
// Called only inside mutate(): company lock, role check, request key and transaction are shared.
function estate_mutate(array $b,int $c,int $u):array {
 $action=$b['action'];
 if($action==='estate_party'){
  $id=(int)($b['party_id']??0);$name=text_value($b['name']??'',150);$phone=estate_optional($b,'phone',40);
  $address=estate_optional($b,'address',500);$notes=estate_optional($b,'notes',2000);
  if($id){estate_party($c,$id);query('UPDATE re_parties SET name=?,phone=?,address=?,notes=? WHERE id=?',[$name,$phone,$address,$notes,$id]);}
  else{query('INSERT INTO re_parties(name,phone,address,notes) VALUES(?,?,?,?)',[$name,$phone,$address,$notes]);$id=(int)db()->lastInsertId();query('INSERT INTO re_party_access(company_id,party_id) VALUES(?,?)',[$c,$id]);}
  audit($c,$u,'estate.party','Independent party '.$id.' · '.$name);return ['id'=>$id];
 }
 if($action==='estate_property'){
  $id=(int)($b['property_id']??0);$ref=text_value($b['reference']??'',40);
  need(!one('SELECT id FROM re_properties WHERE company_id=? AND reference=? AND id<>?',[$c,$ref,$id]),'Property reference is already used.');
  $type=$b['property_type']??'';need(in_array($type,['Plot','House','Apartment','Commercial','Land'],true),'Choose a property type.');
  $status=$b['status']??'available';need(in_array($status,['available','inactive'],true),'Choose a property status.');
  $ownerId=(int)($b['owner_party_id']??0);$owner=$ownerId?estate_party($c,$ownerId):null;
  if(!$id)need($owner!==null,'Choose an independent property owner.');
  $ownerName=$owner?$owner['name']:text_value($b['owner_name']??'',150);
  $ownerPhone=$owner?$owner['phone']:estate_optional($b,'owner_phone',40);
  $values=[$ref,text_value($b['title']??'',150),$type,text_value($b['location']??'',250),estate_optional($b,'area',80),$ownerName,$ownerPhone,money($b['asking_price']??'0'),estate_optional($b,'notes',2000),$status];
  if($id){
   need(one('SELECT id FROM re_properties WHERE company_id=? AND id=?',[$c,$id])!==null,'Property not found.');
   query('UPDATE re_properties SET reference=?,title=?,property_type=?,location=?,area=?,owner_name=?,owner_phone=?,asking_price=?,notes=?,status=?,owner_party_id=? WHERE company_id=? AND id=?',[...$values,$ownerId?:null,$c,$id]);
  }else{query('INSERT INTO re_properties(reference,title,property_type,location,area,owner_name,owner_phone,asking_price,notes,status,company_id,owner_party_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[...$values,$c,$ownerId?:null]);$id=(int)db()->lastInsertId();}
  audit($c,$u,'estate.property',$ref);return ['id'=>$id];
 }
 if($action==='estate_deal'){
  $p=one('SELECT * FROM re_properties WHERE company_id=? AND id=?',[$c,(int)($b['property_id']??0)]);
  need($p!==null&&$p['status']==='available','Choose an available property in this company.');
  need(!one("SELECT id FROM re_company_assets WHERE company_id=? AND property_id=? AND status='owned'",[$c,$p['id']]),'Company-owned property belongs in the Assets area, not a brokerage deal.');
  need(!one("SELECT id FROM re_deals WHERE company_id=? AND property_id=? AND status='active'",[$c,$p['id']]),'This property already has an active deal.');
  $ref=text_value($b['reference']??'',40);need(!one('SELECT id FROM re_deals WHERE company_id=? AND reference=?',[$c,$ref]),'Deal reference is already used.');
  $kind=$b['kind']??'';need(in_array($kind,['sale','rental'],true),'Choose sale or rental brokerage.');
  $date=day($b['date']??'');$due=day($b['due']??'');need($due>=$date,'Due date cannot precede the deal.');
  $value=money($b['deal_value']??'');$commission=money($b['commission']??'0');need($value>0,'Enter a positive deal value.');
  $buyerId=(int)($b['buyer_party_id']??0);$sellerId=(int)($b['seller_party_id']??0);$payerId=(int)($b['commission_payer_party_id']??0);
  $buyer=$buyerId?estate_party($c,$buyerId):null;$seller=$sellerId?estate_party($c,$sellerId):null;$payer=$payerId?estate_party($c,$payerId):null;
  need($buyer!==null&&$seller!==null,'Choose independent buyer and seller parties.');
  if($commission>0)need($payer!==null,'Choose the independent commission payer.');
  need(!$buyer||!$seller||$buyerId!==$sellerId,'Buyer and seller must be different parties.');
  $buyerName=$buyer?$buyer['name']:text_value($b['buyer_name']??'',150);$sellerName=$seller?$seller['name']:text_value($b['seller_name']??'',150);
  $payerName=$payer?$payer['name']:text_value($b['commission_party']??'',150);
  query('INSERT INTO re_deals(company_id,property_id,reference,kind,buyer_name,buyer_phone,seller_name,seller_phone,deal_date,due_date,deal_value,commission,commission_party,notes,created_by,buyer_party_id,seller_party_id,commission_payer_party_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$c,$p['id'],$ref,$kind,$buyerName,$buyer?$buyer['phone']:estate_optional($b,'buyer_phone',40),$sellerName,$seller?$seller['phone']:estate_optional($b,'seller_phone',40),$date,$due,$value,$commission,$payerName,estate_optional($b,'notes',2000),$u,$buyerId?:null,$sellerId?:null,$payerId?:null]);
  $id=(int)db()->lastInsertId();
  if($commission>0)estate_issue_commission($c,$u,['id'=>$id,'reference'=>$ref,'commission'=>$commission,'commission_party'=>$payerName,'deal_date'=>$date,'due_date'=>$due]);
  audit($c,$u,'estate.deal',$ref);return ['id'=>$id];
 }
 if(in_array($action,['estate_asset_buy','estate_asset_sell','estate_asset_reverse'],true)){
  if($action==='estate_asset_buy'){
   $p=one('SELECT * FROM re_properties WHERE company_id=? AND id=?',[$c,(int)($b['property_id']??0)]);need($p!==null,'Property not found.');
   need(!one("SELECT id FROM re_company_assets WHERE company_id=? AND property_id=? AND status='owned'",[$c,$p['id']]),'This property is already a company asset.');
   need(!one("SELECT id FROM re_deals WHERE company_id=? AND property_id=? AND status='active'",[$c,$p['id']]),'Close the active brokerage deal before buying this property for the company.');
   $n=money($b['amount']??'');need($n>0,'Enter a positive purchase cost.');$date=day($b['date']??'');
   $cash=(int)($b['account_id']??0);cash_account($c,$cash);
   $sellerId=(int)($b['seller_party_id']??($p['owner_party_id']??0));need($sellerId>0,'Choose an independent seller.');estate_party($c,$sellerId);
   $assetAccount=estate_account($c,'re_property_assets','Company-owned property','Asset','1200');
   $j=post_journal($c,$u,$date,'Company property purchase · '.$p['reference'],'estate_asset',pair($assetAccount,$cash,$n));
   query('INSERT INTO re_company_assets(company_id,property_id,acquired_date,cost,journal_id,cash_account_id,seller_party_id,created_by) VALUES(?,?,?,?,?,?,?,?)',[$c,$p['id'],$date,$n,$j,$cash,$sellerId?:null,$u]);$id=(int)db()->lastInsertId();
   query("INSERT INTO re_asset_events(company_id,asset_id,kind,journal_id,entry_date) VALUES(?,?,'purchase',?,?)",[$c,$id,$j,$date]);
   audit($c,$u,'estate.asset_buy',$p['reference']);return ['id'=>$id,'journal_id'=>$j];
  }
  $asset=one('SELECT a.*,p.reference property_reference FROM re_company_assets a JOIN re_properties p ON p.id=a.property_id WHERE a.company_id=? AND a.id=?',[$c,(int)($b['asset_id']??0)]);need($asset!==null,'Company asset not found.');
  $date=day($b['date']??'');$reason=text_value($b['reason']??'Asset correction',150);
  $latest=one('SELECT MAX(entry_date) last_date FROM re_asset_events WHERE company_id=? AND asset_id=?',[$c,$asset['id']]);
  need($date>=$latest['last_date'],'Date cannot precede the latest asset transaction.');
  if($action==='estate_asset_sell'){
   need($asset['status']==='owned','Only an owned property can be sold.');need($date>=$asset['acquired_date'],'Sale cannot precede purchase.');
   $amount=money($b['amount']??'');need($amount>0,'Enter positive sale proceeds.');$cash=(int)($b['account_id']??0);cash_account($c,$cash);
   $buyerId=(int)($b['buyer_party_id']??0);need($buyerId>0,'Choose an independent buyer.');estate_party($c,$buyerId);
   $cost=(int)$asset['cost'];$lines=[['account_id'=>$cash,'debit'=>$amount,'credit'=>0],['account_id'=>system_account($c,'re_property_assets'),'debit'=>0,'credit'=>$cost]];
   if($amount>$cost)$lines[]=['account_id'=>estate_account($c,'re_asset_gain','Gain on company property sale','Revenue','4200'),'debit'=>0,'credit'=>$amount-$cost];
   if($amount<$cost)$lines[]=['account_id'=>estate_account($c,'re_asset_loss','Loss on company property sale','Expense','5200'),'debit'=>$cost-$amount,'credit'=>0];
   $j=post_journal($c,$u,$date,'Company property sale · '.$asset['property_reference'],'estate_asset',$lines);
   query("UPDATE re_company_assets SET status='sold',sold_date=?,sale_proceeds=?,sale_journal_id=?,buyer_party_id=? WHERE company_id=? AND id=?",[$date,$amount,$j,$buyerId?:null,$c,$asset['id']]);
   query("INSERT INTO re_asset_events(company_id,asset_id,kind,journal_id,entry_date) VALUES(?,?,'sale',?,?)",[$c,$asset['id'],$j,$date]);
   audit($c,$u,'estate.asset_sell',$asset['property_reference']);return ['journal_id'=>$j];
  }
  $journal=$asset['status']==='sold'?(int)$asset['sale_journal_id']:(int)$asset['journal_id'];
  need($asset['status']!=='void','This acquisition has already been reversed.');
  $j=estate_reverse_journal($c,$u,$journal,$date,$reason);
  if($asset['status']==='sold')query("UPDATE re_company_assets SET status='owned',sold_date=NULL,sale_proceeds=NULL,sale_journal_id=NULL,buyer_party_id=NULL WHERE company_id=? AND id=?",[$c,$asset['id']]);
  else query("UPDATE re_company_assets SET status='void' WHERE company_id=? AND id=?",[$c,$asset['id']]);
  query("INSERT INTO re_asset_events(company_id,asset_id,kind,journal_id,entry_date) VALUES(?,?,'reversal',?,?)",[$c,$asset['id'],$j,$date]);
  audit($c,$u,'estate.asset_reverse',$asset['property_reference'].' · '.$reason);return ['journal_id'=>$j];
 }
 $d=estate_deal($c,(int)($b['deal_id']??0));$balance=estate_balance($c,(int)$d['id']);
 if($action==='estate_direct_settlement'){
  need($d['status']==='active','Reopen the deal before recording a direct settlement.');
  need((int)$d['buyer_party_id']>0&&(int)$d['seller_party_id']>0,'Link independent buyer and seller parties first.');
  $amount=money($b['amount']??'');need($amount>0,'Enter a positive amount.');
  $date=day($b['date']??'');need($date>=$d['deal_date'],'Settlement cannot precede the agreement.');
  $direct=(int)query('SELECT COALESCE(SUM(amount),0) FROM re_direct_settlements WHERE company_id=? AND deal_id=? AND reversed_at IS NULL',[$c,$d['id']])->fetchColumn();
  need($direct+$balance['paid']+$amount<=(int)$d['deal_value'],'Direct settlement plus office payouts cannot exceed the agreed deal value.');
  $reference=text_value($b['reference']??'',150);$note=estate_optional($b,'note',500);
  query('INSERT INTO re_direct_settlements(company_id,deal_id,amount,settled_date,reference,note,created_by) VALUES(?,?,?,?,?,?,?)',[$c,$d['id'],$amount,$date,$reference,$note,$u]);
  $id=(int)db()->lastInsertId();audit($c,$u,'estate.direct_settlement',$d['reference'].' · '.$reference);return ['id'=>$id];
 }
 if($action==='estate_direct_reverse'){
  need($d['status']==='active','Reopen the deal before correcting a direct settlement.');
  $id=(int)($b['settlement_id']??0);$settlement=one('SELECT * FROM re_direct_settlements WHERE company_id=? AND deal_id=? AND id=?',[$c,$d['id'],$id]);
  need($settlement!==null&&$settlement['reversed_at']===null,'Active direct settlement not found.');
  $reason=text_value($b['reason']??'',150);
  query('UPDATE re_direct_settlements SET reversed_at=NOW(),reversal_reason=? WHERE company_id=? AND deal_id=? AND id=?',[$reason,$c,$d['id'],$id]);
  audit($c,$u,'estate.direct_reverse',$d['reference'].' · '.$reason);return ['ok'=>true];
 }
 if($action==='estate_status'){
  $status=$b['status']??'';need(in_array($status,['active','completed','cancelled'],true),'Choose a deal status.');
  if($status==='active'){
   need(!one("SELECT id FROM re_deals WHERE company_id=? AND property_id=? AND status='active' AND id<>?",[$c,$d['property_id'],$d['id']]),'Another active deal exists for this property.');
   if($d['status']==='cancelled'&&(int)$d['commission']>0){
    $date=day($b['date']??date('Y-m-d'));
    need($date>=$d['deal_date'],'Reopening cannot precede the original agreement.');
    $existing=one("SELECT i.id FROM re_deal_invoices l JOIN invoices i ON i.id=l.invoice_id WHERE l.company_id=? AND l.deal_id=? AND i.status='posted'",[$c,$d['id']]);
    if(!$existing)estate_issue_commission($c,$u,[...$d,'deal_date'=>$date,'due_date'=>max($date,$d['due_date'])]);
   }
  }
  else {
   need($balance['held']===0,'Settle or refund the client money held before closing this deal.');
   $i=one("SELECT COALESCE(SUM(i.total-i.paid),0) outstanding,COALESCE(SUM(i.total),0) billed FROM re_deal_invoices l JOIN invoices i ON i.id=l.invoice_id WHERE l.company_id=? AND l.deal_id=? AND i.status='posted'",[$c,$d['id']]);
   if($status==='completed')need((int)$i['billed']===(int)$d['commission'],'The agreed commission must remain receivable or paid before completion.');
   if($status==='cancelled'){
    need($balance['paid']===0,'A deal with seller payouts cannot be cancelled.');
    $invoice=one("SELECT i.* FROM re_deal_invoices l JOIN invoices i ON i.id=l.invoice_id WHERE l.company_id=? AND l.deal_id=? AND i.status='posted'",[$c,$d['id']]);
    if($invoice){
     need((int)$invoice['paid']===0,'Refund client money and settle received commission before cancellation.');
     $date=day($b['date']??date('Y-m-d'));$reason=text_value($b['reason']??'',150);
     estate_reverse_journal($c,$u,(int)$invoice['journal_id'],$date,'Deal cancelled · '.$reason);
     query("UPDATE invoices SET status='void' WHERE id=? AND company_id=?",[$invoice['id'],$c]);
    }
   }
  }
  query('UPDATE re_deals SET status=? WHERE company_id=? AND id=?',[$status,$c,$d['id']]);audit($c,$u,'estate.status',$d['reference'].' · '.$status.' · '.text_value($b['reason']??'',150));return ['ok'=>true];
 }
 need($d['status']==='active','Reopen this deal before recording transactions.');
 if($action==='estate_reissue_commission'){
  need((int)$d['commission']>0,'No commission was agreed.');
  need(!one("SELECT i.id FROM re_deal_invoices l JOIN invoices i ON i.id=l.invoice_id WHERE l.company_id=? AND l.deal_id=? AND i.status='posted'",[$c,$d['id']]),'The commission receivable is already recorded.');
  $date=day($b['date']??'');need($date>=$d['deal_date'],'Date cannot precede the agreement.');
  return ['id'=>estate_issue_commission($c,$u,[...$d,'deal_date'=>$date,'due_date'=>max($date,$d['due_date'])])];
 }
 if($action==='estate_movement'){
  $kind=$b['kind']??'';need(in_array($kind,['token','biana','receipt','seller_payment','refund'],true),'Choose a valid deal payment.');
  $n=money($b['amount']??'');need($n>0,'Enter a positive amount.');$date=estate_date($c,$d,$b['date']??'');
  $incoming=in_array($kind,['token','biana','receipt'],true);
  if($incoming)need($balance['received']-$balance['refunded']+$n<=(int)$d['deal_value'],'Receipts cannot exceed the agreed client funds for this deal.');
  else need($n<=$balance['held'],'This payment exceeds the client money held for this deal.');
  if($kind==='seller_payment'){
   $direct=(int)query('SELECT COALESCE(SUM(amount),0) FROM re_direct_settlements WHERE company_id=? AND deal_id=? AND reversed_at IS NULL',[$c,$d['id']])->fetchColumn();
   need($direct+$balance['paid']+$n<=(int)$d['deal_value'],'Direct settlement plus office payouts cannot exceed the agreed deal value.');
  }
  $cash=(int)($b['account_id']??0);cash_account($c,$cash);$note=text_value($b['note']??'',150);
  $control=estate_account($c,'re_client_funds','Real estate client funds held','Liability','2100');
  $j=post_journal($c,$u,$date,$d['reference'].' · '.str_replace('_',' ',$kind).' · '.$note,'estate',pair($incoming?$cash:$control,$incoming?$control:$cash,$n));
  query('INSERT INTO re_movements(company_id,deal_id,kind,amount,entry_date,note,journal_id) VALUES(?,?,?,?,?,?,?)',[$c,$d['id'],$kind,$n,$date,$note,$j]);
  $id=(int)db()->lastInsertId();audit($c,$u,'estate.movement',$d['reference'].' · '.$kind);return ['id'=>$id,'journal_id'=>$j];
 }
 if($action==='estate_reverse'){
  $m=one('SELECT * FROM re_movements WHERE company_id=? AND deal_id=? AND id=?',[$c,$d['id'],(int)($b['movement_id']??0)]);need($m!==null,'Deal movement not found.');
  need(!one('SELECT id FROM journals WHERE reversal_of=?',[$m['journal_id']]),'This movement is already reversed.');
  $incoming=in_array($m['kind'],['token','biana','receipt'],true);$n=(int)$m['amount'];
  need(!$incoming||$balance['held']>=$n,'Reverse the related payout or refund before reversing this receipt.');
  if($m['kind']==='refund')need($balance['received']-$balance['refunded']+$n<=(int)$d['deal_value'],'Reverse the replacement receipt before reversing this refund.');
  $date=estate_date($c,$d,$b['date']??'');$reason=text_value($b['reason']??'',150);
  $lines=rows('SELECT account_id,credit AS debit,debit AS credit FROM journal_lines WHERE journal_id=?',[$m['journal_id']]);
  foreach($lines as &$line){$line['debit']=(int)$line['debit'];$line['credit']=(int)$line['credit'];}unset($line);
  $j=post_journal($c,$u,$date,'Reverse '.$d['reference'].' · '.$reason,'reversal',$lines,(int)$m['journal_id']);audit($c,$u,'estate.reverse',$d['reference'].' · '.$reason);return ['journal_id'=>$j];
 }
 throw new DomainException('Unknown real estate action.');
}
function estate_snapshot(int $c):array {
 return [
  'properties'=>rows('SELECT p.*,op.name owner_party_name,op.phone owner_party_phone FROM re_properties p LEFT JOIN re_parties op ON op.id=p.owner_party_id WHERE p.company_id=? ORDER BY p.id DESC',[$c]),
  'parties'=>rows('SELECT p.* FROM re_parties p JOIN re_party_access a ON a.party_id=p.id WHERE a.company_id=? ORDER BY p.name,p.id',[$c]),
  'assets'=>rows('SELECT a.*,p.reference property_reference,p.title property_title FROM re_company_assets a JOIN re_properties p ON p.id=a.property_id WHERE a.company_id=? ORDER BY a.id DESC',[$c]),
  'asset_events'=>rows('SELECT e.*,j.number journal_number FROM re_asset_events e JOIN journals j ON j.id=e.journal_id WHERE e.company_id=? ORDER BY e.id DESC',[$c]),
  'deals'=>rows('SELECT d.*,p.title property_title,p.reference property_reference,bp.name buyer_party_name,bp.phone buyer_party_phone,sp.name seller_party_name,sp.phone seller_party_phone FROM re_deals d JOIN re_properties p ON p.id=d.property_id LEFT JOIN re_parties bp ON bp.id=d.buyer_party_id LEFT JOIN re_parties sp ON sp.id=d.seller_party_id WHERE d.company_id=? ORDER BY d.id DESC',[$c]),
  'movements'=>rows('SELECT m.*,j.number journal_number,r.id reversed_by,r.entry_date reversal_date FROM re_movements m JOIN journals j ON j.id=m.journal_id LEFT JOIN journals r ON r.reversal_of=j.id WHERE m.company_id=? ORDER BY m.entry_date,m.id',[$c]),
  'direct_settlements'=>rows('SELECT * FROM re_direct_settlements WHERE company_id=? ORDER BY settled_date,id',[$c]),
  'invoice_links'=>rows('SELECT * FROM re_deal_invoices WHERE company_id=?',[$c])
 ];
}

<?php
declare(strict_types=1);
require __DIR__.'/knowledge_write.php';
$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');
$ga=$kb->group(2,17,'184683593729'); // namespace43, source1
$gb=$kb->group(2,17,$g['groupid']);
$rows=[['id'=>$ga['groupid'],'revision'=>$ga['revision_token'],'order'=>5],['id'=>$gb['groupid'],'revision'=>$gb['revision_token'],'order'=>6]];
$changed=$kb->reorder(2,17,false,$rows);kc(count($changed)===2&&$kb->group(2,17,$ga['groupid'])['group_order']===5,'atomic native group reorder');
$deny(fn()=>$kb->reorder(2,17,false,$rows));$deny(fn()=>$kb->reorder(2,17,false,[$rows[0],$rows[0]]));
$aRow=$kb->article(2,17,$a);$newRow=$kb->article(2,17,$updated['articleid']);
$before=$db->query('SELECT * FROM tblcompany_kb_articles ORDER BY id')->result_array();
$deny(fn()=>$kb->reorder(2,17,true,[['id'=>$a,'revision'=>$aRow['revision_token'],'order'=>3],['id'=>$newRow['articleid'],'revision'=>str_repeat('0',64),'order'=>4]]));
kc($db->query('SELECT * FROM tblcompany_kb_articles ORDER BY id')->result_array()===$before,'stale later row never partially reorders earlier row');
$groupB=NativeCompanyResourceId::encode('company_kb_group',2);$deny(fn()=>$kb->reorder(2,17,true,[['id'=>$a,'revision'=>$aRow['revision_token'],'order'=>3,'group'=>$groupB]]));
$db->query("UPDATE tblcompany_kb_articles SET description='<iframe>historical content untouched</iframe>' WHERE id=1");$aRow=$kb->article(2,17,$a);
$kb->reorder(2,17,true,[['id'=>$a,'revision'=>$aRow['revision_token'],'order'=>3,'group'=>$gb['groupid']]]);
$after=$kb->article(2,17,$a);kc($after['articlegroup']===$gb['groupid']&&$after['description']==='<iframe>historical content untouched</iframe>','group move updates relation only, never silently sanitizes unrelated stored content');
$legacy=$kb->article(1,17,'1');$primaryGroup=NativeCompanyResourceId::encode('company_kb_group',3);
$kb->reorder(1,17,true,[['id'=>'1','revision'=>$legacy['revision_token'],'order'=>9,'group'=>$primaryGroup]]);kc($kb->article(1,17,'1')['articlegroup']===$primaryGroup,'legacy article native kanban moves through owned relation');
try{$kb->change(2,17,true,$a,$after['revision_token'],['active'=>0],true);throw new LogicException('unowned flag bypass');}catch(RuntimeException $e){kc($e->getMessage()==='issuer_owned_transaction_required','internal enlistment flag not authority');}
echo "Knowledge kanban SQLite: atomic batch revisions, A/B group checks, exact content preservation, legacy relation and enlistment passed.\n";

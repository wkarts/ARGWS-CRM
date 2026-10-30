<?php

declare(strict_types=1);

trait CompanyHrReports
{
    private static function reportPermissions(string $name,bool $export=false):array
    {
        $extra=match($name){'roster','seniority','turnover'=>[],'education','equipment','compensation'=>[['hr_sensitive','view']],'contracts'=>[['hr_contracts','view'],['hr_sensitive','view']],'training'=>[['hr_training','view']],default=>throw new DomainException('issuer_hr_invalid')};return array_merge(self::permissions('hr_reports','view'),$extra,$export?[['hr','export']]:[]);
    }
    private function reportRows(int $i,string $name):array
    {
        $ids=$this->observedRows('SELECT id FROM '.$this->p.'company_hr_employees WHERE issuer_id=? ORDER BY id LIMIT 1001',[$i]);if(count($ids)>1000)throw new DomainException('issuer_hr_limit');$out=[];
        foreach($ids as $v){$e=$this->employee($i,(int)$v['id']);
            // Exports include only identities with an active company relationship now.
            if(!$this->memberWitness($i,(int)$e['staff_id']))continue;$this->staffReference($i,(int)$e['staff_id']);$person=$this->publicEmployee($e);$base=['employee_id'=>(int)$e['id'],'employee_code'=>$e['employee_code'],'name'=>$person['name']];
            if(in_array($name,['roster','seniority','turnover'],true)){
                $end=$e['left_on']??gmdate('Y-m-d');$days=max(0,(int)(new DateTimeImmutable($e['joined_on']))->diff(new DateTimeImmutable($end))->format('%r%a'));
                $out[]=$base+['unit_id'=>(int)$e['unit_id'],'position_id'=>(int)$e['position_id'],'workplace_id'=>(int)$e['workplace_id'],'manager_id'=>$e['manager_id']===null?'':(int)$e['manager_id'],'joined_on'=>$e['joined_on'],'left_on'=>$e['left_on']??'','state'=>$e['state'],'calendar_days'=>$days];continue;
            }
            if(!in_array($e['state'],['active','on_leave'],true))continue;
            if(in_array($name,['education','equipment','compensation'],true)){
                foreach($this->observedRows('SELECT id FROM '.$this->p.'company_hr_records WHERE issuer_id=? AND employee_id=? AND kind=? AND state<>? ORDER BY id LIMIT 201',[$i,$e['id'],$name,'void']) as $rid){$r=$this->record($i,(int)$rid['id']);$d=self::data($r,'data_json','data_hash');$out[]=$base+['record_id'=>(int)$r['id'],'state'=>$r['state']]+$d;}
            }elseif($name==='contracts'){
                foreach($this->observedRows('SELECT id FROM '.$this->p.'company_hr_contracts WHERE issuer_id=? AND employee_id=? ORDER BY id LIMIT 101',[$i,$e['id']]) as $cid){$c=$this->contract($i,(int)$cid['id'],true);$t=self::data($c,'terms_json','terms_hash');$out[]=$base+['contract_id'=>(int)$c['id'],'number'=>(int)$c['number'],'starts_on'=>$c['starts_on'],'ends_on'=>$c['ends_on']??'','state'=>$c['state'],'salary_minor'=>$t['salary_minor'],'currency'=>$t['currency'],'hours_per_week'=>$t['hours_per_week'],'acknowledged_at'=>$c['acknowledged_at']??''];}
            }else{
                foreach($this->observedRows('SELECT id FROM '.$this->p.'company_hr_assignments WHERE issuer_id=? AND employee_id=? ORDER BY id LIMIT 101',[$i,$e['id']]) as $aid){$r=$this->assignment($i,(int)$aid['id'],false);$c=self::data($r,'contract_json','contract_hash');$out[]=$base+['assignment_id'=>(int)$r['id'],'course'=>$c['course']['title'],'state'=>$r['state'],'attempts'=>(int)$r['attempt_count'],'due_at'=>$r['due_at']];}
            }
            if(count($out)>1000)throw new DomainException('issuer_hr_limit');
        }
        return $out;
    }
    public function report($issuer,$actor,string $name,bool $export=false):array
    {
        return $this->transaction($issuer,$actor,self::reportPermissions($name,$export),'report_'.$name,null,function(int $i)use($name):array{
            $rows=$this->reportRows($i,$name);return ['report'=>$name,'as_of'=>gmdate('Y-m-d'),'rows'=>$rows,'count'=>count($rows),'calculation'=>'Calendar days = max(0, end date minus joining date); historical exits are rows, not a payroll or statutory turnover rate. Monetary amounts are integer minor units in their recorded currency.'];
        });
    }
    public function reportCsv($issuer,$actor,string $name):string
    {
        $report=$this->report($issuer,$actor,$name,true);$stream=fopen('php://temp','r+');$rows=$report['rows'];if(!$rows){fputcsv($stream,['employee_id']);}else{
            fputcsv($stream,array_keys($rows[0]));foreach($rows as $row){$line=[];foreach($row as $value){if(is_array($value))$value=self::json($value);$s=(string)$value;if(preg_match('/^[=+\-@\t\r]/',$s))$s="'".$s;$line[]=$s;}fputcsv($stream,$line);}
        }rewind($stream);$out=stream_get_contents($stream);fclose($stream);return $out;
    }
    private function importRows(string $csv):array
    {
        if(strlen($csv)>262144||str_contains($csv,"\0")||preg_match('//u',$csv)!==1)throw new DomainException('issuer_hr_invalid');$stream=fopen('php://temp','r+');fwrite($stream,$csv);rewind($stream);$head=fgetcsv($stream);if($head!==self::IMPORT_COLUMNS){fclose($stream);throw new DomainException('issuer_hr_invalid');}$rows=[];
        while(($line=fgetcsv($stream))!==false){if($line===[null])continue;if(count($line)!==count($head)||count($rows)>=100){fclose($stream);throw new DomainException('issuer_hr_limit');}$row=array_combine($head,$line);if($row['manager_id']==='')$row['manager_id']=null;$rows[]=$this->employeeInput($row,true);}fclose($stream);if(!$rows)throw new DomainException('issuer_hr_invalid');return $rows;
    }
    public function reviewImport($issuer,$actor,string $csv):array
    {
        $rows=$this->importRows($csv);return $this->transaction($issuer,$actor,array_merge(self::permissions('hr','import'),[['hr','create']]),'import_review',null,function(int $i,int $a)use($rows):array{
            $seen=[];foreach($rows as $r){if(isset($seen[$r['staff_id']])||$this->observedRows('SELECT id FROM '.$this->p.'company_hr_employees WHERE issuer_id=? AND (staff_id=? OR employee_code=?)',[$i,$r['staff_id'],$r['employee_code']]))throw new DomainException('issuer_hr_request_conflict');$seen[$r['staff_id']]=true;$this->employeeRefs($i,null,$r);}return ['rows'=>$rows,'count'=>count($rows),'review_hash'=>self::digest([$i,$a,$rows,$this->origin()])];
        });
    }
    public function importEmployees($issuer,$actor,string $csv,string $reviewHash,string $key):array
    {
        $rows=$this->importRows($csv);if(!preg_match('/^[a-f0-9]{64}$/D',$reviewHash))throw new DomainException('issuer_hr_invalid');
        return $this->request($issuer,$actor,array_merge(self::permissions('hr','import'),[['hr','create']]),'employees_imported',$key,[$rows,$reviewHash],function(int $i,int $a)use($rows,$reviewHash,$key):array{
            if(!hash_equals(self::digest([$i,$a,$rows,$this->origin()]),$reviewHash))throw new DomainException('issuer_hr_stale');$ids=[];foreach($rows as $r){$id=$this->createEmployeeRow($i,$a,$r);$ids[]=$id;if(count($ids)>1){$rowKey=hash('sha256','hr-import-row|'.$key.'|'.$i.'|'.$id);$rowHash=self::digest(['employee_imported',$key,$r,$this->origin()]);$this->writeReceipt($i,$a,'employee_imported',$rowKey,$rowHash,'employee',$id,$id);}}return ['employee',$ids[0],['ids'=>$ids,'count'=>count($ids)]];
        });
    }
}

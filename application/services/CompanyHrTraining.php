<?php

declare(strict_types=1);

trait CompanyHrTraining
{
    private function courseInput(array $input):array
    {
        self::fields($input,['title','description','questions','pass_percent','max_attempts','active']);$q=$input['questions']??null;
        if(!is_array($q)||!array_is_list($q)||count($q)<1||count($q)>30)throw new DomainException('issuer_hr_invalid');$questions=[];
        foreach($q as $v){if(!is_array($v))throw new DomainException('issuer_hr_invalid');self::fields($v,['question','choices','correct']);$choices=$v['choices']??null;if(!is_array($choices)||!array_is_list($choices)||count($choices)<2||count($choices)>8)throw new DomainException('issuer_hr_invalid');$choices=array_map(static fn($s)=>self::text($s,1000,true,true),$choices);if(count(array_unique($choices))!==count($choices))throw new DomainException('issuer_hr_invalid');$questions[]=['question'=>self::text($v['question']??null,2000,true,true),'choices'=>$choices,'correct'=>self::integer($v['correct']??null,0,count($choices)-1)];}
        return ['title'=>self::text($input['title']??null,191,true),'description'=>self::text($input['description']??'',4000,false,true),'questions'=>$questions,'pass_percent'=>self::integer($input['pass_percent']??70,0,100),'max_attempts'=>self::integer($input['max_attempts']??1,1,10),'active'=>self::integer($input['active']??1,0,1)];
    }
    private function course(int $i,int $id,bool $active=false):array
    {
        $r=$this->row($i,'course',$id);$this->boundProof($i,'course',$id,$r);if($active&&(int)$r['active']!==1)throw new DomainException('issuer_hr_reference_denied');$q=self::data($r,'questions_json','questions_hash');$valid=$this->courseInput(['title'=>$r['title'],'description'=>$r['description'],'questions'=>$q,'pass_percent'=>(int)$r['pass_percent'],'max_attempts'=>(int)$r['max_attempts'],'active'=>(int)$r['active']]);if($valid['questions']!==$q)throw new RuntimeException('issuer_hr_integrity_failed');return $r;
    }
    public function saveCourse($issuer,$actor,?int $id,$revision,array $input,string $key):int
    {
        $r=$this->courseInput($input);return $this->request($issuer,$actor,self::permissions('hr_training','configure'),'course_saved',$key,[$id,$revision,$r],function(int $i)use($id,$revision,$r):array{
            $json=self::json($r['questions']);unset($r['questions']);$r+=['questions_json'=>$json,'questions_hash'=>hash('sha256',$json),'updated_at'=>self::now()];
            if($id===null){$this->limit($i,'course',500);$id=$this->insert('company_hr_courses',['issuer_id'=>$i]+$r+['revision'=>1,'created_at'=>self::now()]);}
            else{$old=$this->course($i,$id);$this->updateRow('company_hr_courses',$old,$r+['revision'=>$this->revision($old,$revision)]);}return ['course',$id,$id];
        });
    }
    public function courses($issuer,$actor):array
    {
        return $this->transaction($issuer,$actor,self::permissions('hr_training','view'),'courses',null,function(int $i,int $a):array{$rows=$this->observedRows('SELECT id FROM '.$this->p.'company_hr_courses WHERE issuer_id=? ORDER BY id LIMIT 501',[$i]);if(count($rows)>500)throw new DomainException('issuer_hr_limit');$out=[];foreach($rows as $v){$r=$this->course($i,(int)$v['id']);if($this->permissionWitness($i,$a,'hr_training','configure'))$r['questions']=self::data($r,'questions_json','questions_hash');unset($r['questions_json'],$r['questions_hash']);$out[]=$r;}return $out;});
    }
    private static function deadline(string $local,string $timezone):string
    {
        if(!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$/D',$local)||!in_array($timezone,DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),true))throw new DomainException('issuer_hr_invalid');
        $base=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$local,new DateTimeZone('UTC'));if(!$base||$base->format('Y-m-d\TH:i')!==$local)throw new DomainException('issuer_hr_invalid');self::date(substr($local,0,10));
        $tz=new DateTimeZone($timezone);$stamp=$base->getTimestamp();$offsets=[];foreach($tz->getTransitions($stamp-172800,$stamp+172800)?:[] as $t)$offsets[$t['offset']]=true;if(!$offsets)$offsets[$tz->getOffset($base)]=true;$matches=[];
        foreach(array_keys($offsets) as $offset){$candidate=(new DateTimeImmutable('@'.($stamp-$offset)))->setTimezone($tz);if($candidate->format('Y-m-d\TH:i')===$local)$matches[$candidate->getTimestamp()]=true;}
        if(count($matches)!==1)throw new DomainException('issuer_hr_ambiguous_deadline');return gmdate('Y-m-d H:i:s',(int)array_key_first($matches));
    }
    private function assignment(int $i,int $id,bool $current=true):array
    {
        $r=$this->row($i,'assignment',$id);$this->boundProof($i,'assignment',$id,$r);$contract=self::data($r,'contract_json','contract_hash');
        if(($contract['issuer_id']??null)!==$i||($contract['employee_id']??null)!==(int)$r['employee_id']||($contract['course_id']??null)!==(int)$r['course_id']||($contract['actor_id']??null)!==(int)$r['actor_id']||($contract['due_at']??null)!==$r['due_at']||($contract['timezone']??null)!==$r['timezone'])throw new RuntimeException('issuer_hr_integrity_failed');
        $this->courseInput($contract['course']??[]);if($current){$this->employee($i,(int)$r['employee_id'],true);$creator=(int)$r['actor_id'];$this->acl->lockFinancialIdentity($i,$creator);foreach(self::permissions('hr_training','assign') as [$feature,$cap])if(!$this->acl->can($i,$creator,$feature,$cap))throw new DomainException('issuer_hr_access_denied');$this->validateOrigin($contract['api_origin']??null,$i,$creator);$this->validators[]=function()use($i,$creator):void{if(!$this->acl->can($i,$creator,'hr','view')||!$this->acl->can($i,$creator,'hr_training','assign'))throw new DomainException('issuer_hr_access_denied');};}
        $attempts=$this->observedRows('SELECT * FROM '.$this->p.'company_hr_attempts WHERE issuer_id=? AND assignment_id=? ORDER BY attempt_number',[$i,$id]);if(count($attempts)!==(int)$r['attempt_count']||count($attempts)>$contract['course']['max_attempts'])throw new RuntimeException('issuer_hr_integrity_failed');
        $passed=false;foreach($attempts as $n=>$attempt){$this->boundProof($i,'attempt',(int)$attempt['id'],$attempt);$answers=self::data($attempt,'answers_json','answers_hash');[$correct,$score]=$this->grade($contract['course']['questions'],$answers);if((int)$attempt['employee_id']!==(int)$r['employee_id']||(int)$attempt['attempt_number']!==$n+1||(int)$attempt['correct_count']!==$correct||(int)$attempt['score']!==$score||(int)$attempt['question_count']!==count($contract['course']['questions'])||(int)$attempt['passed']!==(int)($score>=$contract['course']['pass_percent'])||$passed)throw new RuntimeException('issuer_hr_integrity_failed');$passed=(int)$attempt['passed']===1;$this->remember('company_hr_attempts',['id'=>$attempt['id'],'issuer_id'=>$i],$attempt);}
        if($r['state']!=='cancelled'&&$r['state']!==($passed?'passed':(count($attempts)>=$contract['course']['max_attempts']?'failed':'assigned')))throw new RuntimeException('issuer_hr_integrity_failed');return $r;
    }
    public function assignCourse($issuer,$actor,$employee,$course,string $dueLocal,string $timezone,bool $notify,string $key):int
    {
        $employee=IssuerPolicy::id($employee);$course=IssuerPolicy::id($course);$due=self::deadline($dueLocal,$timezone);
        return $this->request($issuer,$actor,self::permissions('hr_training','assign'),'course_assigned',$key,[$employee,$course,$due,$timezone,$notify],function(int $i,int $a)use($employee,$course,$due,$timezone,$notify):array{
            if($due<=self::now())throw new DomainException('issuer_hr_expired');$this->employee($i,$employee,true);$c=$this->course($i,$course,true);$this->limit($i,'assignment',100,$employee);
            $captured=['title'=>$c['title'],'description'=>$c['description'],'questions'=>self::data($c,'questions_json','questions_hash'),'pass_percent'=>(int)$c['pass_percent'],'max_attempts'=>(int)$c['max_attempts'],'active'=>1];
            $materials=[];foreach($this->observedRows('SELECT id FROM '.$this->p.'company_hr_files WHERE issuer_id=? AND purpose=? AND object_id=? AND hidden=0 ORDER BY id',[$i,'course',$course]) as $material){$file=$this->fileBytes($i,(int)$material['id']);$materials[]=['id'=>(int)$material['id'],'sha256'=>$file['hash']];}$json=self::json(['issuer_id'=>$i,'employee_id'=>$employee,'course_id'=>$course,'actor_id'=>$a,'due_at'=>$due,'timezone'=>$timezone,'course'=>$captured,'materials'=>$materials,'api_origin'=>$this->origin()]);
            $createdAt=self::now();$id=$this->insert('company_hr_assignments',['issuer_id'=>$i,'employee_id'=>$employee,'course_id'=>$course,'actor_id'=>$a,'due_at'=>$due,'timezone'=>$timezone,'contract_json'=>$json,'contract_hash'=>hash('sha256',$json),'state'=>'assigned','attempt_count'=>0,'notify_opt_in'=>$notify?1:0,'revision'=>1,'created_at'=>$createdAt,'updated_at'=>$createdAt]);
            if($notify)foreach(['assigned'=>$createdAt,'due'=>$due] as $event=>$at)$this->insert('company_hr_notices',['issuer_id'=>$i,'employee_id'=>$employee,'assignment_id'=>$id,'actor_id'=>$a,'event'=>$event,'scheduled_at'=>$at,'state'=>'pending','read_at'=>null,'created_at'=>$createdAt]);
            $this->validators[]=function()use($i,$id):void{$this->assignment($i,$id);};return ['assignment',$id,$id];
        });
    }
    private function grade(array $questions,array $answers):array
    {
        if(!array_is_list($answers)||count($answers)!==count($questions))throw new DomainException('issuer_hr_invalid');$correct=0;foreach($questions as $n=>$q){$answer=self::integer($answers[$n],0,count($q['choices'])-1);if($answer===$q['correct'])$correct++;}return [$correct,intdiv($correct*100,count($questions))];
    }
    private function submit(int $i,int $a,int $id,$revision,array $answers):array
    {
        $r=$this->assignment($i,$id);$employee=$this->employee($i,(int)$r['employee_id'],true);if((int)$employee['staff_id']!==$a)throw new DomainException('issuer_hr_access_denied');if($r['state']!=='assigned')throw new DomainException('issuer_hr_state');if($r['due_at']<=self::now())throw new DomainException('issuer_hr_expired');$next=$this->revision($r,$revision);$c=self::data($r,'contract_json','contract_hash')['course'];[$correct,$score]=$this->grade($c['questions'],$answers);$answers=array_map('intval',$answers);$json=self::json($answers);$passed=$score>=$c['pass_percent'];$count=(int)$r['attempt_count']+1;
        $attemptSql='SELECT * FROM '.$this->p.'company_hr_attempts WHERE issuer_id=? AND assignment_id=? ORDER BY attempt_number';$beforeAttempts=$this->observedRows($attemptSql,[$i,$id]);
        $attemptData=['issuer_id'=>$i,'employee_id'=>(int)$r['employee_id'],'assignment_id'=>$id,'attempt_number'=>$count,'actor_id'=>$a,'answers_json'=>$json,'answers_hash'=>hash('sha256',$json),'correct_count'=>$correct,'question_count'=>count($answers),'score'=>$score,'passed'=>$passed?1:0,'created_at'=>self::now()];$attempt=$this->insert('company_hr_attempts',$attemptData);
        $this->expectRows($attemptSql,[$i,$id],array_merge($beforeAttempts,[['id'=>$attempt]+$attemptData]),true);
        $this->updateRow('company_hr_assignments',$r,['state'=>$passed?'passed':($count>=$c['max_attempts']?'failed':'assigned'),'attempt_count'=>$count,'revision'=>$next,'updated_at'=>self::now()]);$this->validators[]=function()use($i,$id):void{$this->assignment($i,$id);};return ['attempt',$attempt,['attempt_id'=>$attempt,'score'=>$score,'passed'=>$passed,'revision'=>$next]];
    }
    public function submitTraining($issuer,$actor,$assignment,$revision,array $answers,string $key):array
    {
        $id=IssuerPolicy::id($assignment);return $this->request($issuer,$actor,[],'training_submitted',$key,[$id,$revision,$answers],function(int $i,int $a)use($id,$revision,$answers):array{$this->ownEmployee($i,$a);return $this->submit($i,$a,$id,$revision,$answers);},function(int $i,int $a)use($id):void{$e=$this->ownEmployee($i,$a);$r=$this->assignment($i,$id);if((int)$r['employee_id']!==(int)$e['id'])throw new DomainException('issuer_hr_access_denied');});
    }
    private function assignmentView(array $r,bool $participant):array
    {
        $c=self::data($r,'contract_json','contract_hash');$course=$c['course'];if($participant)foreach($course['questions'] as &$q)unset($q['correct']);unset($q);unset($r['contract_json'],$r['contract_hash']);return $r+['course'=>$course,'materials'=>$c['materials']];
    }
    public function assignments($issuer,$actor,?int $employee=null,bool $mine=false):array
    {
        return $this->transaction($issuer,$actor,$mine?[]:self::permissions('hr_training','view'),'assignments',null,function(int $i,int $a)use($employee,$mine):array{
            if($mine)$employee=(int)$this->ownEmployee($i,$a)['id'];if($employee===null)throw new DomainException('issuer_hr_invalid');$this->employee($i,$employee,true);$ids=$this->observedRows('SELECT id FROM '.$this->p.'company_hr_assignments WHERE issuer_id=? AND employee_id=? ORDER BY id LIMIT 101',[$i,$employee]);if(count($ids)>100)throw new DomainException('issuer_hr_limit');$out=[];foreach($ids as $v)$out[]=$this->assignmentView($this->assignment($i,(int)$v['id'],false),$mine);return $out;
        });
    }
    public function cancelAssignment($issuer,$actor,$id,$revision,string $key):int
    {
        $id=IssuerPolicy::id($id);return $this->request($issuer,$actor,self::permissions('hr_training','assign'),'assignment_cancelled',$key,[$id,$revision],function(int $i)use($id,$revision):array{$r=$this->assignment($i,$id,false);if($r['state']!=='assigned')throw new DomainException('issuer_hr_state');$this->updateRow('company_hr_assignments',$r,['state'=>'cancelled','revision'=>$this->revision($r,$revision),'updated_at'=>self::now()]);return ['assignment',$id,$id];});
    }
}

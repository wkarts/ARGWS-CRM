<?php

declare(strict_types=1);
require_once __DIR__ . '/NativeCompanyAuxiliarySchema.php';
require_once __DIR__ . '/issuers/IssuerResourceAccess.php';
require_once __DIR__ . '/issuers/IssuerTransaction.php';

/** Explicit company/actor utilities behind the existing native controllers and views. */
final class NativeCompanyAuxiliary
{
    private function assertInserted():void
    {
        $connection=$this->db->conn_id??$this->db->pdo??null;
        $sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $row=IssuerStorage::query($this->db,$sqlite?'SELECT changes() AS affected':'SELECT ROW_COUNT() AS affected')->row_array();
        if((string)($row['affected']??'')!=='1')throw new RuntimeException('issuer_native_insert_unconfirmed');
    }

    private IssuerResourceAccess $access;
    public function __construct(private $db, private string $p, private int $issuer, private int $actor)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $p)) { throw new InvalidArgumentException('Invalid prefix'); }
        IssuerPolicy::id($issuer); IssuerPolicy::id($actor);
        $this->access = new IssuerResourceAccess($db, $p); $this->member();
    }
    private function rows(string $sql, array $args = []): array { return IssuerStorage::query($this->db, $sql, $args)->result_array(); }
    private function member(): void
    {
        if (!$this->access->member($this->issuer, $this->actor)) { throw new DomainException('company_auxiliary_access_denied'); }
    }
    private function can(string $feature, string $capability): void
    {
        if (!$this->access->can($this->issuer, $this->actor, $feature, $capability)) { throw new DomainException('company_auxiliary_access_denied'); }
    }
    public static function tableNames():array{return array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_audit'],array_keys(NativeCompanyAuxiliarySchema::fields()));}
    private function tx(callable $operation,?string $relationType=null)
    {
        NativeCompanyAuxiliarySchema::assertValid($this->db, $this->p);
        $tables = self::tableNames();
        if($relationType!==null){require_once __DIR__.'/NativeCompanyResourceReferences.php';$tables=array_values(array_unique(array_merge($tables,NativeCompanyResourceReferences::transactionTables($this->db,$this->p,$relationType))));}
        return IssuerTransaction::run($this->db, $this->p, $tables, function () use ($operation) {
            $this->access->lockFinancialIdentity($this->issuer,$this->actor);
            $this->member(); $result = $operation(); $this->member(); return $result;
        });
    }
    private static function text($value, int $max, bool $empty = false): string
    {
        if (!is_string($value) || strlen($value) > $max || preg_match('//u', $value) !== 1
            || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value) || (!$empty && trim($value) === '')) {
            throw new DomainException('company_auxiliary_invalid_input');
        }
        return trim($value);
    }
    private static function revision($value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string)$value)
            || (int)$value > 4294967294) { throw new DomainException('company_auxiliary_stale'); }
        return (int)$value;
    }
    private static function bit($value): int
    {
        if (!in_array($value, [0,1,'0','1'], true)) { throw new DomainException('company_auxiliary_invalid_input'); }
        return (int)$value;
    }
    private static function page(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > 100 || $offset < 0 || $offset > 100000) { throw new DomainException('company_auxiliary_invalid_page'); }
    }
    private function audit(string $event, int $id): void
    {
        $meta = json_encode(['version' => 1, 'source' => 'native_company_utilities', 'operation_nonce' => bin2hex(random_bytes(16))], JSON_THROW_ON_ERROR);
        IssuerStorage::query($this->db, 'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)', [$this->issuer,$this->actor,$id,$event,$meta,gmdate('Y-m-d H:i:s')]);
        $auditId=(int)$this->db->insert_id();
        if (!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$auditId,$this->issuer,$this->actor,$id,$event,$meta])) { throw new RuntimeException('issuer_auxiliary_audit_unconfirmed'); }
    }
    public function todos(bool $finished, int $limit = 10, int $offset = 0): array
    {
        self::page($limit,$offset); $this->member();
        $rows = $this->rows('SELECT todoid,description,staffid,dateadded,datefinished,finished,item_order,revision FROM '.$this->p.'company_todos WHERE issuer_id=? AND staffid=? AND finished=? ORDER BY item_order,todoid LIMIT '.$limit.' OFFSET '.$offset, [$this->issuer,$this->actor,(int)$finished]);
        $this->member(); return $rows;
    }
    public function todoCounts(): array
    {
        $this->member(); $counts = [0 => 0, 1 => 0];
        foreach ($this->rows('SELECT finished,COUNT(*) AS total FROM '.$this->p.'company_todos WHERE issuer_id=? AND staffid=? GROUP BY finished', [$this->issuer,$this->actor]) as $row) {
            if (!in_array((string)$row['finished'], ['0','1'], true)) { throw new RuntimeException('issuer_native_auxiliary_data_invalid'); }
            $counts[(int)$row['finished']] = (int)$row['total'];
        }
        $this->member(); return $counts;
    }
    public function todo($id): array
    {
        $this->member(); $row = $this->todoRow(IssuerPolicy::id($id)); $this->member(); return $row;
    }
    private function todoRow(int $id): array
    {
        $row = $this->rows('SELECT * FROM '.$this->p.'company_todos WHERE issuer_id=? AND staffid=? AND todoid=?', [$this->issuer,$this->actor,$id])[0] ?? null;
        if (!$row) { throw new DomainException('company_auxiliary_access_denied'); }
        return $row;
    }
    public function saveTodo(array $input, $id = null, $revision = null): int
    {
        if (array_diff(array_keys($input), ['description'])) { throw new DomainException('company_auxiliary_invalid_input'); }
        $description = self::text($input['description'] ?? null, 16000);
        $id = $id === null || $id === '' ? null : IssuerPolicy::id($id);
        $revision = $id === null ? null : self::revision($revision);
        return $this->tx(function () use ($description,$id,$revision): int {
            if ($id === null) {
                IssuerStorage::query($this->db, 'INSERT INTO '.$this->p.'company_todos(issuer_id,staffid,description,finished,item_order,dateadded,datefinished,revision) VALUES(?,?,?,0,0,?,NULL,1)', [$this->issuer,$this->actor,$description,gmdate('Y-m-d H:i:s')]);
                $this->assertInserted();$id = (int)$this->db->insert_id();
            } else {
                $row = $this->todoRow($id);
                if ((int)$row['revision'] !== $revision) { throw new DomainException('company_auxiliary_stale'); }
                IssuerStorage::query($this->db, 'UPDATE '.$this->p.'company_todos SET description=?,revision=revision+1 WHERE issuer_id=? AND staffid=? AND todoid=? AND revision=?', [$description,$this->issuer,$this->actor,$id,$revision]);
            }
            $saved = $this->todoRow($id);
            if ($saved['description'] !== $description || (int)$saved['revision'] !== ($revision === null ? 1 : $revision+1)) { throw new RuntimeException('issuer_auxiliary_write_unconfirmed'); }
            $this->audit('native_todo_saved',$id); return $id;
        });
    }
    public function deleteTodo($id, $revision): bool
    {
        $id = IssuerPolicy::id($id); $revision = self::revision($revision);
        return $this->tx(function () use ($id,$revision): bool {
            if ((int)$this->todoRow($id)['revision'] !== $revision) { throw new DomainException('company_auxiliary_stale'); }
            IssuerStorage::query($this->db, 'DELETE FROM '.$this->p.'company_todos WHERE issuer_id=? AND staffid=? AND todoid=? AND revision=?', [$this->issuer,$this->actor,$id,$revision]);
            if ($this->rows('SELECT todoid FROM '.$this->p.'company_todos WHERE issuer_id=? AND todoid=?',[$this->issuer,$id])) { throw new RuntimeException('issuer_auxiliary_write_unconfirmed'); }
            $this->audit('native_todo_deleted',$id); return true;
        });
    }
    /** Every row is validated before any update; cross-company rows roll back the entire reorder. */
    public function orderTodos(array $input,bool $inOwned=false): array
    {
        if (count($input) > 200) { throw new DomainException('company_auxiliary_invalid_input'); }
        $items=[];
        foreach ($input as $row) {
            if (!is_array($row) || array_keys($row) !== [0,1,2,3]) { throw new DomainException('company_auxiliary_invalid_input'); }
            $id=IssuerPolicy::id($row[0]);
            if (isset($items[$id]) || !preg_match('/^[0-9]{1,6}$/D',(string)$row[1])) { throw new DomainException('company_auxiliary_invalid_input'); }
            $items[$id]=[(int)$row[1],self::bit($row[2]),self::revision($row[3])];
        }
        $operation=function () use ($items): array {
            foreach ($items as $id => [,$finished,$revision]) {
                if ((int)$this->todoRow($id)['revision'] !== $revision) { throw new DomainException('company_auxiliary_stale'); }
            }
            $result=[];
            foreach ($items as $id => [$order,$finished,$revision]) {
                $row=$this->todoRow($id); $date=$finished ? ($row['datefinished'] ?: gmdate('Y-m-d H:i:s')) : null;
                IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_todos SET item_order=?,finished=?,datefinished=?,revision=revision+1 WHERE issuer_id=? AND staffid=? AND todoid=? AND revision=?',[$order,$finished,$date,$this->issuer,$this->actor,$id,$revision]);
                $saved=$this->todoRow($id);
                if ((int)$saved['revision']!==$revision+1 || (int)$saved['item_order']!==$order || (int)$saved['finished']!==$finished) { throw new RuntimeException('issuer_auxiliary_write_unconfirmed'); }
                $result[$id]=$revision+1;
            }
            return $result;
        };
        if($inOwned){IssuerTransaction::assertOwned($this->db,$this->p,self::tableNames());$this->member();return$operation();}
        return $this->tx($operation);
    }
    public function allowed(string $feature,string $capability):bool
    {
        return $this->access->can($this->issuer,$this->actor,$feature,$capability);
    }
    public function announcementTable(int $start,int $length,string $search=''):array
    {
        self::page($length,$start); $this->member();
        $where='a.issuer_id=?';$args=[$this->issuer];
        if(!$this->allowed('announcements','edit'))$where.=' AND a.showtostaff=1';
        $total=(int)$this->rows('SELECT COUNT(*) AS n FROM '.$this->p.'company_announcements a WHERE '.$where,$args)[0]['n'];
        if($search!==''){$where.=' AND a.name LIKE ?';$args[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],self::text($search,191)).'%';$where.=" ESCAPE '!'";}
        $filtered=(int)$this->rows('SELECT COUNT(*) AS n FROM '.$this->p.'company_announcements a WHERE '.$where,$args)[0]['n'];
        $rows=$this->rows('SELECT a.*,EXISTS(SELECT 1 FROM '.$this->p.'company_announcement_dismissals d WHERE d.issuer_id=a.issuer_id AND d.announcementid=a.announcementid AND d.actor_id=?) AS is_dismissed FROM '.$this->p.'company_announcements a WHERE '.$where.' ORDER BY a.dateadded DESC,a.announcementid DESC LIMIT '.$length.' OFFSET '.$start,array_merge([$this->actor],$args));
        $this->member();return ['total'=>$total,'filtered'=>$filtered,'rows'=>$rows];
    }
    public function announcements(int $limit = 100, int $offset = 0): array
    {
        self::page($limit,$offset); $this->member();
        $rows=$this->rows('SELECT a.* FROM '.$this->p.'company_announcements a WHERE a.issuer_id=? AND a.showtostaff=1 ORDER BY a.dateadded DESC,a.announcementid DESC LIMIT '.$limit.' OFFSET '.$offset,[$this->issuer]);
        $this->member();return $rows;
    }
    public function announcement($id): array
    {
        $this->member();$id=IssuerPolicy::id($id);
        $row=$this->rows('SELECT * FROM '.$this->p.'company_announcements WHERE issuer_id=? AND announcementid=?',[$this->issuer,$id])[0]??null;
        if (!$row || ((int)$row['showtostaff']!==1 && !$this->access->can($this->issuer,$this->actor,'announcements','edit'))) { throw new DomainException('company_auxiliary_access_denied'); }
        $this->member();return$row;
    }
    public function undismissedAnnouncementsCount(): int
    {
        $this->member();$row=$this->rows('SELECT COUNT(*) AS total FROM '.$this->p.'company_announcements a WHERE a.issuer_id=? AND a.showtostaff=1 AND NOT EXISTS(SELECT 1 FROM '.$this->p.'company_announcement_dismissals d WHERE d.issuer_id=a.issuer_id AND d.announcementid=a.announcementid AND d.actor_id=?)',[$this->issuer,$this->actor])[0];
        $this->member();return(int)$row['total'];
    }
    public function dismissAnnouncement($id): void
    {
        $id=IssuerPolicy::id($id);$this->tx(function()use($id):void{
            $this->announcement($id);
            if (!$this->rows('SELECT announcementid FROM '.$this->p.'company_announcement_dismissals WHERE issuer_id=? AND announcementid=? AND actor_id=?',[$this->issuer,$id,$this->actor])) {
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_announcement_dismissals(issuer_id,announcementid,actor_id,created_at) VALUES(?,?,?,?)',[$this->issuer,$id,$this->actor,gmdate('Y-m-d H:i:s')]);
            }
        });
    }
    public function remindersCount(): int
    {
        $this->member(); $count=0;
        foreach ($this->rows('SELECT * FROM '.$this->p.'company_reminders WHERE issuer_id=? AND staff=? AND isnotified=0',[$this->issuer,$this->actor]) as $row) {
            try { $this->validateStoredRelation($row); ++$count; }
            catch (DomainException $error) { /* Revoked resource reminders are no longer visible. */ }
        }
        $this->member(); return $count;
    }
    /** Actor-visible audit entries only; never return financial metadata or transport secrets. */
    public function activity(int $limit=30): array
    {
        self::page($limit,0);$this->can('reports','view');
        $rows=$this->rows('SELECT id,actor_id AS staffid,event AS description,created_at AS date FROM '.$this->p.'issuers_audit WHERE issuer_id=? ORDER BY id DESC LIMIT '.$limit,[$this->issuer]);
        $this->can('reports','view');return$rows;
    }
    public static function announcementFields(array $input):array
    {
        if (array_diff(array_keys($input), ['name','message','showtousers','showtostaff','showname'])) { throw new DomainException('company_auxiliary_invalid_input'); }
        $name=self::text($input['name']??null,191); $message=self::text($input['message']??'',64000,true);
        require_once APPPATH.'vendor/autoload.php';
        $config=HTMLPurifier_Config::createDefault();$config->set('Cache.DefinitionImpl',null);$config->set('URI.DisableExternalResources',true);
        $message=(new HTMLPurifier($config))->purify($message);
        $flags=[];foreach(['showtousers','showtostaff','showname']as$key)$flags[$key]=self::bit($input[$key]??0);
        return ['name'=>$name,'message'=>$message]+$flags;
    }
    public function saveAnnouncement(array $input, $id = null, $revision = null): int
    {
        $fields=self::announcementFields($input);$name=$fields['name'];$message=$fields['message'];$flags=array_intersect_key($fields,array_flip(['showtousers','showtostaff','showname']));
        $id=$id===null||$id===''?null:IssuerPolicy::id($id);$revision=$id===null?null:self::revision($revision);
        return $this->tx(function()use($name,$message,$flags,$id,$revision):int{
            $this->can('announcements',$id===null?'create':'edit');
            if($id===null){
                $author=$this->rows('SELECT firstname,lastname FROM '.$this->p.'staff WHERE staffid=? AND active=1',[$this->actor])[0]??null;
                if(!$author)throw new DomainException('company_auxiliary_access_denied');
                $authorName=mb_substr(trim($author['firstname'].' '.$author['lastname']),0,100);
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_announcements(issuer_id,name,message,showtousers,showtostaff,showname,userid,actor_id,dateadded,revision) VALUES(?,?,?,?,?,?,?,?,?,1)',[$this->issuer,$name,$message,$flags['showtousers'],$flags['showtostaff'],$flags['showname'],$authorName,$this->actor,gmdate('Y-m-d H:i:s')]);
                $this->assertInserted();$id=(int)$this->db->insert_id();
            }else{
                $row=$this->announcement($id);if((int)$row['revision']!==$revision)throw new DomainException('company_auxiliary_stale');
                IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_announcements SET name=?,message=?,showtousers=?,showtostaff=?,showname=?,revision=revision+1 WHERE issuer_id=? AND announcementid=? AND revision=?',[$name,$message,$flags['showtousers'],$flags['showtostaff'],$flags['showname'],$this->issuer,$id,$revision]);
            }
            $saved=$this->rows('SELECT * FROM '.$this->p.'company_announcements WHERE issuer_id=? AND announcementid=?',[$this->issuer,$id])[0]??[];
            if(($saved['name']??null)!==$name||($saved['message']??null)!==$message||(int)($saved['revision']??0)!==($revision===null?1:$revision+1))throw new RuntimeException('issuer_auxiliary_write_unconfirmed');
            $this->audit('native_announcement_saved',$id);return$id;
        });
    }
    public function deleteAnnouncement($id,$revision):void
    {
        $id=IssuerPolicy::id($id);$revision=self::revision($revision);
        $this->tx(function()use($id,$revision):void{
            $this->can('announcements','delete');$owned=$this->rows('SELECT revision FROM '.$this->p.'company_announcements WHERE issuer_id=? AND announcementid=?',[$this->issuer,$id])[0]??null;if(!$owned||(int)$owned['revision']!==$revision)throw new DomainException('company_auxiliary_stale');
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_announcement_dismissals WHERE issuer_id=? AND announcementid=?',[$this->issuer,$id]);
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_announcement_contact_dismissals WHERE issuer_id=? AND announcementid=?',[$this->issuer,$id]);
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_announcements WHERE issuer_id=? AND announcementid=? AND revision=?',[$this->issuer,$id,$revision]);
            if($this->rows('SELECT announcementid FROM '.$this->p.'company_announcements WHERE issuer_id=? AND announcementid=?',[$this->issuer,$id]))throw new RuntimeException('issuer_auxiliary_write_unconfirmed');
            $this->audit('native_announcement_deleted',$id);
        });
    }
    private function relation(string $type,$id,string $cap='view'):array
    {
        require_once __DIR__.'/NativeCompanyResourceReferences.php';
        return NativeCompanyResourceReferences::resolve($this->db,$this->p,$this->issuer,$this->actor,$type,$id,$cap);
    }
    private function author(array $row,string $field):void{if((int)$row[$field]!==$this->actor&&!$this->access->administrative($this->issuer,$this->actor))throw new DomainException('company_auxiliary_access_denied');}
    private function validateStoredRelation(array $row,string $cap='view'):array
    {
        $reference=$this->relation($row['rel_type'],$row['rel_id'],$cap);
        if(($reference['source_kind']??null)!==$row['source_kind']||(string)($reference['source_id']??'')!==(string)$row['source_id'])throw new DomainException('company_auxiliary_access_denied');
        return$reference;
    }
    public function notes(string $type,$id):array
    {
        $ref=$this->relation($type,$id);
        $rows=$this->rows('SELECT n.*,COALESCE(s.firstname,\'\') AS firstname,COALESCE(s.lastname,\'\') AS lastname FROM '.$this->p.'company_notes n LEFT JOIN '.$this->p.'staff s ON s.staffid=n.addedfrom WHERE n.issuer_id=? AND n.rel_type=? AND n.rel_id=? ORDER BY n.id DESC',[$this->issuer,$type,$ref['canonical_id']]);
        foreach($rows as&$row){$this->validateStoredRelation($row);try{$this->validateStoredRelation($row,'edit');$this->author($row,isset($row['addedfrom'])?'addedfrom':'creator');$row['can_edit']=true;}catch(DomainException $error){$row['can_edit']=false;}}unset($row);return$rows;
    }
    public function saveNote(string $type,$relId,array $input,$id=null,$revision=null):int
    {
        if(array_diff(array_keys($input),['description']))throw new DomainException('company_auxiliary_invalid_input');
        $description=self::text($input['description']??null,16000);$id=$id===null?null:IssuerPolicy::id($id);$revision=$id===null?null:self::revision($revision);
        return$this->tx(function()use($type,$relId,$description,$id,$revision):int{
            $ref=$this->relation($type,$relId,'edit');
            if($id===null){
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_notes(issuer_id,rel_type,rel_id,source_kind,source_id,description,addedfrom,dateadded,date_contacted,revision) VALUES(?,?,?,?,?,?,?,?,NULL,1)',[$this->issuer,$type,$ref['canonical_id'],$ref['source_kind'],$ref['source_id'],$description,$this->actor,gmdate('Y-m-d H:i:s')]);$this->assertInserted();$id=(int)$this->db->insert_id();
            }else{
                $row=$this->note($id);$this->author($row,'addedfrom');if($row['rel_type']!==$type||(string)$row['rel_id']!==(string)$ref['canonical_id']||(int)$row['revision']!==$revision)throw new DomainException('company_auxiliary_stale');
                IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_notes SET description=?,revision=revision+1 WHERE issuer_id=? AND id=? AND revision=?',[$description,$this->issuer,$id,$revision]);
            }
            $saved=$this->note($id);if($saved['description']!==$description||(int)$saved['revision']!==($revision===null?1:$revision+1))throw new RuntimeException('issuer_auxiliary_write_unconfirmed');
            $this->audit('native_note_saved',$id);return$id;
        },$type);
    }
    public function note($id):array
    {
        $this->member();$row=$this->rows('SELECT * FROM '.$this->p.'company_notes WHERE issuer_id=? AND id=?',[$this->issuer,IssuerPolicy::id($id)])[0]??null;
        if(!$row)throw new DomainException('company_auxiliary_access_denied');$this->validateStoredRelation($row);return$row;
    }
    public function deleteNote($id,$revision):void
    {
        $id=IssuerPolicy::id($id);$revision=self::revision($revision);$this->member();$relationType=$this->rows('SELECT rel_type FROM '.$this->p.'company_notes WHERE issuer_id=? AND id=?',[$this->issuer,$id])[0]['rel_type']??null;if(!is_string($relationType))throw new DomainException('company_auxiliary_access_denied');$this->tx(function()use($id,$revision):void{
            $row=$this->note($id);$this->author($row,'addedfrom');$this->validateStoredRelation($row,'edit');if((int)$row['revision']!==$revision)throw new DomainException('company_auxiliary_stale');
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_notes WHERE issuer_id=? AND id=? AND revision=?',[$this->issuer,$id,$revision]);
            if($this->rows('SELECT id FROM '.$this->p.'company_notes WHERE issuer_id=? AND id=?',[$this->issuer,$id]))throw new RuntimeException('issuer_auxiliary_write_unconfirmed');$this->audit('native_note_deleted',$id);
        },$relationType);
    }
    public function reminders(?string $type=null,$relId=null,int $limit=100,int $offset=0):array
    {
        self::page($limit,$offset);$this->member();$args=[$this->issuer];$where='issuer_id=?';
        if($type!==null){$ref=$this->relation($type,$relId);$where.=' AND rel_type=? AND rel_id=?';$args[]=$type;$args[]=$ref['canonical_id'];}
        else{$where.=' AND staff=?';$args[]=$this->actor;}
        $rows=$this->rows('SELECT * FROM '.$this->p.'company_reminders WHERE '.$where.' ORDER BY date,id LIMIT '.$limit.' OFFSET '.$offset,$args);
        $result=[];foreach($rows as$row){$reference=$this->validateStoredRelation($row);$row['relation']=$reference;$result[]=$row;}$this->member();return$result;
    }
    public function reminderTable(?string $type,$relId,int $start,int $length,string $search='',bool $pendingOnly=false,bool $allVisible=false):array
    {
        self::page($length,$start);$this->member();$args=[$this->issuer];$where='r.issuer_id=?';
        if($type!==null){$ref=$this->relation($type,$relId);$where.=' AND r.rel_type=? AND r.rel_id=?';$args[]=$type;$args[]=$ref['canonical_id'];}
        else{if(!$allVisible){$where.=' AND r.staff=?';$args[]=$this->actor;}elseif(!$this->access->administrative($this->issuer,$this->actor)){$where.=' AND (r.staff=? OR r.creator=?)';$args[]=$this->actor;$args[]=$this->actor;}}
        if($pendingOnly)$where.=' AND r.isnotified=0';
        $visible=[];
        foreach($this->rows('SELECT r.*,s.firstname,s.lastname FROM '.$this->p.'company_reminders r JOIN '.$this->p.'staff s ON s.staffid=r.staff WHERE '.$where.' ORDER BY r.date,r.id',$args)as$row){
            try{$row['relation']=$this->validateStoredRelation($row);}catch(DomainException $error){continue;}
            try{$this->validateStoredRelation($row,'edit');$this->author($row,isset($row['addedfrom'])?'addedfrom':'creator');$row['can_edit']=true;}catch(DomainException $error){$row['can_edit']=false;}
            $visible[]=$row;
        }
        $total=count($visible);
        if($search!==''){$search=self::text($search,191);$visible=array_values(array_filter($visible,static fn($row)=>mb_stripos($row['description'].' '.$row['relation']['name'],$search)!==false));}
        $this->member();return ['total'=>$total,'filtered'=>count($visible),'rows'=>array_slice($visible,$start,$length)];
    }
    /** Calendar range, preserving native creator OR recipient OR administrative visibility. */
    public function calendarReminders(string $from,string $until,bool $hideNotified):array
    {
        foreach([$from,$until]as$date){$d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$date);if(!$d||$d->format('Y-m-d H:i:s')!==$date)throw new DomainException('native_calendar_range_invalid');}
        if($until<=$from||(new DateTimeImmutable($from))->diff(new DateTimeImmutable($until))->days>63)throw new DomainException('native_calendar_range_invalid');
        $this->member();$where='r.issuer_id=? AND r.date>=? AND r.date<?';$args=[$this->issuer,$from,$until];
        if(!$this->access->administrative($this->issuer,$this->actor)){$where.=' AND (r.staff=? OR r.creator=?)';$args[]=$this->actor;$args[]=$this->actor;}
        if($hideNotified)$where.=' AND r.isnotified=0';
        $rows=$this->rows('SELECT r.*,s.firstname,s.lastname FROM '.$this->p.'company_reminders r JOIN '.$this->p.'staff s ON s.staffid=r.staff WHERE '.$where.' ORDER BY r.date,r.id LIMIT 501',$args);
        if(count($rows)>500)throw new DomainException('native_calendar_range_too_large');
        $result=[];foreach($rows as$row){try{$row['relation']=$this->validateStoredRelation($row);}catch(DomainException $denied){continue;}$result[]=$row;}
        foreach($result as$row)if($this->validateStoredRelation($row)!==$row['relation'])throw new DomainException('native_calendar_stale');
        $again=$this->rows('SELECT r.*,s.firstname,s.lastname FROM '.$this->p.'company_reminders r JOIN '.$this->p.'staff s ON s.staffid=r.staff WHERE '.$where.' ORDER BY r.date,r.id LIMIT 501',$args);if($again!==$rows)throw new DomainException('native_calendar_stale');
        $this->member();return$result;
    }
    public function members():array
    {
        $this->member();$rows=$this->rows('SELECT s.staffid,s.firstname,s.lastname FROM '.$this->p.'staff s WHERE s.active=1 AND (EXISTS(SELECT 1 FROM '.$this->p.'issuers_staff m WHERE m.staff_id=s.staffid AND m.issuer_id=? AND m.can_view=1) OR EXISTS(SELECT 1 FROM '.$this->p.'issuers_installation_identity i WHERE i.id=1 AND i.master_staff_id=s.staffid)) ORDER BY s.firstname,s.lastname',[$this->issuer]);
        $rows=array_values(array_filter($rows,fn($row)=>$this->access->member($this->issuer,(int)$row['staffid'])));$this->member();return$rows;
    }
    public function reminder($id):array
    {
        $this->member();$row=$this->rows('SELECT * FROM '.$this->p.'company_reminders WHERE issuer_id=? AND id=?',[$this->issuer,IssuerPolicy::id($id)])[0]??null;
        if(!$row)throw new DomainException('company_auxiliary_access_denied');$this->validateStoredRelation($row);return$row;
    }
    public function saveReminder(string $type,$relId,array $input,$id=null,$revision=null):int
    {
        if(array_diff(array_keys($input),['description','date','staff','notify_by_email']))throw new DomainException('company_auxiliary_invalid_input');
        $description=self::text($input['description']??null,16000);$date=self::text($input['date']??null,19);$parsed=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$date,new DateTimeZone('UTC'));
        if(!$parsed||$parsed->format('Y-m-d H:i:s')!==$date)throw new DomainException('company_auxiliary_invalid_input');
        $staff=IssuerPolicy::id($input['staff']??null);$notify=self::bit($input['notify_by_email']??0);$id=$id===null?null:IssuerPolicy::id($id);$revision=$id===null?null:self::revision($revision);
        return$this->tx(function()use($type,$relId,$description,$date,$staff,$notify,$id,$revision):int{
            $ref=$this->relation($type,$relId,'edit');if(!$this->access->can($this->issuer,$staff,$ref['feature'],'view'))throw new DomainException('company_auxiliary_access_denied');
            $recipientRef=NativeCompanyResourceReferences::resolve($this->db,$this->p,$this->issuer,$staff,$type,$relId,'view');if($recipientRef['source_kind']!==$ref['source_kind']||$recipientRef['source_id']!==$ref['source_id'])throw new DomainException('company_auxiliary_access_denied');
            if($id===null){
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_reminders(issuer_id,rel_type,rel_id,source_kind,source_id,staff,creator,description,date,isnotified,notify_by_email,revision) VALUES(?,?,?,?,?,?,?,?,?,0,?,1)',[$this->issuer,$type,$ref['canonical_id'],$ref['source_kind'],$ref['source_id'],$staff,$this->actor,$description,$date,$notify]);$this->assertInserted();$id=(int)$this->db->insert_id();
            }else{
                $row=$this->reminder($id);$this->author($row,'creator');if($row['rel_type']!==$type||(string)$row['rel_id']!==(string)$ref['canonical_id']||(int)$row['revision']!==$revision||(int)$row['isnotified']!==0)throw new DomainException('company_auxiliary_stale');
                IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_reminders SET description=?,date=?,staff=?,notify_by_email=?,revision=revision+1 WHERE issuer_id=? AND id=? AND revision=? AND isnotified=0',[$description,$date,$staff,$notify,$this->issuer,$id,$revision]);
            }
            $saved=$this->reminder($id);if($saved['description']!==$description||(int)$saved['revision']!==($revision===null?1:$revision+1))throw new RuntimeException('issuer_auxiliary_write_unconfirmed');$this->audit('native_reminder_saved',$id);if($recipientRef!==NativeCompanyResourceReferences::resolve($this->db,$this->p,$this->issuer,$staff,$type,$relId,'view'))throw new DomainException('company_auxiliary_access_denied');return$id;
        },$type);
    }
    public function deleteReminder($id,$revision):void
    {
        $id=IssuerPolicy::id($id);$revision=self::revision($revision);$this->member();$relationType=$this->rows('SELECT rel_type FROM '.$this->p.'company_reminders WHERE issuer_id=? AND id=?',[$this->issuer,$id])[0]['rel_type']??null;if(!is_string($relationType))throw new DomainException('company_auxiliary_access_denied');$this->tx(function()use($id,$revision):void{
            $row=$this->reminder($id);$this->author($row,'creator');$this->validateStoredRelation($row,'edit');if((int)$row['revision']!==$revision)throw new DomainException('company_auxiliary_stale');
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_reminders WHERE issuer_id=? AND id=? AND revision=?',[$this->issuer,$id,$revision]);
            if($this->rows('SELECT id FROM '.$this->p.'company_reminders WHERE issuer_id=? AND id=?',[$this->issuer,$id]))throw new RuntimeException('issuer_auxiliary_write_unconfirmed');$this->audit('native_reminder_deleted',$id);
        },$relationType);
    }

}

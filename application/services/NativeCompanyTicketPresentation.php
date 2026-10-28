<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketCommands.php';
require_once __DIR__.'/NativeCompanyKnowledgePresentation.php';

/** Explicit data for the original add template; no global models/options/hooks. */
final class NativeCompanyTicketPresentation
{
    public function __construct(private $db, private string $p) {}

    public function add(int $issuer, int $actor): array
    {
        $acl = new IssuerResourceAccess($this->db, $this->p);
        if (!$acl->can($issuer, $actor, 'tickets', 'view') || !$acl->can($issuer, $actor, 'tickets', 'create')) {
            throw new DomainException('native_ticket_create_denied');
        }
        $preferences = (new NativeCompanyTicketPreferences($this->db, $this->p))->read($issuer, $actor);
        $options = $preferences['values'];
        $catalog = new NativeCompanyTicketCatalog($this->db, $this->p);
        $departments = new NativeCompanyDepartments($this->db, $this->p);
        $staff = (new NativeCompanyTicketCommands($this->db, $this->p))->staffOptions($issuer, $actor);
        if ($options['access_tickets_to_none_staff_members'] === '0') {
            $staff = array_values(array_filter($staff, function ($member) {
                return (bool) IssuerStorage::query($this->db, 'SELECT staffid FROM '.$this->p.'staff WHERE staffid=? AND active=1 AND is_not_staff=0', [$member['staffid']])->row_array();
            }));
        }
        $groups = [];
        if ($acl->can($issuer, $actor, 'knowledge_base', 'view')) {
            $knowledge = new NativeCompanyKnowledge($this->db, $this->p);
            foreach ($knowledge->groups($issuer, $actor) as $group) {
                if ((int) $group['active'] === 1) $groups[$group['groupid']] = ['name'=>$group['name'], 'articles'=>[]];
            }
            foreach ($knowledge->articles($issuer, $actor) as $article) {
                if ((int) $article['active'] === 1 && (int) $article['staff_article'] === 0 && isset($groups[$article['articlegroup']])) {
                    $groups[$article['articlegroup']]['articles'][] = [
                        'articleid'=>$article['articleid'], 'subject'=>$article['subject'],
                        'public_url'=>NativeCompanyKnowledgePresentation::publicUrl($article['slug'], $issuer),
                    ];
                }
            }
        }
        $out = [
            'departments'=>!$acl->administrative($issuer, $actor) && $options['staff_access_only_assigned_departments'] === '1'
                ? $departments->forStaff($issuer, $actor) : $departments->catalog($issuer, $actor),
            'priorities'=>$catalog->list($issuer, $actor, 'priority'),
            'services'=>$catalog->list($issuer, $actor, 'service'),
            'predefined_replies'=>$catalog->list($issuer, $actor, 'predefined_reply'),
            'staff'=>$staff,
            'native_ticket_presentation'=>[
                'actor'=>$actor, 'options'=>$options, 'default_priority'=>$options['email_piping_default_priority'],
                'inline_service'=>$acl->administrative($issuer, $actor), 'knowledge_groups'=>array_values($groups),
                'ai_available'=>false,
                'limitations'=>'IA e envio de notificações não estão disponíveis neste contexto. Os anexos aceitos são PDF, PNG e JPG, com até 2 MB por arquivo.',
            ],
        ];
        if (!$acl->can($issuer, $actor, 'tickets', 'view') || !$acl->can($issuer, $actor, 'tickets', 'create')) throw new DomainException('native_ticket_create_denied');
        return $out;
    }

    /** Contact capability owns the lookup; customer-profile privileges are not substituted. */
    public function contact(int $issuer, int $actor, $id, $client = null): array
    {
        $contacts = new NativeCompanyTicketContacts($this->db, $this->p);
        return IssuerTransaction::run($this->db, $this->p, $contacts->tables(), function () use ($contacts, $issuer, $actor, $id, $client) {
            $row = $contacts->getInOwnedTransaction($issuer, $actor, $id, $client);
            if (!isset($row['ticket_emails']) || !in_array((string) $row['ticket_emails'], ['0', '1'], true)) throw new DomainException('native_ticket_contact_preferences_unavailable');
            return $row;
        });
    }

    public function customerHasProjects(int $issuer, int $actor, int $client): bool
    {
        $acl = new IssuerResourceAccess($this->db, $this->p);
        if (!$acl->can($issuer, $actor, 'projects', 'view')) return false;
        $found = (bool) IssuerStorage::query($this->db, 'SELECT id FROM '.$this->p.'company_projects WHERE issuer_id=? AND client_id=? LIMIT 1', [$issuer, $client])->row_array();
        if (!$found && $acl->primaryId() === $issuer) $found = (bool) IssuerStorage::query($this->db, 'SELECT id FROM '.$this->p.'projects WHERE clientid=? LIMIT 1', [$client])->row_array();
        if (!$acl->can($issuer, $actor, 'projects', 'view')) throw new DomainException('native_ticket_project_denied');
        return $found;
    }

    public function project(int $issuer, int $actor, $id): array
    {
        $ref = NativeCompanyResourceReferences::resolve($this->db, $this->p, $issuer, $actor, 'project', $id);
        $owned = $ref['source_kind'] === 'company_project';
        $row = IssuerStorage::query($this->db, 'SELECT '.($owned?'client_id':'clientid').' AS client_id FROM '.$this->p.($owned?'company_projects':'projects').' WHERE id=?'.($owned?' AND issuer_id=?':''), $owned?[$ref['source_id'], $issuer]:[$ref['source_id']])->row_array();
        if (!$row || NativeCompanyResourceReferences::resolve($this->db, $this->p, $issuer, $actor, 'project', $id) !== $ref) throw new DomainException('native_ticket_project_denied');
        return $ref + ['client_id'=>(int) $row['client_id']];
    }
}

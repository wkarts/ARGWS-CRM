<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Sm_contract_addendum_merge_fields extends App_merge_fields
{
    public function build()
    {
        return [
                [
                    'name'      => 'Contract ID',
                    'key'       => '{contract_id}',
                    'available' => [
                        'sm_contract_addendum',
                    ],
                ],
                [
                    'name'      => 'Contract Subject',
                    'key'       => '{contract_subject}',
                    'available' => [
                        'sm_contract_addendum',
                    ],
                ],
                [
                    'name'      => 'Contract Description',
                    'key'       => '{contract_description}',
                    'available' => [
                        'sm_contract_addendum',
                    ],
                ],
                [
                    'name'      => 'Contract Date Start',
                    'key'       => '{contract_datestart}',
                    'available' => [
                        'sm_contract_addendum',
                    ],
                ],
                [
                    'name'      => 'Contract Date End',
                    'key'       => '{contract_dateend}',
                    'available' => [
                        'sm_contract_addendum',
                    ],
                ],
                [
                    'name'      => 'Contract Value',
                    'key'       => '{contract_contract_value}',
                    'available' => [
                        'sm_contract_addendum',
                    ],
                ],
                [
                    'name'      => 'Contract Link',
                    'key'       => '{contract_link}',
                    'available' => [
                        'sm_contract_addendum',
                    ],
                ],
                [
                    'name'      => 'Contract Type',
                    'key'       => '{contract_type}',
                    'available' => [
                        'sm_contract_addendum',
                    ],
                ],
                [
                    'name'      => 'Project name',
                    'key'       => '{project_name}',
                    'available' => [
                        'sm_contract_addendum',
                    ],
                ],
                [
                    'name'      => 'Contract Addendum ID',
                    'key'       => '{contract_addendum_id}',
                    'available' => [
                        'sm_contract_addendum',
                    ],
                ],
                [
                    'name'      => 'Contract Addendum Subject',
                    'key'       => '{contract_addendum_subject}',
                    'available' => [
                        'sm_contract_addendum',
                    ],
                ],
                [
                    'name'      => 'Contract Addendum Description',
                    'key'       => '{contract_addendum_description}',
                    'available' => [
                        'sm_contract_addendum',
                    ],
                ],
                [
                    'name'      => 'Contract Addendum Datestart',
                    'key'       => '{contract_addendum_datestart}',
                    'available' => [
                        'sm_contract_addendum',
                    ],
                ],
                
            ];
    }

    /**
     * Merge field for contracts
     * @param  mixed $contract_id contract id
     * @return array
     */
    public function format($contract_id)
    {
        $fields = [];
        $this->ci->db->select(db_prefix() . 'sm_contracts.id as id, '.db_prefix() . 'sm_contracts.subject as subject, '.db_prefix() . 'sm_contracts.description as description, '.db_prefix() . 'sm_contracts.datestart as datestart, '.db_prefix() . 'sm_contracts.dateend as dateend, contract_value, '.db_prefix() . 'sm_contracts.hash as hash, project_id, ' . db_prefix() . 'contracts_types.name as type_name, '.db_prefix() . 'sm_contract_addendums.id as contract_addendum_id, '.db_prefix() . 'sm_contract_addendums.subject as contract_addendum_subject , '.db_prefix() . 'sm_contract_addendums.description as contract_addendum_description  , '.db_prefix() . 'sm_contract_addendums.datestart as contract_addendum_datestart ');
        $this->ci->db->where('sm_contracts.id', $contract_id);
        $this->ci->db->join(db_prefix() . 'sm_contracts', '' . db_prefix() . 'sm_contracts.id = ' . db_prefix() . 'sm_contract_addendums.contract_id', 'left');
        $this->ci->db->join(db_prefix() . 'contracts_types', '' . db_prefix() . 'contracts_types.id = ' . db_prefix() . 'sm_contracts.contract_type', 'left');
        $contract = $this->ci->db->get(db_prefix() . 'sm_contract_addendums')->row();

        if (!$contract) {
            return $fields;
        }

        $currency = get_base_currency();

        $fields['{contract_id}']             = $contract->id;
        $fields['{contract_subject}']        = $contract->subject;
        $fields['{contract_type}']           = $contract->type_name;
        $fields['{contract_description}']    = nl2br($contract->description);
        $fields['{contract_datestart}']      = _d($contract->datestart);
        $fields['{contract_dateend}']        = _d($contract->dateend);
        $fields['{contract_contract_value}'] = app_format_money($contract->contract_value, $currency);

        $fields['{contract_link}']      = site_url('contract/' . $contract->id . '/' . $contract->hash);
        $fields['{project_name}']       = get_project_name_by_id($contract->project_id);
        $fields['{contract_short_url}'] = get_contract_shortlink($contract);

        $fields['{contract_addendum_id}']             = $contract->contract_addendum_id;
        $fields['{contract_addendum_subject}']        = $contract->contract_addendum_subject;
        $fields['{contract_addendum_description}']    = nl2br($contract->contract_addendum_description);
        $fields['{contract_addendum_datestart}']      = _d($contract->contract_addendum_datestart);

        return hooks()->apply_filters('sm_contract_merge_fields', $fields, [
        'id'       => $contract_id,
        'contract' => $contract,
     ]);
    }
}

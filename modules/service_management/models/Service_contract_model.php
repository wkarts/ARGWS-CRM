<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Service_contract_model extends App_Model
{
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Get contract/s
	 * @param  mixed  $id         contract id
	 * @param  array   $where      perform where
	 * @param  boolean $for_editor if for editor is false will replace the field if not will not replace
	 * @return mixed
	 */
	public function get($id = '', $where = [], $for_editor = false)
	{
		$this->db->select('*,' . db_prefix() . 'contracts_types.name as type_name,' . db_prefix() . 'sm_contracts.id as id, ' . db_prefix() . 'sm_contracts.addedfrom');
		$this->db->where($where);
		$this->db->join(db_prefix() . 'contracts_types', '' . db_prefix() . 'contracts_types.id = ' . db_prefix() . 'sm_contracts.contract_type', 'left');
		$this->db->join(db_prefix() . 'clients', '' . db_prefix() . 'clients.userid = ' . db_prefix() . 'sm_contracts.client');
		if (is_numeric($id)) {
			$this->db->where(db_prefix() . 'sm_contracts.id', $id);
			$contract = $this->db->get(db_prefix() . 'sm_contracts')->row();
			if ($contract) {
				$contract->attachments = $this->get_contract_attachments('', $contract->id);
				if ($for_editor == false) {
					$this->load->library('merge_fields/client_merge_fields');
					$this->load->library('merge_fields/sm_contract_merge_fields');
					$this->load->library('merge_fields/other_merge_fields');

					$merge_fields = [];
					$merge_fields = array_merge($merge_fields, $this->sm_contract_merge_fields->format($id));
					$merge_fields = array_merge($merge_fields, $this->client_merge_fields->format($contract->client));
					$merge_fields = array_merge($merge_fields, $this->other_merge_fields->format());
					foreach ($merge_fields as $key => $val) {
						if (stripos($contract->content ?? '', $key) !== false) {
							$contract->content = str_ireplace($key, $val, $contract->content ?? '');
						} else {
							$contract->content = str_ireplace($key, '', $contract->content ?? '');
						}
					}
				}
			}

			return $contract;
		}
		$contracts = $this->db->get(db_prefix() . 'sm_contracts')->result_array();
		$i         = 0;
		foreach ($contracts as $contract) {
			$contracts[$i]['attachments'] = $this->get_contract_attachments('', $contract['id']);
			$i++;
		}

		return $contracts;
	}

	/**
	 * Select unique contracts years
	 * @return array
	 */
	public function get_contracts_years()
	{
		return $this->db->query('SELECT DISTINCT(YEAR(datestart)) as year FROM ' . db_prefix() . 'sm_contracts')->result_array();
	}

	/**
	 * @param  integer ID
	 * @return object
	 * Retrieve contract attachments from database
	 */
	public function get_contract_attachments($attachment_id = '', $id = '')
	{
		if (is_numeric($attachment_id)) {
			$this->db->where('id', $attachment_id);

			return $this->db->get(db_prefix() . 'files')->row();
		}
		$this->db->order_by('dateadded', 'desc');
		$this->db->where('rel_id', $id);
		$this->db->where('rel_type', 'sm_contract');

		return $this->db->get(db_prefix() . 'files')->result_array();
	}

	/**
	 * @param   array $_POST data
	 * @return  integer Insert ID
	 * Add new contract
	 */
	public function add($data)
	{
		$data['dateadded'] = date('Y-m-d H:i:s');
		$data['addedfrom'] = get_staff_user_id();

		$data['datestart'] = to_sql_date($data['datestart']);
		unset($data['attachment']);
		if ($data['dateend'] == '') {
			unset($data['dateend']);
		} else {
			$data['dateend'] = to_sql_date($data['dateend']);
		}

		if (isset($data['trash']) && ($data['trash'] == 1 || $data['trash'] === 'on')) {
			$data['trash'] = 1;
		} else {
			$data['trash'] = 0;
		}

		if (isset($data['not_visible_to_client']) && ($data['not_visible_to_client'] == 1 || $data['not_visible_to_client'] === 'on')) {
			$data['not_visible_to_client'] = 1;
		} else {
			$data['not_visible_to_client'] = 0;
		}
		if (isset($data['custom_fields'])) {
			$custom_fields = $data['custom_fields'];
			unset($data['custom_fields']);
		}

		$data['hash'] = app_generate_hash();

		$data = hooks()->apply_filters('before_sm_contract_added', $data);

		$this->db->insert(db_prefix() . 'sm_contracts', $data);
		$insert_id = $this->db->insert_id();

		if ($insert_id) {
			if (isset($custom_fields)) {
				handle_custom_fields_post($insert_id, $custom_fields);
			}
			hooks()->do_action('after_sm_contract_added', $insert_id);
			log_activity('New Contract Added [' . $data['subject'] . ']');

			return $insert_id;
		}

		return false;
	}

	/**
	 * @param  array $_POST data
	 * @param  integer Contract ID
	 * @return boolean
	 */
	public function update($data, $id)
	{
		$affectedRows = 0;

		$data['datestart'] = to_sql_date($data['datestart']);
		if ($data['dateend'] == '') {
			$data['dateend'] = null;
		} else {
			$data['dateend'] = to_sql_date($data['dateend']);
		}
		if (isset($data['trash'])) {
			$data['trash'] = 1;
		} else {
			$data['trash'] = 0;
		}
		if (isset($data['not_visible_to_client'])) {
			$data['not_visible_to_client'] = 1;
		} else {
			$data['not_visible_to_client'] = 0;
		}

		$data = hooks()->apply_filters('before_sm_contract_updated', $data, $id);

		if (isset($data['custom_fields'])) {
			$custom_fields = $data['custom_fields'];
			if (handle_custom_fields_post($id, $custom_fields)) {
				$affectedRows++;
			}
			unset($data['custom_fields']);
		}

		$this->db->where('id', $id);
		$this->db->update(db_prefix() . 'sm_contracts', $data);

		if ($this->db->affected_rows() > 0) {
			hooks()->do_action('after_sm_contract_updated', $id);
			log_activity('Contract Updated [' . $data['subject'] . ']');

			return true;
		}

		return $affectedRows > 0;
	}

	/**
	 * clear signature
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function clear_signature($id)
	{
		$this->db->select('signature');
		$this->db->where('id', $id);
		$contract = $this->db->get(db_prefix() . 'sm_contracts')->row();

		if ($contract) {
			$this->db->where('id', $id);
			$this->db->update(db_prefix() . 'sm_contracts', array_merge(get_acceptance_info_array(true), ['signed' => 0]));

			if (!empty($contract->signature)) {
				unlink(SM_CONTRACT_FOLDER . $id . '/' . $contract->signature);
			}

			return true;
		}


		return false;
	}

	/**
	* Add contract comment
	* @param mixed  $data   $_POST comment data
	* @param boolean $client is request coming from the client side
	*/
	public function add_comment($data, $client = false)
	{
		if (is_staff_logged_in()) {
			$client = false;
		}

		if (isset($data['action'])) {
			unset($data['action']);
		}

		$data['dateadded'] = date('Y-m-d H:i:s');

		if ($client == false) {
			$data['staffid'] = get_staff_user_id();
		}

		$data['content'] = nl2br($data['content']);
		$this->db->insert(db_prefix() . 'sm_contract_comments', $data);
		$insert_id = $this->db->insert_id();

		if ($insert_id) {
			$contract = $this->get($data['contract_id']);

			if (($contract->not_visible_to_client == '1' || $contract->trash == '1') && $client == false) {
				return true;
			}

			if ($client == true) {

				// Get creator
				$this->db->select('staffid, email, phonenumber');
				$this->db->where('staffid', $contract->addedfrom);
				$staff_contract = $this->db->get(db_prefix() . 'staff')->result_array();

				$notifiedUsers = [];

				foreach ($staff_contract as $member) {
					$notified = add_notification([
						'description'     => 'not_contract_comment_from_client',
						'touserid'        => $member['staffid'],
						'fromcompany'     => 1,
						'fromuserid'      => 0,
						'link'            => 'contracts/contract/' . $data['contract_id'],
						'additional_data' => serialize([
							$contract->subject,
						]),
					]);

					if ($notified) {
						array_push($notifiedUsers, $member['staffid']);
					}

					$template     = mail_template('contract_comment_to_staff', $contract, $member);
					$merge_fields = $template->get_merge_fields();
					$template->send();

					// Send email/sms to admin that client commented
					$this->app_sms->trigger(SMS_TRIGGER_CONTRACT_NEW_COMMENT_TO_STAFF, $member['phonenumber'], $merge_fields);
				}
				pusher_trigger_notification($notifiedUsers);
			} else {
				$contacts = $this->clients_model->get_contacts($contract->client, ['active' => 1, 'contract_emails' => 1]);

				foreach ($contacts as $contact) {
					$template     = mail_template('contract_comment_to_customer', $contract, $contact);
					$merge_fields = $template->get_merge_fields();
					$template->send();

					$this->app_sms->trigger(SMS_TRIGGER_CONTRACT_NEW_COMMENT_TO_CUSTOMER, $contact['phonenumber'], $merge_fields);
				}
			}

			return true;
		}

		return false;
	}

	public function edit_comment($data, $id)
	{
		$this->db->where('id', $id);
		$this->db->update(db_prefix() . 'sm_contract_comments', [
			'content' => nl2br($data['content']),
		]);

		if ($this->db->affected_rows() > 0) {
			return true;
		}

		return false;
	}

	/**
	 * Get contract comments
	 * @param  mixed $id contract id
	 * @return array
	 */
	public function get_comments($id)
	{
		$this->db->where('contract_id', $id);
		$this->db->order_by('dateadded', 'ASC');

		return $this->db->get(db_prefix() . 'sm_contract_comments')->result_array();
	}

	/**
	 * Get contract single comment
	 * @param  mixed $id  comment id
	 * @return object
	 */
	public function get_comment($id)
	{
		$this->db->where('id', $id);

		return $this->db->get(db_prefix() . 'sm_contract_comments')->row();
	}

	/**
	 * Remove contract comment
	 * @param  mixed $id comment id
	 * @return boolean
	 */
	public function remove_comment($id)
	{
		$comment = $this->get_comment($id);

		$this->db->where('id', $id);
		$this->db->delete(db_prefix() . 'sm_contract_comments');
		if ($this->db->affected_rows() > 0) {
			log_activity('Contract Comment Removed [Contract ID:' . $comment->contract_id . ', Comment Content: ' . $comment->content . ']');

			return true;
		}

		return false;
	}

	public function copy($id)
	{
		$contract       = $this->get($id, [], true);
		$fields         = $this->db->list_fields(db_prefix() . 'sm_contracts');
		$newContactData = [];

		foreach ($fields as $field) {
			if (isset($contract->$field)) {
				$newContactData[$field] = $contract->$field;
			}
		}

		unset($newContactData['id']);

		$newContactData['trash']            = 0;
		$newContactData['isexpirynotified'] = 0;
		$newContactData['isexpirynotified'] = 0;
		$newContactData['signed']           = 0;
		$newContactData['signature']        = null;

		$newContactData = array_merge($newContactData, get_acceptance_info_array(true));

		if ($contract->dateend) {
			$dStart                    = new DateTime($contract->datestart);
			$dEnd                      = new DateTime($contract->dateend);
			$dDiff                     = $dStart->diff($dEnd);
			$newContactData['dateend'] = _d(date('Y-m-d', strtotime(date('Y-m-d', strtotime('+' . $dDiff->days . 'DAY')))));
		} else {
			$newContactData['dateend'] = '';
		}

		$newId = $this->add($newContactData);

		if ($newId) {
			$custom_fields = get_custom_fields('contracts');
			foreach ($custom_fields as $field) {
				$value = get_custom_field_value($id, $field['id'], 'contracts', false);
				if ($value != '') {
					$this->db->insert(db_prefix() . 'customfieldsvalues', [
						'relid'   => $newId,
						'fieldid' => $field['id'],
						'fieldto' => 'contracts',
						'value'   => $value,
					]);
				}
			}
		}

		return $newId;
	}

	/**
	 * @param  integer ID
	 * @return boolean
	 * Delete contract, also attachment will be removed if any found
	 */
	public function delete($id)
	{
		hooks()->do_action('before_contract_deleted', $id);
		$this->clear_signature($id);
		$contract = $this->get($id);
		$this->db->where('id', $id);
		$this->db->delete(db_prefix() . 'sm_contracts');
		if ($this->db->affected_rows() > 0) {
			$this->db->where('contract_id', $id);
			$this->db->delete(db_prefix() . 'sm_contract_addendums');
			
			$this->db->where('contract_id', $id);
			$this->db->delete(db_prefix() . 'sm_contract_comments');

			// Delete the custom field values
			$this->db->where('relid', $id);
			$this->db->where('fieldto', 'contracts');
			$this->db->delete(db_prefix() . 'customfieldsvalues');

			$this->db->where('rel_id', $id);
			$this->db->where('rel_type', 'sm_contract');
			$attachments = $this->db->get(db_prefix() . 'files')->result_array();
			foreach ($attachments as $attachment) {
				$this->delete_contract_attachment($attachment['id']);
			}

			$this->db->where('rel_id', $id);
			$this->db->where('rel_type', 'sm_contract');
			$this->db->delete(db_prefix() . 'notes');


			$this->db->where('contractid', $id);
			$this->db->delete(db_prefix() . 'sm_contract_renewals');
			// Get related tasks
			$this->db->where('rel_type', 'sm_contract');
			$this->db->where('rel_id', $id);
			$tasks = $this->db->get(db_prefix() . 'tasks')->result_array();
			foreach ($tasks as $task) {
				$this->tasks_model->delete_task($task['id']);
			}

			delete_tracked_emails($id, 'contract');

			log_activity('Contract Deleted [' . $id . ']');

			return true;
		}

		return false;
	}

	/**
	 * Mark the contract as signed manually
	 *
	 * @param  int $id contract id
	 *
	 * @return boolean
	 */
	public function mark_as_signed($id)
	{
		$this->db->where('id', $id);
		$this->db->update('sm_contracts', ['marked_as_signed' => 1]);

		return $this->db->affected_rows() > 0;
	}

	/**
	 * Unmark the contract as signed manually
	 *
	 * @param  int $id contract id
	 *
	 * @return boolean
	 */
	public function unmark_as_signed($id)
	{
		$this->db->where('id', $id);
		$this->db->update('sm_contracts', ['marked_as_signed' => 0]);

		return $this->db->affected_rows() > 0;
	}

	/**
	 * Function that send contract to customer
	 * @param  mixed  $id        contract id
	 * @param  boolean $attachpdf to attach pdf or not
	 * @param  string  $cc        Email CC
	 * @return boolean
	 */
	public function send_contract_to_client($id, $attachpdf = true, $cc = '')
	{
		$contract = $this->get($id);

		if ($attachpdf) {
			set_mailing_constant();
			$pdf    = contract_pdf($contract);
			$attach = $pdf->Output(slug_it($contract->subject) . '.pdf', 'S');
		}

		$sent_to = $this->input->post('sent_to');
		$sent    = false;

		if (is_array($sent_to)) {
			$i = 0;
			foreach ($sent_to as $contact_id) {
				if ($contact_id != '') {
					$contact = $this->clients_model->get_contact($contact_id);

					// Send cc only for the first contact
					if (!empty($cc) && $i > 0) {
						$cc = '';
					}

					$template = mail_template('contract_send_to_customer', $contract, $contact, $cc);

					if ($attachpdf) {
						$template->add_attachment([
							'attachment' => $attach,
							'filename'   => slug_it($contract->subject) . '.pdf',
							'type'       => 'application/pdf',
						]);
					}

					if ($template->send()) {
						$sent = true;
					}
				}
				$i++;
			}
		} else {
			return false;
		}
		if ($sent) {
			return true;
		}

		return false;
	}

	/**
	 * Delete contract attachment
	 * @param  mixed $attachment_id
	 * @return boolean
	 */
	public function delete_contract_attachment($attachment_id)
	{
		$deleted    = false;
		$attachment = $this->get_contract_attachments($attachment_id);

		if ($attachment) {
			if (empty($attachment->external)) {
				unlink(SM_CONTRACT_FOLDER . $attachment->rel_id . '/' . $attachment->file_name);
			}
			$this->db->where('id', $attachment->id);
			$this->db->delete(db_prefix() . 'files');
			if ($this->db->affected_rows() > 0) {
				$deleted = true;
				log_activity('Contract Attachment Deleted [ContractID: ' . $attachment->rel_id . ']');
			}

			if (is_dir(SM_CONTRACT_FOLDER . $attachment->rel_id)) {
				// Check if no attachments left, so we can delete the folder also
				$other_attachments = list_files(SM_CONTRACT_FOLDER . $attachment->rel_id);
				if (count($other_attachments) == 0) {
					// okey only index.html so we can delete the folder also
					delete_dir(SM_CONTRACT_FOLDER . $attachment->rel_id);
				}
			}
		}

		return $deleted;
	}

	/**
	 * Renew contract
	 * @param  mixed $data All $_POST data
	 * @return mixed
	 */
	public function renew($data)
	{
		$keepSignature = isset($data['renew_keep_signature']);
		if ($keepSignature) {
			unset($data['renew_keep_signature']);
		}
		$data['new_start_date']      = to_sql_date($data['new_start_date']);
		$data['new_end_date']        = to_sql_date($data['new_end_date']);
		$data['date_renewed']        = date('Y-m-d H:i:s');
		$data['renewed_by']          = get_staff_full_name(get_staff_user_id());
		$data['renewed_by_staff_id'] = get_staff_user_id();
		if (!is_date($data['new_end_date'])) {
			unset($data['new_end_date']);
		}
		// get the original contract so we can check if is expiry notified on delete the expiry to revert
		$_contract                         = $this->get($data['contractid']);
		$data['is_on_old_expiry_notified'] = $_contract->isexpirynotified;
		$this->db->insert(db_prefix() . 'sm_contract_renewals', $data);
		$insert_id = $this->db->insert_id();
		if ($insert_id) {
			$this->db->where('id', $data['contractid']);
			$_data = [
				'datestart'        => $data['new_start_date'],
				'contract_value'   => $data['new_value'],
				'isexpirynotified' => 0,
			];

			if (isset($data['new_end_date'])) {
				$_data['dateend'] = $data['new_end_date'];
			}

			if (!$keepSignature) {
				$_data           = array_merge($_data, get_acceptance_info_array(true));
				$_data['signed'] = 0;
				if (!empty($_contract->signature)) {
					unlink(SM_CONTRACT_FOLDER . $data['contractid'] . '/' . $_contract->signature);
				}
			}

			$this->db->update(db_prefix() . 'sm_contracts', $_data);
			if ($this->db->affected_rows() > 0) {
				log_activity('Contract Renewed [ID: ' . $data['contractid'] . ']');

				return true;
			}
			// delete the previous entry
			$this->db->where('id', $insert_id);
			$this->db->delete(db_prefix() . 'sm_contract_renewals');

			return false;
		}

		return false;
	}

	/**
	 * Delete contract renewal
	 * @param  mixed $id         renewal id
	 * @param  mixed $contractid contract id
	 * @return boolean
	 */
	public function delete_renewal($id, $contractid)
	{
		// check if this renewal is last so we can revert back the old values, if is not last we wont do anything
		$this->db->select('id')->from(db_prefix() . 'sm_contract_renewals')->where('contractid', $contractid)->order_by('id', 'desc')->limit(1);
		$query                 = $this->db->get();
		$last_contract_renewal = $query->row()->id;
		$is_last               = false;
		if ($last_contract_renewal == $id) {
			$is_last = true;
			$this->db->where('id', $id);
			$original_renewal = $this->db->get(db_prefix() . 'sm_contract_renewals')->row();
		}

		$contract = $this->get($id);
		$this->db->where('id', $id);
		$this->db->delete(db_prefix() . 'sm_contract_renewals');
		if ($this->db->affected_rows() > 0) {
			if (!is_null($contract->short_link)) {
				app_archive_short_link($contract->short_link);
			}

			if ($is_last == true) {
				$this->db->where('id', $contractid);
				$data = [
					'datestart'        => $original_renewal->old_start_date,
					'contract_value'   => $original_renewal->old_value,
					'isexpirynotified' => $original_renewal->is_on_old_expiry_notified,
				];
				if ($original_renewal->old_end_date != '0000-00-00') {
					$data['dateend'] = $original_renewal->old_end_date;
				}
				$this->db->update(db_prefix() . 'sm_contracts', $data);
			}
			log_activity('Contract Renewed [RenewalID: ' . $id . ', ContractID: ' . $contractid . ']');

			return true;
		}

		return false;
	}

	/**
	 * Get the contracts about to expired in the given days
	 *
	 * @param  integer|null $staffId
	 * @param  integer $days
	 *
	 * @return array
	 */
	public function get_contracts_about_to_expire($staffId = null, $days = 7)
	{
		$diff1 = date('Y-m-d', strtotime('-' . $days . ' days'));
		$diff2 = date('Y-m-d', strtotime('+' . $days . ' days'));

		if ($staffId && ! staff_can('view', 'contracts', $staffId)) {
			$this->db->where('addedfrom', $staffId);
		}

		$this->db->select('id,subject,client,datestart,dateend');

		$this->db->where('dateend IS NOT NULL');
		$this->db->where('trash', 0);
		$this->db->where('dateend >=', $diff1);
		$this->db->where('dateend <=', $diff2);

		return $this->db->get(db_prefix() . 'sm_contracts')->result_array();
	}

	/**
	 * Get contract renewals
	 * @param  mixed $id contract id
	 * @return array
	 */
	public function get_contract_renewal_history($id)
	{
		$this->db->where('contractid', $id);
		$this->db->order_by('date_renewed', 'asc');

		return $this->db->get(db_prefix() . 'sm_contract_renewals')->result_array();
	}

	/**
	 * @param  integer ID (optional)
	 * @return mixed
	 * Get contract type object based on passed id if not passed id return array of all types
	 */
	public function get_contract_types($id = '')
	{
		return $this->contract_type_get($id);
	}

	/**
	 * @param  integer ID
	 * @return mixed
	 * Delete contract type from database, if used return array with key referenced
	 */
	public function delete_contract_type($id)
	{
		return $this->contract_type_delete($id);
	}

	/**
	 * Add new contract type
	 * @param mixed $data All $_POST data
	 */
	public function add_contract_type($data)
	{
		return $this->contract_type_add($data);
	}

	/**
	 * Edit contract type
	 * @param mixed $data All $_POST data
	 * @param mixed $id Contract type id
	 */
	public function update_contract_type($data, $id)
	{
		return $this->contract_type_update($data, $id);
	}

	/**
	 * Get contract types data for chart
	 * @return array
	 */
	public function get_contracts_types_chart_data()
	{
		return $this->contract_type_get_chart_data();
	}

	/**
	* Get contract types values for chart
	* @return array
	*/
	public function get_contracts_types_values_chart_data()
	{
		return $this->contract_type_get_values_chart_data();
	}

	/*service contract type*/

	/**
	* Add new contract type
	* @param mixed $data All $_POST data
	*/
	public function contract_type_add($data)
	{
		$this->db->insert(db_prefix().'contracts_types', $data);
		$insert_id = $this->db->insert_id();
		if ($insert_id) {
			log_activity('New Contract Type Added [' . $data['name'] . ']');

			return $insert_id;
		}

		return false;
	}

	/**
	 * Edit contract type
	 * @param mixed $data All $_POST data
	 * @param mixed $id Contract type id
	 */
	public function contract_type_update($data, $id)
	{
		$this->db->where('id', $id);
		$this->db->update(db_prefix().'contracts_types', $data);
		if ($this->db->affected_rows() > 0) {
			log_activity('Contract Type Updated [' . $data['name'] . ', ID:' . $id . ']');

			return true;
		}

		return false;
	}

	/**
	 * @param  integer ID (optional)
	 * @return mixed
	 * Get contract type object based on passed id if not passed id return array of all types
	 */
	public function contract_type_get($id = '')
	{
		if (is_numeric($id)) {
			$this->db->where('id', $id);

			return $this->db->get(db_prefix().'contracts_types')->row();
		}

		$types = $this->app_object_cache->get('contract-types');

		if (!$types && !is_array($types)) {
			$types = $this->db->get(db_prefix().'contracts_types')->result_array();
			$this->app_object_cache->add('contract-types', $types);
		}

		return $types;
	}

	/**
	 * @param  integer ID
	 * @return mixed
	 * Delete contract type from database, if used return array with key referenced
	 */
	public function contract_type_delete($id)
	{
		if (is_reference_in_table('contract_type', db_prefix().'sm_contracts', $id)) {
			return [
				'referenced' => true,
			];
		}
		$this->db->where('id', $id);
		$this->db->delete(db_prefix().'contracts_types');
		if ($this->db->affected_rows() > 0) {
			log_activity('Contract Deleted [' . $id . ']');

			return true;
		}

		return false;
	}

	/**
	 * Get contract types data for chart
	 * @return array
	 */
	public function contract_type_get_chart_data()
	{
		$labels = [];
		$totals = [];
		$types  = $this->contract_type_get();
		foreach ($types as $type) {
			$total_rows_where = [
				'contract_type' => $type['id'],
				'trash'         => 0,
			];
			if (is_client_logged_in()) {
				$total_rows_where['client']                = get_client_user_id();
				$total_rows_where['not_visible_to_client'] = 0;
			} else {
				if (!has_permission('service_management', '', 'view')) {
					$total_rows_where['addedfrom'] = get_staff_user_id();
				}
			}
			$total_rows = total_rows(db_prefix().'sm_contracts', $total_rows_where);
			if ($total_rows == 0 && is_client_logged_in()) {
				continue;
			}
			array_push($labels, $type['name']);
			array_push($totals, $total_rows);
		}
		$chart = [
			'labels'   => $labels,
			'datasets' => [
				[
					'label'           => _l('contract_summary_by_type'),
					'backgroundColor' => 'rgba(3,169,244,0.2)',
					'borderColor'     => '#03a9f4',
					'borderWidth'     => 1,
					'data'            => $totals,
				],
			],
		];

		return $chart;
	}

	/**
	 * Get contract types values for chart
	 * @return array
	 */
	public function contract_type_get_values_chart_data()
	{
		$labels = [];
		$totals = [];
		$types  = $this->contract_type_get();
		foreach ($types as $type) {
			array_push($labels, $type['name']);

			$where = [
				'where' => [
					'contract_type' => $type['id'],
					'trash'         => 0,
				],
				'field' => 'contract_value',
			];

			if (!has_permission('service_management', '', 'view')) {
				$where['where']['addedfrom'] = get_staff_user_id();
			}

			$total = sum_from_table(db_prefix().'sm_contracts', $where);
			if ($total == null) {
				$total = 0;
			}
			array_push($totals, $total);
		}
		$chart = [
			'labels'   => $labels,
			'datasets' => [
				[
					'label'           => _l('contract_summary_by_type_value'),
					'backgroundColor' => 'rgba(37,155,35,0.2)',
					'borderColor'     => '#84c529',
					'tension'         => false,
					'borderWidth'     => 1,
					'data'            => $totals,
				],
			],
		];

		return $chart;
	}

	/**
	 * add contract addendum
	 * @param [type] $data 
	 */
	public function add_contract_addendum($data)
	{
		$data['dateadded'] = date('Y-m-d H:i:s');
		$data['addedfrom'] = get_staff_user_id();

		$data['datestart'] = to_sql_date($data['datestart']);
		unset($data['attachment']);

		if (isset($data['trash']) && ($data['trash'] == 1 || $data['trash'] === 'on')) {
			$data['trash'] = 1;
		} else {
			$data['trash'] = 0;
		}

		if (isset($data['not_visible_to_client']) && ($data['not_visible_to_client'] == 1 || $data['not_visible_to_client'] === 'on')) {
			$data['not_visible_to_client'] = 1;
		} else {
			$data['not_visible_to_client'] = 0;
		}

		$data['hash'] = app_generate_hash();

		$this->db->insert(db_prefix() . 'sm_contract_addendums', $data);
		$insert_id = $this->db->insert_id();

		if ($insert_id) {
			if (isset($custom_fields)) {
				handle_custom_fields_post($insert_id, $custom_fields);
			}
			hooks()->do_action('after_sm_contract_addendum_added', $insert_id);
			log_activity('New Contract Addendum Added [' . $data['subject'] . ']');

			return $insert_id;
		}

		return false;
	}

	/**
	 * update contract addendum
	 * @param  [type] $data 
	 * @param  [type] $id   
	 * @return [type]       
	 */
	public function update_contract_addendum($data, $id)
	{
		$affectedRows = 0;

		$data['datestart'] = to_sql_date($data['datestart']);

		if (isset($data['trash'])) {
			$data['trash'] = 1;
		} else {
			$data['trash'] = 0;
		}
		if (isset($data['not_visible_to_client'])) {
			$data['not_visible_to_client'] = 1;
		} else {
			$data['not_visible_to_client'] = 0;
		}

		$this->db->where('id', $id);
		$this->db->update(db_prefix() . 'sm_contract_addendums', $data);

		if ($this->db->affected_rows() > 0) {
			hooks()->do_action('after_sm_contract_addendum_updated', $id);
			log_activity('Contract Addendum Updated [' . $data['subject'] . ']');

			return true;
		}

		return $affectedRows > 0;
	}

	/**
	 * delete contract addendum
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function delete_contract_addendum($id)
	{
		hooks()->do_action('before_contract_addendum_deleted', $id);
		$this->clear_signature($id);
		$contract = $this->get($id);
		$this->db->where('id', $id);
		$this->db->delete(db_prefix() . 'sm_contract_addendums');

		if ($this->db->affected_rows() > 0) {

			return true;
		}

		return false;
	}

	/**
	 * get contract addendum
	 * @param  string  $id         
	 * @param  array   $where      
	 * @param  boolean $for_editor 
	 * @return [type]              
	 */
	public function get_contract_addendum($id = '', $where = [], $for_editor = false)
	{
		$this->db->select('*,' . db_prefix() . 'sm_contract_addendums.id as id, ' . db_prefix() . 'sm_contract_addendums.addedfrom');
		$this->db->where($where);
		if (is_numeric($id)) {
			$this->db->where(db_prefix() . 'sm_contract_addendums.id', $id);
			$contract = $this->db->get(db_prefix() . 'sm_contract_addendums')->row();
			if ($contract) {
				$client_id = 0;

				if(is_numeric($contract->contract_id) && $contract->contract_id != 0){

					$sm_contract = $this->get($contract->contract_id);
					if($sm_contract){
						$client_id = $sm_contract->client;
					}
				}
				$contract->client = $client_id;

				$contract->attachments = $this->get_contract_addendum_attachments('', $contract->id);
				if ($for_editor == false) {
					$this->load->library('merge_fields/client_merge_fields');
					$this->load->library('merge_fields/sm_contract_addendum_merge_fields');
					$this->load->library('merge_fields/other_merge_fields');

					$merge_fields = [];
					$merge_fields = array_merge($merge_fields, $this->sm_contract_addendum_merge_fields->format($id));
					$merge_fields = array_merge($merge_fields, $this->client_merge_fields->format($client_id));
					$merge_fields = array_merge($merge_fields, $this->other_merge_fields->format());
					foreach ($merge_fields as $key => $val) {
						if (stripos($contract->content, $key) !== false) {
							$contract->content = str_ireplace($key, $val, $contract->content);
						} else {
							$contract->content = str_ireplace($key, '', $contract->content);
						}
					}
				}
			}

			return $contract;
		}
		$contracts = $this->db->get(db_prefix() . 'sm_contract_addendums')->result_array();
		$i         = 0;
		foreach ($contracts as $contract) {
			$contracts[$i]['attachments'] = $this->get_contract_attachments('', $contract['id']);
			$i++;
		}

		return $contracts;
	}

	/**
	 * get contract addendum attachments
	 * @param  string $attachment_id 
	 * @param  string $id            
	 * @return [type]                
	 */
	public function get_contract_addendum_attachments($attachment_id = '', $id = '')
	{
		if (is_numeric($attachment_id)) {
			$this->db->where('id', $attachment_id);

			return $this->db->get(db_prefix() . 'files')->row();
		}
		$this->db->order_by('dateadded', 'desc');
		$this->db->where('rel_id', $id);
		$this->db->where('rel_type', 'sm_contract_addendum');

		return $this->db->get(db_prefix() . 'files')->result_array();
	}

	/**
	 * delete contract addendum attachment
	 * @param  [type] $attachment_id 
	 * @return [type]                
	 */
	public function delete_contract_addendum_attachment($attachment_id)
	{
		$deleted    = false;
		$attachment = $this->get_contract_attachments($attachment_id);

		if ($attachment) {
			if (empty($attachment->external)) {
				unlink(SM_CONTRACT_ADDENDUM_FOLDER . $attachment->rel_id . '/' . $attachment->file_name);
			}
			$this->db->where('id', $attachment->id);
			$this->db->delete(db_prefix() . 'files');
			if ($this->db->affected_rows() > 0) {
				$deleted = true;
				log_activity('Contract Attachment Deleted [ContractID: ' . $attachment->rel_id . ']');
			}

			if (is_dir(SM_CONTRACT_ADDENDUM_FOLDER . $attachment->rel_id)) {
				// Check if no attachments left, so we can delete the folder also
				$other_attachments = list_files(SM_CONTRACT_ADDENDUM_FOLDER . $attachment->rel_id);
				if (count($other_attachments) == 0) {
					// okey only index.html so we can delete the folder also
					delete_dir(SM_CONTRACT_ADDENDUM_FOLDER . $attachment->rel_id);
				}
			}
		}

		return $deleted;
	}

}

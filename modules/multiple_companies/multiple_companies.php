<?php

defined("BASEPATH") or exit("No direct script access allowed");

/*
Module Name: Múltiplas Empresas
Description: Cria múltiplas empresas com o mesmo e-mail de contato principal
Author: WKARTS
Author URI: https://t.me/wkartspro
Version: 1.0.5
*/


define('MULTIPLE_COMPANIES_MODULE_NAME', "multiple_companies");

$CI = &get_instance();


/**
 * Language
 */
register_language_files(MULTIPLE_COMPANIES_MODULE_NAME, [MULTIPLE_COMPANIES_MODULE_NAME]);


/**
 * close contact email uniq control
 */
hooks()->add_filter('contact_email_unique',function ( $status ){

    return false;

});



## CLIENT SIDE PROCESS


/**
 * Customers registered to the logged in contact are checked
 */
hooks()->add_action('after_clients_area_init',function (){

    $CI = get_instance();

    $_SESSION["all_clients"] = null;

    $contact_user_id = get_contact_user_id();

    if ( !empty( $contact_user_id ) )
    {


        $table = db_prefix() . 'contacts contact';


        $info = $CI->db->select('email')
                    ->where('id',$contact_user_id)
                    ->get($table)
                    ->row();

        if( !empty( $info->email ) )
        {

            $tableClient = db_prefix()."clients client";

            $clients = $CI->db->select("client.company , contact.userid , contact.id ")
                                ->from($table)
                                ->join($tableClient," contact.userid = client.userid ")
                                ->where('contact.email',$info->email)
                                ->order_by('client.company')
                                ->get()
                                ->result();

            foreach ( $clients as $client)
            {

                if( $client->id == $contact_user_id )
                {

                    $_SESSION["client_user_id"] = $client->userid;

                }

            }

            $_SESSION["all_clients"] = $clients;

        }


    }


});



/**
 * Customer navication menu button
 */
hooks()->add_action('customers_navigation_start',function (){


    if( is_client_logged_in() && !empty( $_SESSION["all_clients"] ) && count( $_SESSION["all_clients"] ) > 1 ) {

        $get_client_user_id = get_client_user_id();

        ?>

        <ul class="nav navbar-nav navbar-left">

            <li class="dropdown ">

                <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                    <?php echo get_company_name( $get_client_user_id )?>
                    <i class="fa fa-caret-down fa-lg" aria-hidden="true"></i>
                </a>

                <ul class="dropdown-menu animated fadeIn">

                    <?php foreach ( $_SESSION["all_clients"] as $all_client ) {

                        $hll_class = $hll_link = "";

                        if( $get_client_user_id == $all_client->userid )
                            $hll_class = "active";
                        else
                            $hll_link = site_url('multiple_companies/clients/change_auth/'.$all_client->userid);

                        echo "<li class='customers-nav-item-edit-profile $hll_class'>";

                            echo "<a href='$hll_link'> $all_client->company </a>";

                        echo '</li>';

                    } ?>

                </ul>

            </li>

        </ul>

    <?php }


});


/**
 * contact email company list for admin
 */
hooks()->add_action('after_contact_modal_content_loaded',function (){

    require_once __DIR__ . '/includes/contact_modal.php';

});


/**
 * company form
 */
hooks()->add_action('after_custom_profile_tab_content',function (){

    require_once __DIR__ . '/includes/company_modal.php';

});


hooks()->add_action('contact_updated', function ( $contact_id ){


    if ( !empty( $contact_id ) )
    {

        $contact = get_instance()->db->select('password,email')->from(db_prefix().'contacts')->where('id',$contact_id)->get()->row();

        if ( !empty( $contact->password ) )
        {

            get_instance()->db->set('password',$contact->password)
                            ->set('last_password_change',date('Y-m-d H:i:s'))
                            ->where('email',$contact->email)
                            ->update(db_prefix().'contacts');

        }

    }

});


hooks()->add_action('contact_created', function ( $contact_id ){


    if ( !empty( $contact_id ) )
    {

        $contact = get_instance()->db->select('password,email')->from(db_prefix().'contacts')->where('id',$contact_id)->get()->row();

        if ( !empty( $contact->password ) )
        {

            get_instance()->db->set('password',$contact->password)
                        ->set('last_password_change',date('Y-m-d H:i:s'))
                        ->where('email',$contact->email)
                        ->update(db_prefix().'contacts');

        }


    }

});


/**
 * profile post action
 */
hooks()->add_action('after_clients_area_init',function ( $page_obj ){



    if ( !empty( $_SESSION["all_clients"] ) && count( $_SESSION["all_clients"] ) > 1 )
    {

        $called_function = $page_obj->router->fetch_method();


        if ( $called_function == 'profile' )
        {

            // update password
            if ( $page_obj->input->post('change_password'))
            {


                $page_obj->form_validation->set_rules('oldpassword', _l('clients_edit_profile_old_password'), 'required');

                $page_obj->form_validation->set_rules('newpassword', _l('clients_edit_profile_new_password'), 'required');

                $page_obj->form_validation->set_rules('newpasswordr', _l('clients_edit_profile_new_password_repeat'), 'required|matches[newpassword]');

                if ($page_obj->form_validation->run() !== false) {


                    $oldPassword = $page_obj->input->post('oldpassword', false);
                    $newPassword = $page_obj->input->post('newpasswordr', false);


                    $password_control = false;

                    foreach ( $_SESSION["all_clients"] as $all_client )
                    {
                        $page_obj->db->where('id', $all_client->id);

                        $client = $page_obj->db->get(db_prefix() . 'contacts')->row();

                        if ( app_hasher()->CheckPassword($oldPassword, $client->password)) {
                            $password_control = true;
                        }

                    }


                    if( $password_control )
                    {

                        $password_control = false;

                        foreach ( $_SESSION["all_clients"] as $all_client )
                        {

                            $page_obj->db->where('id', $all_client->id);

                            $page_obj->db->update(db_prefix() . 'contacts', [

                                'last_password_change' => date('Y-m-d H:i:s'),

                                'password'             => app_hash_password($newPassword),

                            ]);

                            if ($page_obj->db->affected_rows() > 0) {

                                log_activity('Contact Password Changed [ContactID: ' . $all_client->id . ']');


                                $password_control = true;

                            }

                        }

                        $success = $password_control;

                    }
                    else
                    {
                        $success = [

                            'old_password_not_match' => true,

                        ];
                    }



                    if (is_array($success) && isset($success['old_password_not_match'])) {

                        set_alert('danger', _l('client_old_password_incorrect'));

                    } elseif ($success == true) {

                        set_alert('success', _l('client_password_changed'));

                    }



                    redirect(site_url('clients/profile'));

                }// end if form validation

            }


            // update profile
            if ( $page_obj->input->post('profile') )
            {

                $page_obj->form_validation->set_rules('firstname', _l('client_firstname'), 'required');

                $page_obj->form_validation->set_rules('lastname', _l('client_lastname'), 'required');



                $page_obj->form_validation->set_message('contact_email_profile_unique', _l('form_validation_is_unique'));

                $page_obj->form_validation->set_rules('email', _l('clients_email'), 'required|valid_email');



                $custom_fields = get_custom_fields('contacts', [

                    'show_on_client_portal'  => 1,

                    'required'               => 1,

                    'disalow_client_to_edit' => 0,

                ]);

                foreach ($custom_fields as $field) {

                    $field_name = 'custom_fields[' . $field['fieldto'] . '][' . $field['id'] . ']';

                    if ($field['type'] == 'checkbox' || $field['type'] == 'multiselect') {

                        $field_name .= '[]';

                    }

                    $page_obj->form_validation->set_rules($field_name, $field['name'], 'required');

                }

                if ($page_obj->form_validation->run() !== false)
                {


                    $check_email = $page_obj->input->post('email');


                    if ( check_comapny_email_is_currect( $check_email , get_contact_user_id() ) )
                    {


                        handle_contact_profile_image_upload();


                        $data = $page_obj->input->post();



                        $contact = $page_obj->clients_model->get_contact(get_contact_user_id());



                        if (has_contact_permission('invoices')) {

                            $data['invoice_emails']     = isset($data['invoice_emails']) ? 1 : 0;

                            $data['credit_note_emails'] = isset($data['credit_note_emails']) ? 1 : 0;

                        } else {

                            $data['invoice_emails']     = $contact->invoice_emails;

                            $data['credit_note_emails'] = $contact->credit_note_emails;

                        }



                        if (has_contact_permission('estimates')) {

                            $data['estimate_emails'] = isset($data['estimate_emails']) ? 1 : 0;

                        } else {

                            $data['estimate_emails'] = $contact->estimate_emails;

                        }



                        if (has_contact_permission('support')) {

                            $data['ticket_emails'] = isset($data['ticket_emails']) ? 1 : 0;

                        } else {

                            $data['ticket_emails'] = $contact->ticket_emails;

                        }



                        if (has_contact_permission('contracts')) {

                            $data['contract_emails'] = isset($data['contract_emails']) ? 1 : 0;

                        } else {

                            $data['contract_emails'] = $contact->contract_emails;

                        }



                        if (has_contact_permission('projects'))
                        {

                            $data['project_emails'] = isset($data['project_emails']) ? 1 : 0;

                            $data['task_emails']    = isset($data['task_emails']) ? 1 : 0;

                        }
                        else
                        {

                            $data['project_emails'] = $contact->project_emails;

                            $data['task_emails']    = $contact->task_emails;

                        }



                        $success = $page_obj->clients_model->update_contact([

                            'firstname'          => $page_obj->input->post('firstname'),

                            'lastname'           => $page_obj->input->post('lastname'),

                            'title'              => $page_obj->input->post('title'),

                            'email'              => $page_obj->input->post('email'),

                            'phonenumber'        => $page_obj->input->post('phonenumber'),

                            'direction'          => $page_obj->input->post('direction'),

                            'invoice_emails'     => $data['invoice_emails'],

                            'credit_note_emails' => $data['credit_note_emails'],

                            'estimate_emails'    => $data['estimate_emails'],

                            'ticket_emails'      => $data['ticket_emails'],

                            'contract_emails'    => $data['contract_emails'],

                            'project_emails'     => $data['project_emails'],

                            'task_emails'        => $data['task_emails'],

                            'custom_fields'      => isset($data['custom_fields']) && is_array($data['custom_fields']) ? $data['custom_fields'] : [],

                        ], get_contact_user_id(), true);



                        if ($success == true) {

                            set_alert('success', _l('clients_profile_updated'));

                        }



                        redirect(site_url('clients/profile'));

                    }

                }


            }


        }

    }


});


/**
 * contact post action
 */
hooks()->add_action('after_clients_area_init',function ( $page_obj ){



    if ( !empty( $_SESSION["all_clients"] ) && count( $_SESSION["all_clients"] ) > 1 )
    {

        $called_function = $page_obj->router->fetch_method();


        if ( $called_function == 'contact' && $page_obj->input->post() )
        {

            $contact_id = str_replace( 'contacts/contact' , '' ,  $page_obj->uri->uri_string ) ;
            $contact_id = str_replace( '/' , '' ,  $contact_id ) ;


            $page_obj->form_validation->set_rules('firstname', _l('client_firstname'), 'required');

            $page_obj->form_validation->set_rules('lastname', _l('client_lastname'), 'required');



            if ( !empty( $contact_id ) && is_numeric( $contact_id ) )
            {

                $page_obj->form_validation->set_message('contact_email_profile_unique', _l('contact_form_validation_is_unique'));

            }
            else
            {

                $page_obj->form_validation->set_rules('password', _l('client_password'), 'required');

                $page_obj->form_validation->set_message('is_unique', _l('contact_form_validation_is_unique'));

            }



            $custom_fields = get_custom_fields('contacts', [

                'show_on_client_portal'  => 1,

                'required'               => 1,

                'disalow_client_to_edit' => 0,

            ]);



            foreach ($custom_fields as $field) {

                $field_name = 'custom_fields[' . $field['fieldto'] . '][' . $field['id'] . ']';



                if ($field['type'] == 'checkbox' || $field['type'] == 'multiselect') {

                    $field_name .= '[]';

                }



                $page_obj->form_validation->set_rules($field_name, $field['name'], 'required');

            }



            if ( $page_obj->form_validation->run() !== false )
            {


                $check_email = $page_obj->input->post('email');


                if ( check_comapny_email_is_currect( $check_email , $contact_id ) )
                {


                    $data        = $page_obj->input->post();

                    $phonenumber = $page_obj->input->post('phonenumber');



                    if ( !empty( $callingCode ) && $callingCode && !empty($phonenumber) && $phonenumber == $callingCode )
                    {

                        $phonenumber = '';

                    }



                    $contact_data = [

                        'is_primary'         => 0,

                        'firstname'          => $data['firstname'],

                        'lastname'           => $data['lastname'],

                        'title'              => $data['title'],

                        'email'              => $data['email'],

                        'phonenumber'        => $phonenumber,

                        'direction'          => $data['direction'],

                        'invoice_emails'     => isset($data['invoice_emails']) ? 1 : 0,

                        'credit_note_emails' => isset($data['credit_note_emails']) ? 1 : 0,

                        'estimate_emails'    => isset($data['estimate_emails']) ? 1 : 0,

                        'ticket_emails'      => isset($data['ticket_emails']) ? 1 : 0,

                        'contract_emails'    => isset($data['contract_emails']) ? 1 : 0,

                        'project_emails'     => isset($data['project_emails']) ? 1 : 0,

                        'task_emails'        => isset($data['task_emails']) ? 1 : 0,

                        'custom_fields'      => isset($data['custom_fields']) && is_array($data['custom_fields']) ? $data['custom_fields'] : [],

                    ];



                    if (isset($data['password'])) {

                        $contact_data['password'] = $page_obj->input->post('password', false);

                    }



                    if (isset($data['send_set_password_email']) && $data['send_set_password_email'] == 'on') {

                        $contact_data['send_set_password_email'] = true;

                    }



                    if (isset($data['donotsendwelcomeemail']) && $data['donotsendwelcomeemail'] == 'on') {

                        $contact_data['donotsendwelcomeemail'] = true;

                    }



                    if ( !empty( $contact_id ) && is_numeric( $contact_id ) )
                    {

                        handle_contact_profile_image_upload( $contact_id );

                        $success = $page_obj->clients_model->update_contact($contact_data, $contact_id , true);



                        if ( $success == true )
                        {

                            set_alert('success', _l('clients_contact_updated'));

                        }

                    }
                    else
                    {

                        $contactId = $page_obj->clients_model->add_contact_via_customers_area($contact_data, get_client_user_id());



                        if ( $contactId !== false )
                        {

                            handle_contact_profile_image_upload($contactId);

                            set_alert('success', _l('clients_contact_added'));

                        }

                    }


                    redirect(site_url('contacts'));

                }

            }


        }

    }


});





function check_comapny_email_is_currect( $email = '' , $contact_id = 0 )
{

    if ( empty( $email ) )
        return true;

    if( !empty( $contact_id )  )
    {

        $info = get_instance()->db->select('email')->from(db_prefix().'contacts')->where('id',$contact_id)->get()->row();

        if ( !empty( $info->email ) )
        {

            if ( $info->email == $email )
                return  true;
            
        }

    }


    //return total_rows(db_prefix() . 'contacts', 'id !=' . get_contact_user_id() . ' AND userid != '.get_client_user_id().' AND email="' . get_instance()->db->escape_str($email) . '"') > 0 ? false : true;
    return total_rows(db_prefix() . 'contacts', 'userid != '.get_client_user_id().' AND email="' . get_instance()->db->escape_str($email) . '"') > 0 ? false : true;

}


/**
 * @Version 1.0.4
 */

hooks()->add_action("app_admin_footer", function (){

    echo "
    
    <div id='multiple_companies_div_content'></div>
    
    <script>
    
        var mc_existing_user_lang = '"._l('mc_existing_user')."'; 

    </script>
    
    <script src='" . base_url("modules/multiple_companies/assets/multiple_companies_js.js?v=3") ."'></script> ";



});



hooks()->add_filter('get_relation_data',function ( $data , $filter ){


    if ( !empty( $filter['type'] ) )
    {

        if ( $filter['type'] == 'multiple_contact' )
        {

            $CI = &get_instance();


            $q  = '';

            if ( !empty( $CI->input->post('q') ) )
            {

                $q = $CI->input->post('q'); $q = trim($q);
            }

            $company_id = $CI->input->post('mt_customer_id');


            $where_contacts = db_prefix() . 'contacts.active=1';

            $company_emails = '';

            $company_contacts =  $CI->db->select('email')->from(db_prefix().'contacts')->where('userid',$company_id)->get()->result();

            if ( !empty( $company_contacts ) )
            {

                foreach ( $company_contacts as $company_contact)
                {

                    if( !empty( $company_contact->email ) )
                    {

                        if ( $company_emails != '' )
                            $company_emails .= " , ";

                        $company_emails .= "'".$company_contact->email."'";

                    }

                }


                if ( $company_emails != '' )
                {

                    $where_contacts .= " AND ".db_prefix() . "contacts.email NOT IN ( ".$company_emails." ) ";

                }

            }


            //$search = $CI->misc_model->_search_contacts($q, 0, $where_contacts);


            $CI->db->select(implode(',', prefixed_table_fields_array(db_prefix() . 'contacts')) . ',company');

            $CI->db->from(db_prefix() . 'contacts');



            $CI->db->join(db_prefix() . 'clients', '' . db_prefix() . 'clients.userid=' . db_prefix() . 'contacts.userid', 'left');

            $CI->db->where('(firstname LIKE "%' . $CI->db->escape_like_str($q) . '%" ESCAPE \'!\'

                OR lastname LIKE "%' . $CI->db->escape_like_str($q) . '%" ESCAPE \'!\'

                OR email LIKE "%' . $CI->db->escape_like_str($q) . '%" ESCAPE \'!\'

                OR CONCAT(firstname, \' \', lastname) LIKE "%' . $CI->db->escape_like_str($q) . '%" ESCAPE \'!\'

                OR CONCAT(lastname, \' \', firstname) LIKE "%' . $CI->db->escape_like_str($q) . '%" ESCAPE \'!\'

                OR ' . db_prefix() . 'contacts.phonenumber LIKE "%' . $CI->db->escape_like_str($q) . '%" ESCAPE \'!\'

                OR ' . db_prefix() . 'contacts.title LIKE "%' . $CI->db->escape_like_str($q) . '%" ESCAPE \'!\'

                OR company LIKE "%' . $CI->db->escape_like_str($q) . '%" ESCAPE \'!\'

                )');




            $CI->db->where($where_contacts);


            $CI->db->group_by('email');
            $CI->db->order_by('firstname', 'ASC');

            $data = $CI->db->get()->result_array();



        }


    }

    return $data;

},11 ,2);


hooks()->add_filter('init_relation_options',function ( $_data , $filter ){


    if ( !empty( $filter['data'] ) )
    {

        $data = $filter['data'];

        if ( $filter['type'] == 'multiple_contact' )
        {

            foreach ( $data as $relation )
            {

                //$relation_values = get_relation_values( $relation , "contacts" );

                $userid = $relation['userid'] ;

                $id     = $relation['id'];

                $name   = $relation['firstname'] . ' ' . $relation['lastname'];

                //$subtext = get_company_name($userid);
                $subtext = $relation['email'];

                $link    = admin_url('clients/client/' . $userid . '?contactid=' . $id);

                $relation_values = [

                    'id'        => $id,

                    'name'      => $name,

                    'link'      => $link,

                    'addedfrom' => $addedfrom,

                    'subtext'   => $subtext,

                    'type'      => $type,

                ];

                $_data[] = $relation_values;

            }

        }

    }


    return $_data;

} , 11 , 2 );


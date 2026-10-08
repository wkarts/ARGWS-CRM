<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property Multiple_companies_model $multiple_companies_model
 */

class Companies extends AdminController {


    public function __construct()
    {

        parent::__construct();

        $this->load->model('multiple_companies_model');

    }


    public function get_contact_companies( $current_customer_id = 0 , $contact_id = 0 )
    {

        $email_address = $this->input->get('email_adress');

        $data['customer_id']    = $current_customer_id;
        $data['contact_id']     = $contact_id;
        $data['companies']      = [];
        $data['email_address']  = $email_address;

        if ( !empty( $email_address ) )
            $data['companies'] = $this->multiple_companies_model->get_contact_companies( $email_address , $current_customer_id );

        /**
         * Checking email exist
         */

        $data['email_exist'] = 0;

        if ( !empty( $email_address ) )
            $data['email_exist'] = total_rows(db_prefix() . 'contacts', 'id !=' . $contact_id. ' AND userid = '.$current_customer_id.' AND email="' . get_instance()->db->escape_str($email_address) . '"') ;


        $this->load->view('v_contact_companies',$data);

    }


    public function check_contact_email()
    {

        if ($this->input->is_ajax_request())
        {


            $email_address  = $this->input->post('email');
            $contact_id     = $this->input->post('contact_id');
            $customer_id    = $this->input->post('customer_id');

            if ( empty( $email_address ) )
            {

                $success = false;

            }
            else
            {

                if ( empty( $contact_id ) )
                    $contact_id = 0;

                if ( total_rows(db_prefix() . 'contacts', 'id !=' . $contact_id. ' AND userid = '.$customer_id.' AND email="' . get_instance()->db->escape_str($email_address) . '"' ) > 0 )
                    $success = false;
                else
                    $success = true;

            }



            echo json_encode($success);

            die();


        }

    }


    /**
     * Exist contact data
     */
    public function exist_contact( $customer_id = 0 )
    {

        $data['customer_id'] = $customer_id;

        $this->load->view('v_exist_contact' , $data );

    }

    public function save_contact()
    {


        if( $this->input->is_ajax_request() )
        {

            $success = false;
            $message = _l('mc_exist_contact_failed');

            $customer_id = $this->input->post('customer_id');
            $contacts = $this->input->post('contacts');

            if ( !empty( $contacts ) )
            {


                foreach ( $contacts as $contact )
                {

                    if ( $this->multiple_companies_model->contact_copy( $customer_id , $contact ) )
                    {

                        $success = true;
                        $message = _l('mc_exist_contact_success');

                    }

                }

            }

            echo json_encode( ['success' => $success , 'message' => $message ] );

        }

    }

}

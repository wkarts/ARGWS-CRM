<?php

defined('BASEPATH') or exit('No direct script access allowed');


class Multiple_companies_model extends CI_Model
{


    public function __construct()
    {

        parent::__construct();


    }


    public function get_contact_companies( $email = '' , $customer_id = 0 )
    {

        if ( !empty( $customer_id ) )
            $this->db->where('contact.userid !=',$customer_id);

        return $this->db->select(" client.company , contact.userid , contact.id , contact.firstname , contact.lastname , contact.is_primary ")
                        ->from(db_prefix() . 'contacts contact')
                        ->join(db_prefix()."clients client"," contact.userid = client.userid ")
                        ->where('contact.email',$email)
                        ->group_by('contact.userid')
                        ->order_by('client.company')
                        ->get()
                        ->result();

    }


    public function contact_copy( $customer_id , $contact_id  )
    {

        $contact = $this->db->select('*')->from(db_prefix().'contacts')->where('id',$contact_id)->get()->row();

        if ( !empty( $contact ) )
        {

            unset($contact->id);

            $contact->userid = $customer_id;
            $contact->datecreated = date('Y-m-d H:i:s');
            $contact->is_primary = 0;

            $this->db->insert( db_prefix().'contacts' , $contact );


            $new_contact_id = $this->db->insert_id();

            if ( $new_contact_id )
            {

                log_activity('Contact Added FROM Multiple Companies Module [ID: ' . $new_contact_id . ']');


                hooks()->do_action('contact_created', $new_contact_id);

                return true;

            }

        }

        return false;

    }



}


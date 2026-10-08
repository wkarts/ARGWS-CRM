<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Clients extends ClientsController {


    public function __construct()
    {

        parent::__construct();

    }


    /**
     * Setting up new session information is in progress.
     */
    public function change_auth( $clientID = 0 )
    {

        foreach ( $_SESSION["all_clients"] as $client )
        {

            if( $client->userid == $clientID )
            {

                /**
                 * active customer information is assigned
                 */

                $_SESSION["client_user_id"] = $client->userid;

                $_SESSION["contact_user_id"] = $client->id;

            }

        }

        redirect(site_url());

    }

}

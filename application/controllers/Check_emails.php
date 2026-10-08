<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Legacy email-open tracking endpoint; retained only as a non-tracking 404. */
class Check_emails extends CI_Controller
{
    public function index()
    {
        show_404();
    }

    public function track()
    {
        show_404();
    }
}

<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Legacy compatibility route. Application files are distributed through the
 * ARGWS release channel and are never downloaded using a purchase key.
 */
class Auto_update extends AdminController
{
    public function index()
    {
        show_404();
    }

    public function database()
    {
        show_404();
    }
}

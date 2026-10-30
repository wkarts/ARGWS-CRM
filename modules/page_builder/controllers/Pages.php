<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pages extends ClientsController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function show_404()
    {
        show_404();
    }
}
<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Custom_links_proxy extends AdminController
{
    public function index($id = 0)
    {
        // Keep the legacy route inert so stale links cannot make the CRM fetch
        // or serve HTML from an external URL.
        show_404();
    }
}

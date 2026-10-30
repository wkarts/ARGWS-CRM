<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Its essensial that we set 404 override. This ensure the page builder module is reached.
if (empty($route['404_override']))
    $route['404_override']         = 'page_builder/pages/show_404';
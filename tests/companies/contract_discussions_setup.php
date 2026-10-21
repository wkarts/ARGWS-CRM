<?php
require __DIR__.'/portal_operations_setup.php';require_once APPPATH.'services/CompanyContractDiscussions.php';$discussion=new CompanyContractDiscussions($db,$p,$portal);$discussionIds=$portalParents['contracts'];

<?php
defined('BASEPATH') or exit('No direct script access allowed');

// The framework owns removal of this module's files and registry entry.
// Retain payment/charge/adoption history, encrypted certificates and settings.
// Never unlink legacy application/controllers or application/libraries copies:
// they may be independently managed deployment artifacts. The scoped preflight
// rejects the retired copied controller instead of executing or deleting it.

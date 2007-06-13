<?php

$_GET['title'] = 'Enano:Access_denied';
require('../includes/common.php');
header('HTTP/1.1 403 Forbidden');
$session->perms['edit_page'] = AUTH_DENY;
$session->perms['view_source'] = AUTH_DENY;
$template->tpl_strings['PAGE_NAME'] = 'Access denied';

$template->header();
echo '<p>The administrator has flagged the page "' . $_SERVER['REQUEST_URI'] . '" so that it cannot be accessed from the web. Perhaps this is because this is a cache or includes directory and only needs to be accessed by scripts.</p><p>HTTP error: 403 Forbidden</p>';
$template->footer();
$db->close();

<?php

$title = 'Access denied';
require('../includes/common.php');
header('HTTP/1.1 403 Forbidden');

$template->header();
echo '<p>The administrator has flagged the page "' . htmlspecialchars($_SERVER['REQUEST_URI']) . '" so that it cannot be accessed from the web. Perhaps this is because this is a cache or includes directory and only needs to be accessed by scripts.</p><p>HTTP error: 403 Forbidden</p>';
$template->footer();


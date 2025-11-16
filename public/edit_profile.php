<?php
// Compatibility redirect: some links expect edit_profile.php but our project uses profile.php
// Use an absolute URL path so the redirect works correctly regardless of referer or current path.
header('Location: /nacos/public/profile.php', true, 302);
exit;

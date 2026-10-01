<?php
session_start();
// Destroy all session data including guest
session_unset();
session_destroy();
// Redirect to welcome page
header('Location: welcome.php');
exit;
?>
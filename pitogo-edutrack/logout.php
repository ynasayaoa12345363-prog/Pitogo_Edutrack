<?php
session_start();

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - Logout
|--------------------------------------------------------------------------
*/

session_unset();
session_destroy();

header("Location: index.html");
exit();
?>
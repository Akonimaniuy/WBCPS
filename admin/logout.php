<?php
	session_start();

	// Destroy all session variables
	$_SESSION = [];

	// If you want to kill the session completely
	session_destroy();

	// Redirect to login page
	header("Location: ../index.php");
	exit();
?>

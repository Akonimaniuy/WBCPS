<?php
	session_start();
	include_once("../lib/config.php");

	$conn = new mysqli($host, $user, $pass, $db);
	if ($conn->connect_error) die("DB error");

	if (isset($_POST['id'])) {
	    $id = $_POST['id'];

	    $sql = "DELETE FROM users WHERE id=?";
	    $stmt = $conn->prepare($sql);
	    $stmt->bind_param("i", $id);

	    if ($stmt->execute()) {
	        echo "<script>
	            alert('User deleted successfully!');
	            window.location.href='../user_driven.php';
	        </script>";
	    } else {
	        echo "<script>
	            alert('Failed to delete user!');
	            window.location.href='../user_driven.php';
	        </script>";
	    }
	}
?>

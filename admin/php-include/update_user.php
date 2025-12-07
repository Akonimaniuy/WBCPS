<?php
    session_start();
    include_once("../lib/config.php");

    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) die("DB error");

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $email = $_POST['email'];

        $sql = "UPDATE users SET name=?, email=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $name, $email, $id);

        if ($stmt->execute()) {
            echo "<script>
                alert('User updated successfully!');
                window.location.href='../user_driven.php';
            </script>";
        } else {
            echo "<script>
                alert('Failed to update user!');
                window.location.href='../user_driven.php';
            </script>";
        }
    }
?>
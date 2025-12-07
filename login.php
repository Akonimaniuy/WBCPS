<?php
    session_start();
    include_once('admin/lib/config.php');

    // Default redirect URL if something goes wrong
    $return_url = isset($_POST['return_url']) && !empty($_POST['return_url']) ? $_POST['return_url'] : 'index.php';

    $current_action = isset($_POST['action']) ? $_POST['action'] : 'login';

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if ($current_action === 'login') {
            $email = $_POST['login_email'];
            $password = $_POST['login_password'];
            if (empty($email) || empty($password)) {
                $_SESSION['auth_message'] = "Login failed: Email and password are required.";
                $_SESSION['auth_message_type'] = 'error';
            } else {
                // --- Step 1: Attempt to log in as an Admin ---
                $admin_stmt = $conn->prepare("SELECT id, name, password FROM admin WHERE email = ?");
                $admin_stmt->bind_param("s", $email);
                $admin_stmt->execute();
                $admin_result = $admin_stmt->get_result();

                if ($admin_result->num_rows === 1) {
                    $admin_row = $admin_result->fetch_assoc();
                    // SECURITY WARNING: This is an insecure plaintext password check.
                    if ($password === $admin_row['password']) {
                        // Admin login successful
                        $_SESSION['user_id'] = $admin_row['id']; // Using user_id for consistency
                        $_SESSION['name'] = $admin_row['name'];
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_role'] = 'admin';
                        header("Location: admin/index.php"); // Redirect admin to their dashboard
                        exit;
                    }
                }
                $admin_stmt->close();

                // --- Step 2: If not an admin, attempt to log in as a User ---
                $user_stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ?");
                $user_stmt->bind_param("s", $email);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();

                if ($user_result->num_rows === 1) {
                    $user_row = $user_result->fetch_assoc();
                    if (password_verify($password, $user_row['password'])) {
                        // User login successful
                        $_SESSION['user_id'] = $user_row['id'];
                        $_SESSION['name'] = $user_row['name'];
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_role'] = 'user';
                        header("Location: dashboard.php"); // Redirect user to their dashboard
                        exit;
                    }
                }
                $user_stmt->close();

                // --- Step 3: If both attempts fail ---
                $_SESSION['auth_message'] = "Login failed: Invalid credentials.";
                $_SESSION['auth_message_type'] = 'error';
            }
        } elseif ($current_action === 'register') {
            $name = $_POST['register_name'];
            $email = $_POST['register_email'];
            $password = $_POST['register_password'];
            $confirm_password = $_POST['register_confirm_password'];
            // ... (registration logic remains the same)
        }
        $_SESSION['auth_action'] = $current_action;
        header("Location: " . $return_url);
        exit;
    }

    // If someone navigates to login.php directly, just send them to the home page.
    header("Location: index.php");
    exit();
?>

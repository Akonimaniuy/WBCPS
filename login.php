<?php
    session_start();
    include_once('admin/lib/config.php');

    // Default redirect URL if something goes wrong
    $return_url = isset($_POST['return_url']) && !empty($_POST['return_url']) ? $_POST['return_url'] : '/WBCPS/'; // Default to home

    $current_action = isset($_POST['action']) ? $_POST['action'] : 'login';

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if ($current_action === 'login') {
            $email = $_POST['login_email'];
            $password = $_POST['login_password'];
            if (empty($email) || empty($password)) {
                $_SESSION['auth_message'] = "Login failed: Email and password are required.";
                $_SESSION['auth_message_type'] = 'error';
            } else {
                // --- Unified Login Check ---
                $user_stmt = $conn->prepare("SELECT id, name, password, status, role FROM users WHERE email = ?");
                $user_stmt->bind_param("s", $email);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();

                if ($user_result->num_rows === 1) {
                    $user_row = $user_result->fetch_assoc();

                    if (password_verify($password, $user_row['password'])) {
                        if ($user_row['status'] !== 'active') {
                            $_SESSION['auth_message'] = "Login failed: Your account is deactivated. Please contact an administrator.";
                            $_SESSION['auth_message_type'] = 'error';
                        } else {
                            // Password is correct and account is active, set session variables
                            $_SESSION['user_id'] = $user_row['id'];
                            $_SESSION['name'] = $user_row['name'];
                            $_SESSION['user_email'] = $email;
                            $_SESSION['user_role'] = $user_row['role'];

                            // Redirect based on role
                            if ($user_row['role'] === 'admin') {
                                header("Location: admin/index.php");
                            } else {
                                header("Location: /WBCPS/dashboard");
                            }
                            exit;
                        }
                    } else {
                        // Generic error for incorrect password or if user doesn't exist
                        $_SESSION['auth_message'] = "Login failed: Invalid credentials.";
                        $_SESSION['auth_message_type'] = 'error';
                    }
                } else {
                    $_SESSION['auth_message'] = "Login failed: Invalid credentials.";
                    $_SESSION['auth_message_type'] = 'error';
                }
            }
        } elseif ($current_action === 'register') {
            $name = $_POST['register_name'];
            $email = $_POST['register_email'];
            $password = $_POST['register_password'];
            $confirm_password = $_POST['register_confirm_password'];
            
            if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
                $_SESSION['auth_message'] = "Registration failed: All fields are required.";
                $_SESSION['auth_message_type'] = 'error';
            } elseif ($password !== $confirm_password) {
                $_SESSION['auth_message'] = "Registration failed: Passwords do not match.";
                $_SESSION['auth_message_type'] = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['auth_message'] = "Registration failed: Invalid email format.";
                $_SESSION['auth_message_type'] = 'error';
            } else {
                // Check if email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $_SESSION['auth_message'] = "Registration failed: This email is already registered.";
                    $_SESSION['auth_message_type'] = 'error';
                } else {
                    // Insert new user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $insert_stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                    $insert_stmt->bind_param("sss", $name, $email, $hashed_password);
                    $insert_stmt->execute();
                    $_SESSION['auth_message'] = "Registration successful! You can now log in.";
                    $_SESSION['auth_message_type'] = 'success';
                    $current_action = 'login'; // Switch to login tab on success
                }
            }
        }
        $_SESSION['auth_action'] = $current_action;
        header("Location: " . $return_url);
        exit;
    }

    // If someone navigates to login.php directly, just send them to the home page.
    header("Location: /WBCPS/");
    exit();
?>

<?php
session_start();
require 'admin/lib/config.php'; // Use your existing config file

$is_ajax = isset($_POST['ajax']) && $_POST['ajax'] === 'true';
if ($is_ajax) {
    header('Content-Type: application/json');
}

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // ACTION: Verify OTP
    if ($action === 'verify_otp' || (!$is_ajax && isset($_POST['otp']))) {
        $otp = $_POST['otp'];

        $stmt = $conn->prepare("SELECT otp_code, otp_expiry FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && $user['otp_code'] == $otp && strtotime($user['otp_expiry']) > time()) {
            // OTP is correct and not expired, show password reset form
            $_SESSION['otp_verified'] = true;
            if ($is_ajax) { echo json_encode(['success' => true]); exit(); }
        } else {
            $error = "Invalid or expired OTP. Please try again.";
            if ($is_ajax) { echo json_encode(['success' => false, 'message' => $error]); exit(); }
        }
        $stmt->close();
    // ACTION: Reset Password
    } elseif (($action === 'reset_password' || (!$is_ajax && isset($_POST['password']))) && isset($_SESSION['otp_verified']) && $_SESSION['otp_verified'] === true) {
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];

        if ($password === $password_confirm) {
            if (strlen($password) < 6) { // Basic validation
                $error = "Password must be at least 6 characters long.";
                if ($is_ajax) { echo json_encode(['success' => false, 'message' => $error]); exit(); }
            } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Update password and clear OTP
            $stmt = $conn->prepare("UPDATE users SET password = ?, otp_code = NULL, otp_expiry = NULL WHERE email = ?");
            $stmt->bind_param("ss", $hashed_password, $email);
            $stmt->execute();

            $success = "Your password has been reset successfully! You can now log in.";
            // Clean up session
            session_destroy();
            if ($is_ajax) { echo json_encode(['success' => true, 'message' => $success]); exit(); }
            }
        } else {
            $error = "Passwords do not match.";
            if ($is_ajax) { echo json_encode(['success' => false, 'message' => $error]); exit(); }
        }
    } else {
        // Fallback for invalid state
        $error = "An unexpected error occurred. Please start over.";
        if ($is_ajax) { echo json_encode(['success' => false, 'message' => $error]); exit(); }
    }
}

?>
<?php if (!$is_ajax): // Only render HTML for non-AJAX requests ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <!-- You can link your own stylesheet here -->
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .container { border: 1px solid #ccc; padding: 20px; border-radius: 5px; width: 300px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 10px 15px; background-color: #007bff; color: white; border: none; cursor: pointer; }
        .error { color: red; } .success { color: green; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Your Password</h2>
        <?php if (!empty($error)): ?><p class="error"><?php echo $error; ?></p><?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="success"><?php echo $success; ?></p>
            <a href="login.php">Go to Login</a> <!-- Link to your login page -->
        <?php elseif (isset($_SESSION['otp_verified']) && $_SESSION['otp_verified'] === true): ?>
            <form action="update_password.php" method="post">
                <div class="form-group"><label for="password">New Password</label><input type="password" name="password" required></div>
                <div class="form-group"><label for="password_confirm">Confirm New Password</label><input type="password" name="password_confirm" required></div>
                <button type="submit">Reset Password</button>
            </form>
        <?php else: ?>
            <p class="error">Please verify your OTP first.</p>
            <a href="forgot_password.php">Start Over</a>
        <?php endif; ?>
    </div>
</body>
</html>
<?php endif; $conn->close(); ?>

<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require 'admin/lib/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] === 'true';
    if ($is_ajax) {
        header('Content-Type: application/json');
    }

    $email = $_POST['email'];

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Generate OTP
        $otp = rand(100000, 999999);
        // Set OTP expiry time (e.g., 15 minutes from now)
        $otp_expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

        // Store OTP in database
        $stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE email = ?");
        $stmt->bind_param("sss", $otp, $otp_expiry, $email);
        $stmt->execute();

        // Send email with OTP
        $mail = new PHPMailer(true);

        try {
            //Server settings
            $mail->isSMTP();
            // IMPORTANT: Replace with your own SMTP settings
            $mail->Host       = 'smtp.gmail.com'; // Example for Gmail
            $mail->SMTPAuth   = true;
            $mail->Username   = 'webcareerpathway@gmail.com'; // Your Gmail address
            $mail->Password   = 'zlvrsyxldcsnywib'; // Your Gmail App Password (spaces removed)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587; // Port for TLS

            //Recipients
            $mail->setFrom('no-reply@wbcps.com', 'WBCPS');
            $mail->addAddress($email);

            //Content
            $mail->isHTML(true);
            $mail->Subject = 'Your Password Reset Code';
            $mail->Body    = "Your OTP for password reset is: <b>$otp</b>. It is valid for 15 minutes.";
            $mail->AltBody = "Your OTP for password reset is: $otp. It is valid for 15 minutes.";

            $mail->send();

            // Store email in session and redirect to OTP verification page
            $_SESSION['reset_email'] = $email;
            if ($is_ajax) {
                echo json_encode(['success' => true]);
            } else {
                $stmt->close();
                $conn->close();
                header("Location: verify_otp.php");
            }
            exit();

        } catch (Exception $e) {
            $error_message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => $error_message]);
            } else {
                echo $error_message;
            }
            $stmt->close();
            $conn->close();
            exit();
        }
    } else {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'Email address not found in our system.']);
        } else {
            echo "Email address not found.";
        }
        $stmt->close();
        $conn->close();
        exit();
    }
} else {
    header("Location: forgot_password.php");
    exit();
}
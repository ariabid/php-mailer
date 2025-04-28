<?php
require 'vendor/autoload.php';

// Set up error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $error_message = 'Method not allowed';
    error_log($error_message);
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => $error_message]);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

// Initialize errors array
$errors = [];

// Validate required fields
$required_fields = ['name', 'email', 'phone_number', 'message'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        $errors[$field] = "The $field field is required";
    }
}

// Validate email format if email is provided
if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'The email must be a valid email address';
}

// If there are any validation errors, return them
if (!empty($errors)) {
    $error_message = 'Validation errors: ' . json_encode($errors);
    error_log($error_message);
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'The given data was invalid.',
        'errors' => $errors
    ]);
    exit;
}

// Sanitize inputs
$name = htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8');
$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
$phone_number = htmlspecialchars($data['phone_number'], ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars($data['message'], ENT_QUOTES, 'UTF-8');

// Get mailer configuration
$mailer_type = strtoupper($_ENV['MAILER'] ?? 'SMTP');

// SMTP Configuration if needed
if ($mailer_type === 'SMTP') {
    $smtp_host = $_ENV['SMTP_HOST'];
    $smtp_username = $_ENV['SMTP_USERNAME'];
    $smtp_password = $_ENV['SMTP_PASSWORD'];
    $smtp_port = $_ENV['SMTP_PORT'];
    $smtp_secure = $_ENV['SMTP_ENCRYPTION'];
}

// Email configuration from .env
$from_email = $_ENV['MAIL_FROM'];
$from_name = $_ENV['MAIL_FROM_NAME'];
$to_email = $_ENV['MAIL_TO'];

// Create email content
$email_subject = "New Contact Form Submission from $name";
$email_body = "You have received a new message from your website contact form.\n\n".
    "Name: $name\n".
    "Email: $email\n".
    "Phone Number: $phone_number\n".
    "Message:\n$message";

// Use Composer's autoloader
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    $success = false;
    
    if ($mailer_type === 'SMTP') {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        
        // Only enable SMTP authentication if credentials are provided
        if ($smtp_username !== 'null' && $smtp_password !== 'null') {
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_username;
            $mail->Password = $smtp_password;
        } else {
            $mail->SMTPAuth = false;
        }
        
        // Set encryption if specified
        if ($smtp_secure !== 'none') {
            $mail->SMTPSecure = $smtp_secure;
        }
        
        $mail->Port = $smtp_port;
        
        // Recipients
        $mail->setFrom($from_email, $from_name);
        // Handle multiple recipients
        $recipients = array_map('trim', explode(',', $to_email));
        foreach ($recipients as $recipient) {
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($recipient);
            }
        }
        $mail->addReplyTo($email, $name);
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = $email_subject;
        $mail->Body = $email_body;
        
        $success = $mail->send();
    } else {
        // Use PHP's mail() function
        $headers = [
            'From' => "$from_name <$from_email>",
            'Reply-To' => $email,
            'X-Mailer' => 'PHP/' . phpversion(),
            'Content-Type' => 'text/plain; charset=utf-8'
        ];
        
        // Convert headers array to string
        $headers_str = '';
        foreach ($headers as $key => $value) {
            $headers_str .= "$key: $value\r\n";
        }
        
        // Handle multiple recipients for mail() function
        $recipients = array_map('trim', explode(',', $to_email));
        $valid_recipients = array_filter($recipients, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });
        $to = implode(',', $valid_recipients);
        $success = mail($to, $email_subject, $email_body, $headers_str);
    }
    if ($success) {
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Message sent successfully']);
    } else {
        $error_message = 'Message could not be sent';
        error_log($error_message);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $error_message]);
    }
} catch (Exception $e) {
    $error_message = 'Mail error: ' . $e->getMessage();
    error_log($error_message);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Message could not be sent.']);
    exit;
}
?>
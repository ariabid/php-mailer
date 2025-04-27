<?php
require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
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
        $errors[$field][] = "The $field field is required";
    }
}

// Validate email format if email is provided
if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'][] = 'The email must be a valid email address';
}

// If there are any validation errors, return them
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'The given data was invalid.',
        'errors' => $errors
    ]);
    exit;
}

// Sanitize inputs
$name = filter_var($data['name'], FILTER_SANITIZE_STRING);
$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
$phone_number = filter_var($data['phone_number'], FILTER_SANITIZE_STRING);
$message = filter_var($data['message'], FILTER_SANITIZE_STRING);

// SMTP Configuration from .env
$smtp_host = $_ENV['SMTP_HOST'];
$smtp_username = $_ENV['SMTP_USERNAME'];
$smtp_password = $_ENV['SMTP_PASSWORD'];
$smtp_port = $_ENV['SMTP_PORT'];
$smtp_secure = $_ENV['SMTP_ENCRYPTION'];

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
    $mail->addAddress($to_email);
    $mail->addReplyTo($email, $name);
    
    // Content
    $mail->isHTML(false);
    $mail->Subject = $email_subject;
    $mail->Body = $email_body;
    
    $mail->send();
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Message sent successfully']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to send message: ' . $mail->ErrorInfo]);
}
?>
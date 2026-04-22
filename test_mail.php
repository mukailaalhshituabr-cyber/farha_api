<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/SMTP.php';
require_once __DIR__ . '/PHPMailer.php';

$result = [];

// 1. Check if PHPMailer loaded
$result['phpmailer_loaded'] = class_exists('PHPMailer\PHPMailer\PHPMailer');

// 2. Test SMTP port reachability
$sock = @fsockopen('smtp.gmail.com', 587, $errno, $errstr, 5);
if ($sock) {
    $result['port_587'] = 'open';
    fclose($sock);
} else {
    $result['port_587'] = "blocked ($errno: $errstr)";
}

// 3. Try port 465
$sock2 = @fsockopen('ssl://smtp.gmail.com', 465, $errno2, $errstr2, 5);
if ($sock2) {
    $result['port_465'] = 'open';
    fclose($sock2);
} else {
    $result['port_465'] = "blocked ($errno2: $errstr2)";
}

// 4. Try sending a real email (replace with your email)
if ($result['phpmailer_loaded'] && $result['port_587'] === 'open') {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'farha.atelier61@gmail.com';
        $mail->Password   = 'ntdjtmaubcqdeqym';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 8;
        $mail->setFrom('farha.atelier61@gmail.com', 'Farha Test');
        $mail->addAddress('farha.atelier61@gmail.com');
        $mail->Subject = 'Test';
        $mail->Body    = 'Test email from server';
        $mail->send();
        $result['send_test'] = 'sent';
    } catch (Exception $e) {
        $result['send_test'] = 'failed: ' . $e->getMessage();
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);

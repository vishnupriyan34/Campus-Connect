<?php
// PHPMailer Wrapper & Delivery System

require_once __DIR__ . '/db.php'; // ensure env is loaded

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendMail($to, $subject, $body, $altBody = '') {
    $mailLogPath = getenv('MAIL_LOG_PATH') ?: __DIR__ . '/../scratch/mail.log';
    
    // Ensure parent directory for log exists
    $logDir = dirname($mailLogPath);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    // Check if PHPMailer is installed
    $composerAutoload = __DIR__ . '/../vendor/autoload.php';
    $hasPHPMailer = false;
    
    if (file_exists($composerAutoload)) {
        require_once $composerAutoload;
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $hasPHPMailer = true;
        }
    }
    
    $smtp_host = getenv('SMTP_HOST');
    $smtp_port = getenv('SMTP_PORT');
    $smtp_user = getenv('SMTP_USER');
    $smtp_pass = getenv('SMTP_PASS');
    $smtp_from = getenv('SMTP_FROM') ?: 'no-reply@campusconnect.com';
    $smtp_from_name = getenv('SMTP_FROM_NAME') ?: 'Campus Connect';
    
    $mailLogged = false;
    $errorMessage = '';
    
    if ($hasPHPMailer && !empty($smtp_user) && !empty($smtp_pass) && $smtp_host !== 'smtp.mailtrap.io') {
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtp_port;
            
            // Recipients
            $mail->setFrom($smtp_from, $smtp_from_name);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $altBody ?: strip_tags($body);
            
            $mail->send();
            return ['status' => true, 'method' => 'SMTP'];
        } catch (Exception $e) {
            $errorMessage = $mail->ErrorInfo;
            // Fall back to logging on SMTP failure
        }
    }
    
    // Default fallback: Log email details to file
    $timestamp = date('Y-m-d H:i:s');
    $logContent = "==================================================\n";
    $logContent .= "TIMESTAMP: $timestamp\n";
    $logContent .= "TO: $to\n";
    $logContent .= "SUBJECT: $subject\n";
    if (!empty($errorMessage)) {
        $logContent .= "SMTP ERROR: $errorMessage\n";
    }
    $logContent .= "BODY:\n$body\n";
    $logContent .= "==================================================\n\n";
    
    file_put_contents($mailLogPath, $logContent, FILE_APPEND);
    return ['status' => true, 'method' => 'LogFile', 'path' => $mailLogPath];
}
?>

<?php

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');


// Rate limiting (simple file-based)
$rate_limit_file = 'rate_limit.json';
$max_requests_per_hour = 5;

function checkRateLimit($ip, $rate_limit_file, $max_requests) {
    $current_time = time();
    $rate_data = [];
    
    if (file_exists($rate_limit_file)) {
        $rate_data = json_decode(file_get_contents($rate_limit_file), true) ?: [];
    }
    
    // Clean old entries (older than 1 hour)
    $rate_data = array_filter($rate_data, function($timestamp) use ($current_time) {
        return ($current_time - $timestamp) < 3600;
    });
    
    // Check current IP
    $ip_requests = array_filter($rate_data, function($timestamp, $stored_ip) use ($ip) {
        return $stored_ip === $ip;
    }, ARRAY_FILTER_USE_BOTH);
    
    if (count($ip_requests) >= $max_requests) {
        return false;
    }
    
    // Add current request
    $rate_data[$ip . '_' . $current_time] = $current_time;
    file_put_contents($rate_limit_file, json_encode($rate_data));
    
    return true;
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sendEmail($to, $subject, $message, $headers) {
    // Using PHPMailer for better security and reliability
    // If PHPMailer is not available, falls back to mail()
    
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return sendEmailWithPHPMailer($to, $subject, $message);
    } else {
        return mail($to, $subject, $message, $headers);
    }
}

function sendEmailWithPHPMailer($to, $subject, $message) {
    global $config;
    
    require_once 'PHPMailer/src/Exception.php';
    require_once 'PHPMailer/src/PHPMailer.php';
    require_once 'PHPMailer/src/SMTP.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $config['smtp_port'];
        
        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to);
        $mail->addReplyTo($_POST['email'], $_POST['firstname'] . ' ' . $_POST['lastname']);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Main execution
try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed');
    }
    
    // Get client IP
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Rate limiting
    if (!checkRateLimit($client_ip, $rate_limit_file, $max_requests_per_hour)) {
        throw new Exception('Rate limit exceeded. Please try again later.');
    }
    
    // CSRF protection (if using sessions)
    session_start();
    if (isset($_POST['csrf_token'])) {
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token');
        }
    }
    
    // Validate required fields
    $required_fields = ['firstname', 'lastname', 'email', 'subject'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    // Sanitize and validate input
    $firstname = sanitizeInput($_POST['firstname']);
    $lastname = sanitizeInput($_POST['lastname']);
    $email = sanitizeInput($_POST['email']);
    $subject = sanitizeInput($_POST['subject']);
    
    // Additional validation
    if (!validateEmail($email)) {
        throw new Exception('Invalid email address');
    }
    
    if (strlen($firstname) < 2 || strlen($firstname) > 50) {
        throw new Exception('First name must be between 2 and 50 characters');
    }
    
    if (strlen($lastname) < 2 || strlen($lastname) > 50) {
        throw new Exception('Last name must be between 2 and 50 characters');
    }
    
    if (strlen($subject) < 10 || strlen($subject) > 1000) {
        throw new Exception('Message must be between 10 and 1000 characters');
    }
    
    // Check for spam patterns
    $spam_patterns = [
        '/\b(viagra|cialis|casino|lottery|winner)\b/i',
        '/\b(click here|free money|make money fast)\b/i',
        '/(http[s]?:\/\/[^\s]+.*){3,}/', // Multiple URLs
    ];
    
    $full_message = $firstname . ' ' . $lastname . ' ' . $email . ' ' . $subject;
    foreach ($spam_patterns as $pattern) {
        if (preg_match($pattern, $full_message)) {
            throw new Exception('Message appears to be spam');
        }
    }
    
    // Optional: reCAPTCHA verification
    if (!empty($config['recaptcha_secret']) && isset($_POST['g-recaptcha-response'])) {
        $recaptcha_response = $_POST['g-recaptcha-response'];
        $verify_url = "https://www.google.com/recaptcha/api/siteverify";
        $verify_data = [
            'secret' => $config['recaptcha_secret'],
            'response' => $recaptcha_response,
            'remoteip' => $client_ip
        ];
        
        $context = stream_context_create([
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($verify_data)
            ]
        ]);
        
        $verify_result = json_decode(file_get_contents($verify_url, false, $context), true);
        
        if (!$verify_result['success']) {
            throw new Exception('reCAPTCHA verification failed');
        }
    }
    
    // Prepare email content
    $email_subject = $config['subject_prefix'] . $subject;
    
    $email_body = "
    <html>
    <head>
        <title>New Contact Form Submission</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #f4f4f4; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
            .content { background-color: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
            .field { margin-bottom: 15px; }
            .label { font-weight: bold; color: #555; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New Contact Form Submission</h2>
                <p>You have received a new message from your portfolio contact form.</p>
            </div>
            <div class='content'>
                <div class='field'>
                    <div class='label'>Name:</div>
                    <div>$firstname $lastname</div>
                </div>
                <div class='field'>
                    <div class='label'>Email:</div>
                    <div>$email</div>
                </div>
                <div class='field'>
                    <div class='label'>Message:</div>
                    <div>" . nl2br($subject) . "</div>
                </div>
            </div>
            <div class='footer'>
                <p>Sent from: $client_ip at " . date('Y-m-d H:i:s') . "</p>
                <p>This email was sent from your portfolio contact form.</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Email headers for fallback mail() function
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . $config['from_name'] . " <" . $config['from_email'] . ">" . "\r\n";
    $headers .= "Reply-To: $email" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Send email
    if (sendEmail($config['to_email'], $email_subject, $email_body, $headers)) {
        // Log successful submission
        error_log("Contact form submission from: $email ($firstname $lastname)");
        echo 'OK';
    } else {
        throw new Exception('Failed to send email. Please try again later.');
    }
    
} catch (Exception $e) {
    // Log error
    error_log("Contact form error: " . $e->getMessage() . " - IP: " . ($client_ip ?? 'unknown'));
    
    // Return error message
    http_response_code(400);
    echo $e->getMessage();
}
?>
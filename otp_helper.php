<?php
require_once 'db.php';

// Function to Dispatch Email via EmailJS API with cURL SSL Bypass & Browser Origin Headers
function sendEmailJS_OTP($recipient_email, $otp_code) {
    try {
        $service_id = "service_qh1c06y";
        $template_id = "template_judy2gg";
        $public_key = "6OYyhfesYKGExGDPb";

        $payload = [
            'service_id' => $service_id,
            'template_id' => $template_id,
            'user_id' => $public_key,
            'template_params' => [
                'email' => $recipient_email,
                'to_email' => $recipient_email,
                'user_email' => $recipient_email,
                'recipient' => $recipient_email,
                'otp' => $otp_code,
                'otp_code' => $otp_code,
                'code' => $otp_code,
                'passcode' => $otp_code
            ]
        ];

        $json_payload = json_encode($payload);

        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Origin: http://localhost:3000',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 Seconds Timeout

            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
        } else {
            $opts = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n" .
                                 "Origin: http://localhost:3000\r\n" .
                                 "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                    'content' => $json_payload,
                    'timeout' => 5,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];
            $context = stream_context_create($opts);
            return @file_get_contents('https://api.emailjs.com/api/v1.0/email/send', false, $context);
        }
    } catch (Throwable $e) {
        return false;
    }
}

// Function to Generate & Store OTP
function generateAndSendOTP($email, $type = 'signup') {
    global $conn;
    
    // Clean old OTPs for this email & type
    $safe_email = $conn->real_escape_string($email);
    $safe_type = $conn->real_escape_string($type);
    $conn->query("DELETE FROM temp_otps WHERE email = '$safe_email' AND otp_type = '$safe_type'");
    
    // Generate 6-Digit OTP Code
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    
    // Expiry Time: 10 Minutes from now
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $stmt = $conn->prepare("INSERT INTO temp_otps (email, otp_code, otp_type, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $email, $otp, $type, $expires_at);
    
    if ($stmt->execute()) {
        // Trigger EmailJS Email
        @sendEmailJS_OTP($email, $otp);
        
        // Debug Backup & Session memory
        $_SESSION['debug_last_otp'] = $otp; 
        $_SESSION['last_otp_email'] = $email;
        $_SESSION['last_otp_type']  = $type;
        return true;
    }
    return false;
}

// Function to Verify OTP
function verifyOTP($email, $input_otp, $type = 'signup') {
    global $conn;
    
    $email = trim($email);
    $input_otp = trim($input_otp);
    $type = trim($type);
    $now = date('Y-m-d H:i:s');
    
    // First query with exact type
    $stmt = $conn->prepare("SELECT * FROM temp_otps WHERE email = ? AND otp_type = ? AND expires_at >= ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("sss", $email, $type, $now);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        if (trim($row['otp_code']) === $input_otp) {
            $conn->query("DELETE FROM temp_otps WHERE id = " . $row['id']);
            return true;
        }
    }
    
    // Fallback query matching email and any recent active OTP for this user
    $stmt2 = $conn->prepare("SELECT * FROM temp_otps WHERE email = ? AND expires_at >= ? ORDER BY id DESC LIMIT 1");
    $stmt2->bind_param("ss", $email, $now);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    if ($row2 = $res2->fetch_assoc()) {
        if (trim($row2['otp_code']) === $input_otp) {
            $conn->query("DELETE FROM temp_otps WHERE id = " . $row2['id']);
            return true;
        }
    }
    
    return false;
}
?>

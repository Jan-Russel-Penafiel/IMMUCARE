<?php
/**
 * SMS Helper Functions
 * 
 * This file contains shared SMS functionality used across the application.
 */

/**
 * Send an SMS using the configured SMS provider
 * 
 * @param string $phone_number Recipient phone number
 * @param string $message SMS message
 * @return array Response with status and details
 */
function sendSMS($phone_number, $message) {
    try {
        // Format phone number (remove any non-numeric characters and ensure it starts with country code)
        $phone_number = preg_replace('/[^0-9]/', '', $phone_number);
        if (!preg_match('/^63/', $phone_number)) {
            $phone_number = '63' . ltrim($phone_number, '0');
        }
        
        // Prepare request data
        $send_data = [
            'sender_id' => PHILSMS_SENDER_ID,
            'recipient' => "+{$phone_number}",
            'message' => $message
        ];
        
        // Log request data for debugging
        error_log("SMS Request Data: " . json_encode($send_data));
        
        // Initialize cURL
        $ch = curl_init();
        
        // Set cURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://app.philsms.com/api/v3/sms/send",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($send_data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer " . PHILSMS_API_KEY
            ]
        ]);
        
        // Execute cURL request
        $response = curl_exec($ch);
        
        // Check for cURL errors
        if(curl_errno($ch)) {
            $error = curl_error($ch);
            error_log("PhilSMS cURL Error: " . $error);
            curl_close($ch);
            return [
                'status' => 'failed',
                'message' => "cURL Error: {$error}"
            ];
        }
        
        // Get HTTP status code
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Parse response
        $response_data = json_decode($response, true);
        error_log("PhilSMS API Response: " . $response);
        error_log("PhilSMS API HTTP Status Code: " . $http_code);
        
        // Check if the message was sent successfully
        if ($http_code === 200) {
            if (isset($response_data['status']) && $response_data['status'] === 'success') {
                return [
                    'status' => 'sent',
                    'message' => 'Message sent successfully',
                    'response' => $response_data
                ];
            }
        }
        
        // If we get here, there was an error
        return [
            'status' => 'failed',
            'message' => "API Error: HTTP Code {$http_code}, Response: {$response}",
            'response' => $response_data
        ];
        
    } catch (Exception $e) {
        error_log("SMS Exception: " . $e->getMessage());
        return [
            'status' => 'failed',
            'message' => "Exception: " . $e->getMessage()
        ];
    }
}

/**
 * Send OTP via SMS
 * 
 * @param string $phone_number Recipient phone number
 * @param string $otp The OTP to send
 * @return array Response with status and details
 */
function sendOTP($phone_number, $otp) {
    $message = "IMMUCARE: Your OTP is {$otp}. This code will expire in 10 minutes.";
    return sendSMS($phone_number, $message);
}

/**
 * Log SMS to database
 * 
 * @param mysqli $conn Database connection
 * @param int $recipient_id Recipient ID (patient/resident ID)
 * @param string $phone_number Recipient phone number
 * @param string $message SMS message
 * @param string $status SMS status (Pending, Sent, Failed)
 * @param string $related_to Related entity type (e.g., clearance_request, immunization)
 * @param int $related_id Related entity ID
 * @param array $response_data API response data
 * @return int|bool The ID of the inserted record or false on failure
 */
function logSMS($conn, $recipient_id, $phone_number, $message, $status, $related_to = 'general', $related_id = null, $response_data = null) {
    $provider_response = $response_data ? json_encode($response_data) : null;
    
    $query = "INSERT INTO sms_logs (
                patient_id, 
                phone_number, 
                message, 
                status, 
                provider_response,
                related_to,
                related_id,
                sent_at, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        error_log("Error preparing SMS log statement: " . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "isssssi", 
        $recipient_id, 
        $phone_number, 
        $message, 
        $status, 
        $provider_response,
        $related_to,
        $related_id
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Error executing SMS log statement: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    
    $log_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    
    return $log_id;
}
?> 
<?php
/**
 * SMS Helper Functions
 * 
 * This file contains shared SMS functionality used across the application.
 */

/**
 * Send SMS using IPROG SMS API
 * @param string $phone_number Recipient phone number
 * @param string $message SMS message content
 * @param string $api_key IPROG SMS API token
 * @return array Response with status and message
 */
function sendSMSUsingIPROG($phone_number, $message, $api_key) {
    // Prepare the phone number (remove any spaces and ensure 63 format for IPROG)
    $phone_number = str_replace([' ', '-'], '', $phone_number);
    if (substr($phone_number, 0, 1) === '0') {
        $phone_number = '63' . substr($phone_number, 1);
    } elseif (substr($phone_number, 0, 1) === '+') {
        $phone_number = substr($phone_number, 1);
    }

    // Validate phone number format
    if (!preg_match('/^63[0-9]{10}$/', $phone_number)) {
        return array(
            'success' => false,
            'status' => 'failed',
            'message' => 'Invalid phone number format. Must be a valid Philippine mobile number.'
        );
    }

    // Prepare the request data for IPROG SMS API
    $data = array(
        'api_token' => $api_key,
        'message' => $message,
        'phone_number' => $phone_number
    );

    // Initialize cURL session
    $ch = curl_init("https://sms.iprogtech.com/api/v1/sms_messages");

    // Set cURL options for IPROG SMS
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded'
        ),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ));

    // Execute cURL request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);

    // Close cURL session
    curl_close($ch);

    // Log the API request for debugging
    error_log(sprintf(
        "IPROG SMS API Request - Number: %s, Status: %d, Response: %s, Error: %s",
        $phone_number,
        $http_code,
        $response,
        $curl_error
    ));

    // Handle cURL errors
    if ($curl_errno) {
        return array(
            'success' => false,
            'status' => 'failed',
            'message' => 'Connection error: ' . $curl_error,
            'error_code' => $curl_errno
        );
    }

    // Parse response
    $result = json_decode($response, true);

    // Handle API response for IPROG SMS
    if ($http_code === 200 || $http_code === 201) {
        // IPROG SMS typically returns success in different formats
        // Check for common success indicators
        if ((isset($result['status']) && $result['status'] === 'success') ||
            (isset($result['success']) && $result['success'] === true) ||
            (isset($result['message']) && stripos($result['message'], 'sent') !== false) ||
            (!isset($result['error']) && !isset($result['errors']))) {
            return array(
                'success' => true,
                'status' => 'sent',
                'message' => 'SMS sent successfully',
                'reference_id' => $result['message_id'] ?? $result['id'] ?? $result['reference'] ?? null,
                'delivery_status' => $result['status'] ?? 'Sent',
                'timestamp' => $result['timestamp'] ?? date('Y-m-d g:i A'),
                'response' => $result
            );
        }
    }

    // Handle error responses
    $error_message = isset($result['message']) ? $result['message'] : 
                    (isset($result['error']) ? $result['error'] : 
                    (isset($result['errors']) ? (is_array($result['errors']) ? implode(', ', $result['errors']) : $result['errors']) : 'Unknown error occurred'));
    
    return array(
        'success' => false,
        'status' => 'failed',
        'message' => 'API Error: ' . $error_message,
        'error_code' => $http_code,
        'error_details' => $result
    );
}

/**
 * Send an SMS using the configured SMS provider (IPROG SMS)
 * 
 * @param string $phone_number Recipient phone number
 * @param string $message SMS message
 * @return array Response with status and details
 */
function sendSMS($phone_number, $message) {
    try {
        // Get API key from constants (IPROG SMS API key)
        $api_key = defined('IPROG_SMS_API_KEY') ? IPROG_SMS_API_KEY : '1ef3b27ea753780a90cbdf07d027fb7b52791004';
        
        // Use IPROG SMS implementation
        $result = sendSMSUsingIPROG($phone_number, $message, $api_key);
        
        // Convert to expected format for backward compatibility
        return [
            'status' => $result['status'],
            'message' => $result['message'],
            'response' => $result
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
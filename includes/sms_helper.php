<?php
/**
 * SMS Helper Functions
 * 
 * This file contains shared SMS functionality used across the application.
 */

/**
 * Format SMS message with universal prefix for IPROG template compatibility
 * Based on the approved iProg SMS template that requires:
 * "This is an important message from the Organization. [Your message]"
 * 
 * @param string $message Original message content
 * @return string Formatted message with prefix
 */
function formatSMSMessage($message) {
    $prefix = 'This is an important message from the Organization. ';
    // Don't add prefix if message already starts with it
    if (strpos($message, $prefix) === 0) {
        return $message;
    }
    return $prefix . $message;
}

/**
 * Send an SMS using the configured SMS provider
 * 
 * @param string $phone_number Recipient phone number
 * @param string $message SMS message
 * @return array Response with status and details
 */
function sendSMS($phone_number, $message) {
    try {
        // Format phone number (remove any non-numeric characters except +)
        $phone_number = preg_replace('/[^0-9+]/', '', $phone_number);
        
        // If number starts with 0, replace with +63
        if (substr($phone_number, 0, 1) === '0') {
            $phone_number = '63' . substr($phone_number, 1);
        } elseif (substr($phone_number, 0, 3) === '+63') {
            // Remove + sign for API
            $phone_number = substr($phone_number, 1);
        } elseif (!preg_match('/^63/', $phone_number)) {
            // Add country code if not present
            $phone_number = '63' . $phone_number;
        }
        
        // Format message with universal prefix for IPROG template compatibility
        $formatted_message = formatSMSMessage($message);
        
        // Prepare request data for IProg SMS API
        $send_data = [
            'api_token' => IPROG_SMS_API_KEY,
            'phone_number' => $phone_number,
            'message' => $formatted_message
        ];
        
        // Log request data for debugging
        error_log("IProg SMS Request Data: " . json_encode($send_data));
        
        // Initialize cURL
        $ch = curl_init();
        
        // Set cURL options for IProg SMS API
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://sms.iprogtech.com/api/v1/sms_messages",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($send_data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json"
            ]
        ]);
        
        // Execute cURL request
        $response = curl_exec($ch);
        
        // Check for cURL errors
        if(curl_errno($ch)) {
            $error = curl_error($ch);
            error_log("IProg SMS cURL Error: " . $error);
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
        error_log("IProg SMS API Response: " . $response);
        error_log("IProg SMS API HTTP Status Code: " . $http_code);
        
        // Check if the message was sent successfully
        // iProg API returns HTTP 200 but may have error status in JSON response
        if ($http_code === 200 || $http_code === 201) {
            // Check if API response contains an error status (500 = error, 200 = success)
            if (isset($response_data['status']) && $response_data['status'] == 500) {
                // API returned an error in the response body
                $error_msg = is_array($response_data['message']) 
                    ? implode(', ', $response_data['message']) 
                    : $response_data['message'];
                error_log("IProg SMS API Error: " . $error_msg);
                return [
                    'status' => 'failed',
                    'message' => "API Error: " . $error_msg,
                    'response' => $response_data
                ];
            }
            
            // Success - SMS was sent
            return [
                'status' => 'sent',
                'message' => 'Message sent successfully',
                'response' => $response_data
            ];
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
<?php

class TransactionHelper {
    
    /**
     * Generate transaction data including ID and number
     * @param mysqli $conn Database connection
     * @return array Transaction data with transaction_id and transaction_number
     */
    public static function generateTransactionData($conn) {
        // Generate a shorter unique transaction ID
        $transaction_id = 'TX' . date('ymdHis');
        
        // Get the next transaction number
        $transaction_number = self::getNextTransactionNumber($conn);
        
        return [
            'transaction_id' => $transaction_id,
            'transaction_number' => $transaction_number
        ];
    }
    
    /**
     * Get the next sequential transaction number
     * @param mysqli $conn Database connection
     * @return string Next transaction number
     */
    private static function getNextTransactionNumber($conn) {
        // Get the highest transaction number from both appointments and immunizations tables
        $query = "
            SELECT MAX(CAST(SUBSTRING(transaction_number, 3) AS UNSIGNED)) as max_num
            FROM (
                SELECT transaction_number FROM appointments WHERE transaction_number IS NOT NULL AND transaction_number LIKE 'TX%'
                UNION ALL
                SELECT transaction_number FROM immunizations WHERE transaction_number IS NOT NULL AND transaction_number LIKE 'TX%'
            ) as combined_transactions
        ";
        
        $result = $conn->query($query);
        $max_num = 0;
        
        if ($result && $row = $result->fetch_assoc()) {
            $max_num = intval($row['max_num']);
        }
        
        // Return the next number with TX prefix and zero-padded to 4 digits
        return 'TX' . str_pad(($max_num + 1), 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Format transaction ID for display
     * @param string $transaction_id
     * @return string Formatted transaction ID
     */
    public static function formatTransactionId($transaction_id) {
        if (empty($transaction_id)) {
            return 'N/A';
        }
        return $transaction_id;
    }
    
    /**
     * Format transaction number for display
     * @param string $transaction_number
     * @return string Formatted transaction number
     */
    public static function formatTransactionNumber($transaction_number) {
        if (empty($transaction_number)) {
            return 'N/A';
        }
        return $transaction_number;
    }
}
?>
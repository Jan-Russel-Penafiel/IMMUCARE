<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FileTransfer {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        // Configure SMTP settings
        $this->mail->isSMTP();
        $this->mail->Host = SMTP_HOST;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = SMTP_USER;
        $this->mail->Password = SMTP_PASS;
        $this->mail->SMTPSecure = SMTP_SECURE;
        $this->mail->Port = SMTP_PORT;
        
        // Set sender
        $this->mail->setFrom(SMTP_USER, 'ImmuCare System');
    }
    
    /**
     * Generate Excel file with health data
     * @param array $data Array of data to be included in Excel
     * @return string Path to the generated Excel file
     */
    private function generateExcelFile($data) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $headers = ['Patient ID', 'Patient Name', 'Age', 'Gender', 'Vaccine', 'Dose Number', 'Batch Number', 'Date Administered', 'Next Dose Date', 'Immunization Status'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $col++;
        }
        
        // Add data
        $row = 2;
        foreach ($data as $record) {
            $sheet->setCellValue('A' . $row, $record['patient_id']);
            $sheet->setCellValue('B' . $row, $record['name']);
            $sheet->setCellValue('C' . $row, $record['age']);
            $sheet->setCellValue('D' . $row, ucfirst($record['gender']));
            $sheet->setCellValue('E' . $row, $record['vaccine']);
            $sheet->setCellValue('F' . $row, $record['dose_number']);
            $sheet->setCellValue('G' . $row, $record['batch_number'] ?: 'N/A');
            $sheet->setCellValue('H' . $row, date('Y-m-d H:i', strtotime($record['date'])));
            $sheet->setCellValue('I' . $row, $record['next_dose_date'] ? date('Y-m-d', strtotime($record['next_dose_date'])) : 'N/A');
            $sheet->setCellValue('J' . $row, $record['status']);
            
            // Color code the status
            $statusCell = 'J' . $row;
            switch ($record['status']) {
                case 'Completed':
                    $sheet->getStyle($statusCell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('C6EFCE');
                    break;
                case 'Pending Next Dose':
                    $sheet->getStyle($statusCell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFEB9C');
                    break;
                case 'Due for Next Dose':
                    $sheet->getStyle($statusCell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFC7CE');
                    break;
            }
            $row++;
        }
        
        // Format the date columns
        $sheet->getStyle('H2:H' . ($row-1))->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm');
        $sheet->getStyle('I2:I' . ($row-1))->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        
        // Auto-size columns
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Save file
        $filename = 'immucare_health_data_' . date('Y-m-d_His') . '.xlsx';
        $filepath = sys_get_temp_dir() . '/' . $filename;
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        return $filepath;
    }
    
    /**
     * Generate PDF report
     * @param array $data Array of data to be included in PDF
     * @return string Path to the generated PDF file
     */
    private function generatePDFReport($data) {
        require_once(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php');
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('ImmuCare System');
        $pdf->SetAuthor('ImmuCare');
        $pdf->SetTitle('ImmuCare Health Data Report');
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 10);
        
        // Create the table content
        $html = '<h1 style="color: #4285f4;">ImmuCare Health Data Report</h1>';
        $html .= '<p style="color: #666;">Generated on: ' . date('F d, Y \a\t g:i A') . '</p>';
        $html .= '<table border="1" cellpadding="4" style="width: 100%; border-collapse: collapse;">
                    <tr style="background-color: #f5f5f5; font-weight: bold;">
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Vaccine</th>
                        <th>Dose</th>
                        <th>Date Administered</th>
                        <th>Status</th>
                    </tr>';
        
        foreach ($data as $record) {
            // Set status cell color
            $statusColor = '';
            switch ($record['status']) {
                case 'Completed':
                    $statusColor = ' background-color: #C6EFCE;';
                    break;
                case 'Pending Next Dose':
                    $statusColor = ' background-color: #FFEB9C;';
                    break;
                case 'Due for Next Dose':
                    $statusColor = ' background-color: #FFC7CE;';
                    break;
            }
            
            $html .= '<tr>
                        <td>' . $record['patient_id'] . '</td>
                        <td>' . htmlspecialchars($record['name']) . '</td>
                        <td>' . $record['age'] . '</td>
                        <td>' . ucfirst($record['gender']) . '</td>
                        <td>' . htmlspecialchars($record['vaccine']) . '</td>
                        <td>' . $record['dose_number'] . '</td>
                        <td>' . date('Y-m-d H:i', strtotime($record['date'])) . '</td>
                        <td style="' . $statusColor . '">' . $record['status'] . '</td>
                    </tr>';
        }
        
        $html .= '</table>';
        
        // Add summary
        $totalRecords = count($data);
        $completedCount = count(array_filter($data, function($record) { return $record['status'] === 'Completed'; }));
        $pendingCount = count(array_filter($data, function($record) { return $record['status'] === 'Pending Next Dose'; }));
        $dueCount = count(array_filter($data, function($record) { return $record['status'] === 'Due for Next Dose'; }));
        
        $html .= '<br><h3>Summary</h3>';
        $html .= '<p><strong>Total Records:</strong> ' . $totalRecords . '</p>';
        $html .= '<p><strong>Completed:</strong> ' . $completedCount . '</p>';
        $html .= '<p><strong>Pending Next Dose:</strong> ' . $pendingCount . '</p>';
        $html .= '<p><strong>Due for Next Dose:</strong> ' . $dueCount . '</p>';
        
        // Print the HTML content
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Save file
        $filename = 'immucare_health_data_' . date('Y-m-d_His') . '.pdf';
        $filepath = sys_get_temp_dir() . '/' . $filename;
        $pdf->Output($filepath, 'F');
        
        return $filepath;
    }
    
    /**
     * Send files to Google Drive via email
     * @param array $data Array of health data
     * @param string $email Recipient email address
     * @param int $health_center_id Health center ID to send data to
     * @return bool Whether the transfer was successful
     */
    public function sendToGoogleDrive($data, $email = null, $health_center_id = null) {
        // If no email is provided, try to get it from health_center_id
        if ($email === null && $health_center_id !== null) {
            $email = $this->getHealthCenterEmail($health_center_id);
        }
        
        // For backward compatibility, redirect to the new method
        $dataSets = [
            [
                'data' => $data,
                'label' => 'Health Data Report',
                'formats' => ['excel', 'pdf']
            ]
        ];
        
        return $this->sendMultipleToGoogleDrive($dataSets, $email, $health_center_id);
    }
    
    /**
     * Get email address for a health center
     * @param int $health_center_id Health center ID
     * @return string Email address
     */
    private function getHealthCenterEmail($health_center_id) {
        global $conn;
        
        if (!$health_center_id) {
            return 'sucuanomichaeljohn@gmail.com'; // Default fallback email
        }
        
        try {
            $stmt = $conn->prepare("SELECT email FROM health_centers WHERE id = ? AND is_active = 1");
            $stmt->bind_param("i", $health_center_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $health_center = $result->fetch_assoc();
                return $health_center['email'] ?: 'sucuanomichaeljohn@gmail.com'; // Use default if empty
            }
        } catch (Exception $e) {
            error_log("Error fetching health center email: " . $e->getMessage());
        }
        
        return 'sucuanomichaeljohn@gmail.com'; // Default fallback email
    }
    
    /**
     * Send multiple data sets to Google Drive via email
     * @param array $dataSets Array of data sets, each containing 'data', 'label', and 'formats' keys
     * @param string $email Recipient email address
     * @param int $health_center_id Health center ID to send data to
     * @return bool Whether the transfer was successful
     */
    public function sendMultipleToGoogleDrive($dataSets, $email = null, $health_center_id = null) {
        try {
            // Check if data sets are valid
            if (empty($dataSets) || !is_array($dataSets)) {
                throw new Exception("No valid data sets provided for transfer");
            }
            
            // If no email is provided, try to get it from health_center_id
            if ($email === null && $health_center_id !== null) {
                $email = $this->getHealthCenterEmail($health_center_id);
            } else if ($email === null) {
                // Use default email if neither email nor health_center_id is provided
                $email = 'sucuanomichaeljohn@gmail.com';
            }
            
            // Validate email
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address for transfer");
            }
            
            // Reset mail object for fresh configuration
            $this->mail = new PHPMailer(true);
            $this->mail->isSMTP();
            $this->mail->Host = SMTP_HOST;
            $this->mail->SMTPAuth = true;
            $this->mail->Username = SMTP_USER;
            $this->mail->Password = SMTP_PASS;
            $this->mail->SMTPSecure = SMTP_SECURE;
            $this->mail->Port = SMTP_PORT;
            $this->mail->setFrom(SMTP_USER, 'ImmuCare System');
            
            // Configure email
            $this->mail->addAddress($email);
            $this->mail->Subject = 'ImmuCare Health Data Reports - ' . date('Y-m-d');
            $this->mail->isHTML(true);
            
            // Track all generated files and their details
            $generatedFiles = [];
            $totalRecords = 0;
            
            // Start output buffering to allow for faster processing
            ob_start();
            
            // Optimize: Set time limit higher for large datasets
            set_time_limit(300); // 5 minutes
            
            // Generate all files for all data sets
            foreach ($dataSets as $dataSet) {
                $data = $dataSet['data'];
                $label = $dataSet['label'];
                $formats = $dataSet['formats'];
                $totalRecords += count($data);
                
                // Optimize: Process both formats in one loop if both are selected
                if (count($formats) > 1 && in_array('excel', $formats) && in_array('pdf', $formats)) {
                    // Generate Excel file
                    $excelFile = $this->generateExcelFile($data);
                    $generatedFiles[] = [
                        'path' => $excelFile,
                        'name' => basename($excelFile),
                        'format' => 'Excel',
                        'label' => $label
                    ];
                    
                    // Generate PDF file immediately after
                    $pdfFile = $this->generatePDFReport($data);
                    $generatedFiles[] = [
                        'path' => $pdfFile,
                        'name' => basename($pdfFile),
                        'format' => 'PDF',
                        'label' => $label
                    ];
                } else {
                    // Process formats individually if not both selected
                    foreach ($formats as $format) {
                        if ($format === 'excel') {
                            $file = $this->generateExcelFile($data);
                            $generatedFiles[] = [
                                'path' => $file,
                                'name' => basename($file),
                                'format' => 'Excel',
                                'label' => $label
                            ];
                        } 
                        else if ($format === 'pdf') {
                            $file = $this->generatePDFReport($data);
                            $generatedFiles[] = [
                                'path' => $file,
                                'name' => basename($file),
                                'format' => 'PDF',
                                'label' => $label
                            ];
                        }
                    }
                }
                
                // Flush output buffer periodically to prevent memory issues
                ob_flush();
                flush();
            }
            
            // Build email body with details about all attachments
            $emailBody = '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <h2 style="color: #4285f4;">ImmuCare Health Data Reports</h2>
                    <p>Please find attached the health data reports in various formats.</p>
                    <p><strong>Report Details:</strong></p>
                    <ul>
                        <li>Total Records: ' . $totalRecords . '</li>
                        <li>Generated on: ' . date('F d, Y \a\t g:i A') . '</li>
                        <li>Reports Included: ' . count($generatedFiles) . '</li>
                    </ul>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <tr style="background-color: #f5f5f5;">
                            <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Report</th>
                            <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Format</th>
                            <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Filename</th>
                        </tr>';
            
            foreach ($generatedFiles as $file) {
                $emailBody .= '
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 8px;">' . htmlspecialchars($file['label']) . '</td>
                            <td style="border: 1px solid #ddd; padding: 8px;">' . $file['format'] . '</td>
                            <td style="border: 1px solid #ddd; padding: 8px;">' . htmlspecialchars($file['name']) . '</td>
                        </tr>';
            }
            
            $emailBody .= '
                    </table>
                    <p style="margin-top: 20px;">This is an automated message from the ImmuCare System.</p>
                    <p>For any questions, please contact our support team.</p>
                </div>';
            
            $this->mail->Body = $emailBody;
            
            // Add all attachments
            $fileNames = [];
            foreach ($generatedFiles as $file) {
                $this->mail->addAttachment($file['path'], $file['name']);
                $fileNames[] = $file['name'];
            }
            
            // Send email
            $success = $this->mail->send();
            
            // Clean up temporary files
            foreach ($generatedFiles as $file) {
                unlink($file['path']);
            }
            
            // Log the transfer
            if ($success) {
                $this->logTransfer($email, $fileNames, 'completed');
            } else {
                $this->logTransfer($email, $fileNames, 'failed');
            }
            
            return $success;
            
        } catch (Exception $e) {
            // Log error
            error_log("File transfer failed: " . $e->getMessage());
            $this->logTransfer($email, [], 'failed', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log the file transfer
     * @param string $email Recipient email
     * @param array $files Array of transferred files
     * @param string $status Transfer status
     * @param string $message Optional status message
     */
    private function logTransfer($email, $files, $status, $message = '') {
        try {
            global $conn;
            
            // Ensure email is never null or empty in the logs
            if (empty($email)) {
                $email = 'no-email-provided@immucare.local';
            }
            
            // Safely handle file list - ensure it's an array before imploding
            if (!is_array($files)) {
                $files = [];
            }
            $files_str = implode(', ', $files);
            
            // Ensure message is not too long for the database column
            if (strlen($message) > 255) {
                $message = substr($message, 0, 252) . '...';
            }
            
            // Prepare and execute statement with error handling
            $stmt = $conn->prepare("INSERT INTO data_transfers (initiated_by, destination, file_name, transfer_type, status, status_message, started_at, completed_at, created_at) VALUES (?, ?, ?, 'manual', ?, ?, NOW(), NOW(), NOW())");
            
            if (!$stmt) {
                error_log("MySQL prepare error in logTransfer: " . $conn->error);
                return;
            }
            
            $initiated_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Default to admin user ID 1
            
            $stmt->bind_param("issss", $initiated_by, $email, $files_str, $status, $message);
            $stmt->execute();
            
            if ($stmt->error) {
                error_log("MySQL execute error in logTransfer: " . $stmt->error);
            }
            
            $stmt->close();
        } catch (Exception $e) {
            // Log error but don't fail the entire process because of logging failure
            error_log("Failed to log transfer: " . $e->getMessage());
        }
    }
} 
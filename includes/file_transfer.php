<?php

require_once 'config.php';
require_once 'vendor/autoload.php';
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
        $headers = ['Patient ID', 'Name', 'Age', 'Gender', 'Vaccine', 'Date Administered', 'Immunization Status'];
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
            $sheet->setCellValue('D' . $row, $record['gender']);
            $sheet->setCellValue('E' . $row, $record['vaccine']);
            $sheet->setCellValue('F' . $row, date('Y-m-d H:i', strtotime($record['date'])));
            $sheet->setCellValue('G' . $row, $record['status']);
            
            // Color code the status
            $statusCell = 'G' . $row;
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
        
        // Format the date column
        $sheet->getStyle('F2:F' . ($row-1))->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm');
        
        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Save file
        $filename = 'health_data_' . date('Y-m-d_His') . '.xlsx';
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
        $pdf->SetTitle('Health Data Report');
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 10);
        
        // Create the table content
        $html = '<h1>Health Data Report</h1>';
        $html .= '<table border="1" cellpadding="4">
                    <tr style="background-color: #f5f5f5; font-weight: bold;">
                        <th>Patient ID</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Vaccine</th>
                        <th>Date Administered</th>
                        <th>Immunization Status</th>
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
                        <td>' . $record['name'] . '</td>
                        <td>' . $record['age'] . '</td>
                        <td>' . $record['gender'] . '</td>
                        <td>' . $record['vaccine'] . '</td>
                        <td>' . date('Y-m-d H:i', strtotime($record['date'])) . '</td>
                        <td style="' . $statusColor . '">' . $record['status'] . '</td>
                    </tr>';
        }
        
        $html .= '</table>';
        
        // Print the HTML content
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Save file
        $filename = 'health_data_' . date('Y-m-d_His') . '.pdf';
        $filepath = sys_get_temp_dir() . '/' . $filename;
        $pdf->Output($filepath, 'F');
        
        return $filepath;
    }
    
    /**
     * Send files to Google Drive via email
     * @param array $data Array of health data
     * @param string $email Recipient email address
     * @return bool Whether the transfer was successful
     */
    public function sendToGoogleDrive($data, $email = 'artiedastephany@gmail.com') {
        try {
            // Generate files
            $excelFile = $this->generateExcelFile($data);
            $pdfFile = $this->generatePDFReport($data);
            
            // Configure email
            $this->mail->addAddress($email);
            $this->mail->Subject = 'ImmuCare Health Data Report - ' . date('Y-m-d');
            $this->mail->Body = 'Please find attached the health data report in Excel and PDF formats.<br><br>'
                             . 'This is an automated message from the ImmuCare System.';
            $this->mail->isHTML(true);
            
            // Add attachments
            $this->mail->addAttachment($excelFile, basename($excelFile));
            $this->mail->addAttachment($pdfFile, basename($pdfFile));
            
            // Send email
            $success = $this->mail->send();
            
            // Clean up temporary files
            unlink($excelFile);
            unlink($pdfFile);
            
            // Log the transfer
            if ($success) {
                $this->logTransfer($email, [basename($excelFile), basename($pdfFile)], 'completed');
            } else {
                $this->logTransfer($email, [basename($excelFile), basename($pdfFile)], 'failed');
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
        global $conn;
        
        $stmt = $conn->prepare("INSERT INTO data_transfers (initiated_by, destination, file_name, transfer_type, status, status_message, started_at, completed_at, created_at) VALUES (?, ?, ?, 'manual', ?, ?, NOW(), NOW(), NOW())");
        
        $initiated_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        $files_str = implode(', ', $files);
        
        $stmt->bind_param("issss", $initiated_by, $email, $files_str, $status, $message);
        $stmt->execute();
    }
} 
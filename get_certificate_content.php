<?php
session_start();
require 'config.php';

// Check if user is logged in and is a nurse
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    http_response_code(403);
    exit('Access denied');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit('Invalid immunization ID');
}

$immunization_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    exit("Connection failed: " . $conn->connect_error);
}

// Get immunization details with patient and vaccine information
$stmt = $conn->prepare("SELECT i.*, p.first_name, p.middle_name, p.last_name, p.date_of_birth, p.gender, 
                        p.purok, p.city, p.province, p.phone_number, v.name as vaccine_name, 
                        v.manufacturer, v.description as vaccine_description, v.recommended_age,
                        u.name as administered_by_name
                        FROM immunizations i 
                        JOIN patients p ON i.patient_id = p.id 
                        JOIN vaccines v ON i.vaccine_id = v.id
                        JOIN users u ON i.administered_by = u.id
                        WHERE i.id = ? AND i.administered_by = ?");
$stmt->bind_param("ii", $immunization_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    exit('Immunization record not found');
}

$immunization = $result->fetch_assoc();
$patient_age = date_diff(date_create($immunization['date_of_birth']), date_create('today'))->y;
$conn->close();
?>

<style>
@media print {
    .no-print { display: none !important; }
    body { margin: 0; padding: 10px; font-size: 12px; }
    .certificate { 
        width: 100%; 
        max-width: none; 
        box-shadow: none; 
        border: 2px solid #000;
        page-break-inside: avoid;
        height: auto;
        min-height: auto;
    }
}

.certificate {
    max-width: 700px;
    margin: 0 auto;
    padding: 20px;
    border: 2px solid #4285f4;
    border-radius: 8px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    font-family: 'Times New Roman', serif;
    position: relative;
    box-shadow: 0 0 15px rgba(0,0,0,0.1);
    font-size: 13px;
}

.certificate::before {
    content: '';
    position: absolute;
    top: 8px;
    left: 8px;
    right: 8px;
    bottom: 8px;
    border: 1px solid #4285f4;
    border-radius: 4px;
}

.certificate-header {
    text-align: center;
    margin-bottom: 15px;
    border-bottom: 1px solid #4285f4;
    padding-bottom: 10px;
}

.certificate-title {
    font-size: 22px;
    font-weight: bold;
    color: #4285f4;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.certificate-subtitle {
    font-size: 14px;
    color: #666;
    margin-bottom: 3px;
}

.certificate-body {
    line-height: 1.4;
    font-size: 13px;
    text-align: center;
    margin-bottom: 20px;
}

.patient-name {
    font-size: 18px;
    font-weight: bold;
    color: #4285f4;
    text-decoration: underline;
    margin: 10px 0;
}

.vaccine-info {
    background-color: #f8f9fa;
    padding: 12px;
    border-radius: 6px;
    margin: 12px 0;
    border-left: 3px solid #4285f4;
}

.certificate-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 15px 0;
    text-align: left;
    font-size: 12px;
}

.detail-item {
    margin-bottom: 6px;
}

.detail-label {
    font-weight: bold;
    color: #333;
    display: inline-block;
    width: 100px;
    font-size: 11px;
}

.certificate-footer {
    margin-top: 25px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.signature-section {
    text-align: center;
}

.signature-line {
    border-bottom: 1px solid #333;
    height: 35px;
    margin-bottom: 5px;
    position: relative;
}

.signature-label {
    font-weight: bold;
    color: #333;
    font-size: 12px;
}

.certificate-seal {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 60px;
    height: 60px;
    border: 2px solid #4285f4;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 9px;
    font-weight: bold;
    color: #4285f4;
    text-align: center;
    background: white;
}
</style>

<div class="certificate">
    <div class="certificate-seal">
        OFFICIAL<br>SEAL
    </div>
    
    <div class="certificate-header">
        <div class="certificate-title">Certificate of Immunization</div>
        <div class="certificate-subtitle">ImmuCare Health Management System</div>
        <div style="font-size: 14px; color: #888;">This is to certify that the immunization below has been administered</div>
    </div>
    
    <div class="certificate-body">
        <p style="font-size: 14px; margin-bottom: 5px;">This is to certify that</p>
        
        <div class="patient-name">
            <?php echo htmlspecialchars($immunization['first_name'] . ' ' . ($immunization['middle_name'] ? $immunization['middle_name'] . ' ' : '') . $immunization['last_name']); ?>
        </div>
        
        <p style="margin: 10px 0;">has received the following immunization:</p>
        
        <div class="vaccine-info">
            <h3 style="margin: 0 0 8px 0; color: #4285f4; text-align: center; font-size: 16px;">
                <?php echo htmlspecialchars($immunization['vaccine_name']); ?>
            </h3>
            <p style="margin: 3px 0; text-align: center; font-size: 12px;">
                <strong>Manufacturer:</strong> <?php echo htmlspecialchars($immunization['manufacturer']); ?>
            </p>
            <p style="margin: 3px 0; text-align: center; font-size: 12px;">
                <strong>Description:</strong> <?php echo htmlspecialchars($immunization['vaccine_description']); ?>
            </p>
        </div>
    </div>
    
    <div class="certificate-details">
        <div>
            <div class="detail-item">
                <span class="detail-label">Date of Birth:</span>
                <?php echo date('F j, Y', strtotime($immunization['date_of_birth'])); ?>
            </div>
            <div class="detail-item">
                <span class="detail-label">Age:</span>
                <?php echo $patient_age; ?> years old
            </div>
            <div class="detail-item">
                <span class="detail-label">Gender:</span>
                <?php echo ucfirst($immunization['gender']); ?>
            </div>
            <div class="detail-item">
                <span class="detail-label">Address:</span>
                <?php echo htmlspecialchars($immunization['purok'] . ', ' . $immunization['city'] . ', ' . $immunization['province']); ?>
            </div>
            <div class="detail-item">
                <span class="detail-label">Phone:</span>
                <?php echo htmlspecialchars($immunization['phone_number']); ?>
            </div>
        </div>
        
        <div>
            <div class="detail-item">
                <span class="detail-label">Date Given:</span>
                <?php echo date('F j, Y', strtotime($immunization['administered_date'])); ?>
            </div>
            <div class="detail-item">
                <span class="detail-label">Dose Number:</span>
                Dose <?php echo $immunization['dose_number']; ?>
            </div>
            <div class="detail-item">
                <span class="detail-label">Batch Number:</span>
                <?php echo htmlspecialchars($immunization['batch_number']); ?>
            </div>
            <?php if ($immunization['location']): ?>
            <div class="detail-item">
                <span class="detail-label">Location:</span>
                <?php echo htmlspecialchars($immunization['location']); ?>
            </div>
            <?php endif; ?>
            <?php if ($immunization['next_dose_date']): ?>
            <div class="detail-item">
                <span class="detail-label">Next Dose:</span>
                <?php echo date('F j, Y', strtotime($immunization['next_dose_date'])); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="certificate-footer">
        <div class="signature-section">
            <div class="signature-line"></div>
            <div class="signature-label">Healthcare Provider</div>
            <div style="font-size: 11px; margin-top: 3px;">
                <?php echo htmlspecialchars($immunization['administered_by_name']); ?>
            </div>
        </div>
        
        <div class="signature-section">
            <div class="signature-line"></div>
            <div class="signature-label">Date Issued</div>
            <div style="font-size: 11px; margin-top: 3px;">
                <?php echo date('F j, Y'); ?>
            </div>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 15px; font-size: 10px; color: #666;">
        <p style="margin: 3px 0;">This certificate is issued by ImmuCare Health Management System</p>
        <p style="margin: 3px 0;">Certificate ID: IMM-<?php echo str_pad($immunization['id'], 6, '0', STR_PAD_LEFT); ?></p>
    </div>
</div>

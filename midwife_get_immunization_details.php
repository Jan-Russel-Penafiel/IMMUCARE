<?php
session_start();
require 'config.php';

// Check if user is logged in and is a midwife
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'midwife') {
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
$conn->close();
?>

<div class="immunization-details">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
        <div>
            <h4 style="color: var(--primary-color); margin-bottom: 15px;">Patient Information</h4>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; font-weight: 500;">Name:</td>
                    <td style="padding: 8px 0;"><?php echo htmlspecialchars($immunization['first_name'] . ' ' . ($immunization['middle_name'] ? $immunization['middle_name'] . ' ' : '') . $immunization['last_name']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 500;">Date of Birth:</td>
                    <td style="padding: 8px 0;"><?php echo date('F j, Y', strtotime($immunization['date_of_birth'])); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 500;">Gender:</td>
                    <td style="padding: 8px 0;"><?php echo ucfirst($immunization['gender']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 500;">Address:</td>
                    <td style="padding: 8px 0;"><?php echo htmlspecialchars($immunization['purok'] . ', ' . $immunization['city'] . ', ' . $immunization['province']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 500;">Phone:</td>
                    <td style="padding: 8px 0;"><?php echo htmlspecialchars($immunization['phone_number']); ?></td>
                </tr>
            </table>
        </div>
        
        <div>
            <h4 style="color: var(--primary-color); margin-bottom: 15px;">Vaccine Information</h4>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; font-weight: 500;">Vaccine:</td>
                    <td style="padding: 8px 0;"><?php echo htmlspecialchars($immunization['vaccine_name']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 500;">Manufacturer:</td>
                    <td style="padding: 8px 0;"><?php echo htmlspecialchars($immunization['manufacturer']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 500;">Description:</td>
                    <td style="padding: 8px 0;"><?php echo htmlspecialchars($immunization['vaccine_description']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 500;">Recommended Age:</td>
                    <td style="padding: 8px 0;"><?php echo htmlspecialchars($immunization['recommended_age']); ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <div style="border-top: 1px solid #e1e4e8; padding-top: 20px;">
        <h4 style="color: var(--primary-color); margin-bottom: 15px;">Immunization Details</h4>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0; font-weight: 500; width: 200px;">Date Administered:</td>
                <td style="padding: 8px 0;"><?php echo date('F j, Y g:i A', strtotime($immunization['administered_date'])); ?></td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: 500;">Dose Number:</td>
                <td style="padding: 8px 0;">Dose <?php echo $immunization['dose_number']; ?></td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: 500;">Batch Number:</td>
                <td style="padding: 8px 0;"><?php echo htmlspecialchars($immunization['batch_number']); ?></td>
            </tr>
            <?php if ($immunization['expiration_date']): ?>
            <tr>
                <td style="padding: 8px 0; font-weight: 500;">Expiration Date:</td>
                <td style="padding: 8px 0;"><?php echo date('F j, Y', strtotime($immunization['expiration_date'])); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($immunization['next_dose_date']): ?>
            <tr>
                <td style="padding: 8px 0; font-weight: 500;">Next Dose Date:</td>
                <td style="padding: 8px 0;"><?php echo date('F j, Y', strtotime($immunization['next_dose_date'])); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($immunization['location']): ?>
            <tr>
                <td style="padding: 8px 0; font-weight: 500;">Location:</td>
                <td style="padding: 8px 0;"><?php echo htmlspecialchars($immunization['location']); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="padding: 8px 0; font-weight: 500;">Administered By:</td>
                <td style="padding: 8px 0;"><?php echo htmlspecialchars($immunization['administered_by_name']); ?></td>
            </tr>
            <?php if ($immunization['diagnosis']): ?>
            <tr>
                <td style="padding: 8px 0; font-weight: 500; vertical-align: top;">Diagnosis/Notes:</td>
                <td style="padding: 8px 0;"><?php echo nl2br(htmlspecialchars($immunization['diagnosis'])); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

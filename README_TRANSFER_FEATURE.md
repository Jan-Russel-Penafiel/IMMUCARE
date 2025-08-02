# ImmuCare File Transfer Feature Implementation

## Overview
The file transfer feature allows administrators to export immunization data from the ImmuCare system and send it to municipal health centers via email in both Excel and PDF formats.

## Features Implemented

### 1. Data Export
- Exports immunization records with patient details
- Includes vaccine information, dose numbers, batch numbers
- Calculates patient age and immunization status
- Supports multiple data formats (Excel and PDF)

### 2. File Generation
- **Excel (.xlsx)**: Comprehensive spreadsheet with color-coded status
- **PDF**: Professional report with summary statistics
- Both files include:
  - Patient ID, Name, Age, Gender
  - Vaccine name, dose number, batch number
  - Date administered, next dose date
  - Immunization status (Completed/Pending/Due)

### 3. Email Delivery
- Automated email sending via SMTP (Gmail)
- Attaches both Excel and PDF files
- Professional email template with report details
- Configurable recipient email addresses

### 4. Database Integration
- Uses existing `immucare_db` database structure
- Joins patients, vaccines, and immunizations tables
- Logs transfer history in `data_transfers` table
- Supports health center selection

## Database Schema Used

### Tables Involved:
1. **patients** - Patient information
2. **vaccines** - Vaccine details
3. **immunizations** - Immunization records
4. **health_centers** - Health center information
5. **data_transfers** - Transfer logs
6. **users** - User authentication

### Key Relationships:
- `immunizations.patient_id` → `patients.id`
- `immunizations.vaccine_id` → `vaccines.id`
- `immunizations.administered_by` → `users.id`

## Configuration

### SMTP Settings (config.php):
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'vmctaccollege@gmail.com');
define('SMTP_PASS', 'tqqs fkkh lbuz jbeg');
define('SMTP_SECURE', 'tls');
define('SMTP_PORT', 587);
```

### Dependencies (composer.json):
- PHPMailer (^6.10) - Email functionality
- PhpSpreadsheet (^1.28) - Excel file generation
- TCPDF (^6.6) - PDF file generation

## Usage

### Admin Dashboard Access:
1. Login as administrator
2. Navigate to Admin Dashboard
3. Select "Data Transfer to Municipal Health Center" section
4. Choose health center from dropdown
5. Click "Transfer Data" button

### Process Flow:
1. Admin selects health center
2. System queries immunization data
3. Generates Excel and PDF files
4. Sends email with attachments
5. Logs transfer in database
6. Shows success/error message

## File Structure

### Core Files:
- `admin_dashboard.php` - Main dashboard with transfer interface
- `includes/file_transfer.php` - FileTransfer class implementation
- `config.php` - Database and SMTP configuration

### Generated Files:
- Excel: `immucare_health_data_YYYY-MM-DD_HHMMSS.xlsx`
- PDF: `immucare_health_data_YYYY-MM-DD_HHMMSS.pdf`

## Sample Data

The system includes sample immunization records for testing:
- Patient: Test P Patient (35 years, male)
  - BCG vaccine (Completed)
  - Hepatitis B vaccine (Pending Next Dose)
- Patient: Stephany asdada lablab (23 years, female)
  - BCG vaccine (Completed)
  - DTaP vaccine (Due for Next Dose)

## Error Handling

- Database connection validation
- SMTP configuration checks
- File generation error handling
- Email delivery confirmation
- Transfer logging with status tracking

## Security Features

- Session-based authentication
- Admin role verification
- SQL injection prevention (prepared statements)
- File cleanup after email sending
- Secure SMTP authentication

## Testing

The feature has been tested and verified to work with:
- Database connection ✓
- Data retrieval ✓
- File generation ✓
- Email delivery ✓
- Transfer logging ✓

## Future Enhancements

1. **Scheduled Transfers**: Automated daily/weekly transfers
2. **API Integration**: Direct API calls to health center systems
3. **Data Filtering**: Date range and patient-specific exports
4. **Encryption**: Secure file encryption for sensitive data
5. **Multiple Formats**: Additional export formats (CSV, JSON)

## Troubleshooting

### Common Issues:
1. **SMTP Errors**: Check Gmail app password and 2FA settings
2. **Database Errors**: Verify database connection and table structure
3. **File Generation**: Ensure PHP has write permissions to temp directory
4. **Email Delivery**: Check spam folder and email configuration

### Debug Steps:
1. Check error logs in PHP
2. Verify database connectivity
3. Test SMTP settings
4. Confirm file generation permissions 
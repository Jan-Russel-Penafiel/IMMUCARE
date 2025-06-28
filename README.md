# ImmuCare - Smart Immunization Registry System

ImmuCare is a comprehensive web-based platform for recording, tracking, and managing immunization data. The system provides healthcare providers with tools to efficiently manage patient vaccinations while offering patients easy access to their immunization records and appointment scheduling.

## Features

### User Management
- **Multiple User Roles**: Admin, Midwife, Nurse, and Patient accounts with role-based access control
- **Email OTP Authentication**: Secure login without passwords using email verification
- **User Profile Management**: Complete profile management for all users

### Patient Management
- **Comprehensive Patient Records**: Store complete patient information including medical history
- **Family Management**: Link family members for easier management of household immunizations
- **Search and Filter**: Quickly find patients using various search criteria

### Immunization Management
- **Vaccine Registry**: Complete database of vaccines with schedules and dosage information
- **Immunization Records**: Track administered vaccines, including batch numbers and expiration dates
- **Immunization Schedule**: Automatically calculate and track due dates for next doses
- **Vaccine Inventory**: Track vaccine stock levels and expiration dates

### Appointment System
- **Online Appointment Booking**: Patients can request appointments online
- **Appointment Management**: Staff can manage, confirm, reschedule, or cancel appointments
- **Calendar Integration**: View appointments in calendar format

### Notifications
- **SMS Notifications**: Send appointment reminders and vaccination alerts via SMS
- **Email Notifications**: Send detailed information and reminders via email
- **Notification Templates**: Customizable templates for different notification types

### Data Transfer
- **Municipal Health Center Integration**: Transfer immunization data to municipal health centers
- **Export Functionality**: Export data in various formats (CSV, Excel, PDF)
- **Audit Trail**: Track all data transfers with detailed logs

### Reporting
- **Immunization Coverage Reports**: Generate reports on immunization coverage by area, age group, etc.
- **Missed Appointment Reports**: Track and follow up on missed appointments
- **Custom Reports**: Create custom reports based on various parameters

## Database Structure

The database consists of the following main tables:

1. **roles**: User role definitions (admin, midwife, nurse, patient)
2. **users**: User account information with role-based access
3. **patients**: Detailed patient information
4. **vaccines**: Vaccine catalog with details and dosage information
5. **immunizations**: Records of administered vaccines
6. **appointments**: Patient appointment scheduling and tracking
7. **notifications**: System notifications for users
8. **sms_logs**: Log of all SMS messages sent
9. **email_logs**: Log of all emails sent
10. **data_transfers**: Records of data transfers to municipal health centers
11. **health_centers**: Information about connected municipal health centers
12. **system_settings**: System configuration settings

## Technical Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- SMTP server for email notifications
- SMS gateway integration (Twilio recommended)

## Installation

1. Clone the repository
2. Import the database schema using `setup_database.sql`
3. Configure database connection in `config.php`
4. Configure SMTP settings in `config.php`
5. Configure SMS gateway settings in system settings
6. Access the system and log in with the default admin account

## Security Features

- Email OTP authentication instead of password-based login
- OTP expiration after 10 minutes
- Session management and timeout
- Input validation and sanitization
- CSRF protection
- XSS prevention
- Role-based access control

## License

[MIT License](LICENSE)

## Support

For support, please contact support@immucare.com 
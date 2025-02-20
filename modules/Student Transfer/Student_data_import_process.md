# here's how the student transfer import process works

1. Upload Phase:
User uploads the transfer package (.zip) with password
System validates the package and password
Files are extracted to a temporary directory

1. Duplicate Check:
System checks for potential duplicates by:  
Name matching
Date of birth
Previous school
Shows warnings if duplicates found

1. Data Preview:
Shows preview of student data including:
Personal information
Academic records
Medical data
Family information
Custom field data

1. Application Creation:
Creates an application form with:
Student details
Medical conditions
Family contacts
Previous school info
Custom field data
Links to original transfer record
Sets status as "Pending"

1. Post-Import Actions:
Moves attachments to appropriate directories
Updates transfer status
Sends notifications
Cleans up temporary files

To implement this, you would:

- Go to Student Transfer module
- Click "Import Student Transfer"
- Upload the .zip file received from previous school
- Enter the password provided
- Review any duplicate warnings
- Preview the data
- Confirm the import

The system will then:

- Create an application form
- Import all student data
- Move attachments
- Send notifications
- Allow admins to review and approve

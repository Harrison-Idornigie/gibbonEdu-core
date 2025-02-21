<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\StudentTransfer\Domain;

use Gibbon\Domain\User\UserGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\Students\ApplicationFormGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Services\Format;

/**
 * Import Processor
 *
 * Handles duplicate checking and application form creation
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ImportProcessor
{
    protected $pdo;
    protected $userGateway;
    protected $studentGateway;
    protected $applicationFormGateway;
    protected $settingGateway;
    protected $securityService;

    public function __construct(
        Connection $pdo,
        UserGateway $userGateway,
        StudentGateway $studentGateway,
        ApplicationFormGateway $applicationFormGateway,
        SettingGateway $settingGateway,
        SecurityService $securityService
    ) {
        $this->pdo = $pdo;
        $this->userGateway = $userGateway;
        $this->studentGateway = $studentGateway;
        $this->applicationFormGateway = $applicationFormGateway;
        $this->settingGateway = $settingGateway;
        $this->securityService = $securityService;
    }

    /**
     * Check for potential duplicate students based on name, DOB, and previous school
     * @param array $studentData Student data from transfer package
     * @return array Array of potential matches with match type and details
     */
    public function checkDuplicates(array $studentData): array
    {
        $duplicates = [];
        
        // Extract search criteria
        $surname = $studentData['surname'] ?? '';
        $firstName = $studentData['firstName'] ?? '';
        $dob = $studentData['dob'] ?? '';
        $previousSchool = $studentData['schoolNameFrom'] ?? '';
        
        if (empty($surname) || empty($firstName) || empty($dob)) {
            return $duplicates;
        }

        // Check by name and DOB
        $sql = "SELECT gibbonPerson.gibbonPersonID, surname, firstName, dob, previousSchool,
                       gibbonStudentEnrolment.gibbonSchoolYearID,
                       (SELECT name FROM gibbonSchoolYear WHERE gibbonSchoolYearID=gibbonStudentEnrolment.gibbonSchoolYearID) as schoolYear
                FROM gibbonPerson 
                JOIN gibbonStudentEnrolment ON gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID 
                WHERE (surname=:surname AND firstName=:firstName)
                OR (dob=:dob AND (surname=:surname OR firstName=:firstName))";
        
        $result = $this->pdo->select($sql, [
            'surname' => $surname,
            'firstName' => $firstName,
            'dob' => $dob
        ]);

        if (!empty($result)) {
            foreach ($result as $match) {
                $duplicates[] = $this->formatDuplicateMatch($match, $studentData);
            }
        }

        // Check previous school enrollment
        if (!empty($previousSchool)) {
            $sql = "SELECT DISTINCT gibbonPerson.gibbonPersonID, surname, firstName, dob, previousSchool,
                           gibbonStudentEnrolment.gibbonSchoolYearID,
                           (SELECT name FROM gibbonSchoolYear WHERE gibbonSchoolYearID=gibbonStudentEnrolment.gibbonSchoolYearID) as schoolYear
                    FROM gibbonPerson 
                    JOIN gibbonStudentEnrolment ON gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID 
                    WHERE gibbonPerson.previousSchool=:previousSchool";
            
            $result = $this->pdo->select($sql, ['previousSchool' => $previousSchool]);
            
            if (!empty($result)) {
                foreach ($result as $match) {
                    // Skip if already found by name/dob
                    if (array_filter($duplicates, fn($d) => $d['gibbonPersonID'] == $match['gibbonPersonID'])) {
                        continue;
                    }
                    
                    $duplicates[] = $this->formatDuplicateMatch($match, $studentData);
                }
            }
        }

        return $duplicates;
    }

    /**
     * Format duplicate match data for display
     * 
     * @param array $match Raw match data from database
     * @param array $studentData Original student data being imported
     * @return array Formatted match data safe for display
     */
    protected function formatDuplicateMatch(array $match, array $studentData): array
    {
        // Create matchType string by comparing with import data
        $matchTypes = [];
        if ($match['surname'] == ($studentData['surname'] ?? '') && $match['firstName'] == ($studentData['firstName'] ?? '')) {
            $matchTypes[] = 'name';
        }
        if ($match['dob'] == ($studentData['dob'] ?? '')) {
            $matchTypes[] = 'dob';
        }
        if (!empty($match['previousSchool']) && $match['previousSchool'] == ($studentData['schoolNameFrom'] ?? '')) {
            $matchTypes[] = 'previous school';
        }

        return [
            'gibbonPersonID' => strval($match['gibbonPersonID']),
            'firstName' => strval($match['firstName'] ?? ''),
            'surname' => strval($match['surname'] ?? ''),
            'name' => Format::name('', $match['firstName'] ?? '', $match['surname'] ?? '', 'Student', false, false),
            'dob' => Format::date($match['dob'] ?? ''),
            'matchType' => implode(',', $matchTypes),
            'schoolYear' => strval($match['schoolYear'] ?? '')
        ];
    }

    /**
     * Format student data for preview display
     * Ensures all values are properly stringified
     * 
     * @param array $studentData Raw student data from transfer package
     * @return array Formatted student data safe for display
     */
    public function formatStudentDataForPreview(array $studentData): array
    {
        $formatted = [];
        
        // Format personal information
        $formatted['firstName'] = strval($studentData['firstName'] ?? '');
        $formatted['surname'] = strval($studentData['surname'] ?? '');
        $formatted['preferredName'] = strval($studentData['preferredName'] ?? '');
        $formatted['officialName'] = strval($studentData['officialName'] ?? '');
        $formatted['nameInCharacters'] = strval($studentData['nameInCharacters'] ?? '');
        $formatted['dob'] = !empty($studentData['dob']) ? Format::date($studentData['dob']) : '';
        $formatted['gender'] = strval($studentData['gender'] ?? '');
        
        // Format contact information
        $formatted['email'] = strval($studentData['email'] ?? '');
        $formatted['phone'] = strval($studentData['phone'] ?? '');
        $formatted['address'] = strval($studentData['address'] ?? '');
        
        // Format school information
        $formatted['schoolNameFrom'] = strval($studentData['schoolNameFrom'] ?? '');
        $formatted['schoolYearFrom'] = strval($studentData['schoolYearFrom'] ?? '');
        $formatted['dateStart'] = !empty($studentData['dateStart']) ? Format::date($studentData['dateStart']) : '';
        
        // Format medical information (if present)
        if (isset($studentData['medical'])) {
            $formatted['medical'] = array_map(function($item) {
                return array_map('strval', $item);
            }, $studentData['medical']);
        }
        
        // Format family information (if present)
        if (isset($studentData['family'])) {
            $formatted['family'] = array_map(function($member) {
                return array_map('strval', $member);
            }, $studentData['family']);
        }
        
        return $formatted;
    }

    /**
     * Perform a dry run of the import process to validate data
     * No changes are made to the database
     * 
     * @param array $studentData Student data to validate
     * @param array $options Import options (mode, ignoreErrors)
     * @return array Results with any errors or warnings
     */
    public function dryRun(array $studentData, array $options = []): array
    {
        $errors = [];
        $warnings = [];

        // Validate required personal fields
        $requiredPersonal = ['surname', 'firstName', 'officialName', 'gender'];
        foreach ($requiredPersonal as $field) {
            if (empty($studentData['personal'][$field])) {
                $errors[] = __('Required personal field is missing').': '.__($field);
            }
        }

        // Validate email format
        if (!empty($studentData['personal']['email']) && !filter_var($studentData['personal']['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('Invalid email format').': '.$studentData['personal']['email'];
        }

        // Validate date formats
        if (!empty($studentData['personal']['dob'])) {
            try {
                new \DateTime($studentData['personal']['dob']);
            } catch (\Exception $e) {
                $errors[] = __('Invalid date format for date of birth');
            }
        }

        // Validate academic data
        if (empty($studentData['academic']['yearGroup']['id'])) {
            $errors[] = __('Year Group is required');
        } else {
            // Check if year group exists
            $sql = "SELECT COUNT(*) FROM gibbonYearGroup WHERE gibbonYearGroupID=:yearGroupID";
            $result = $this->pdo->selectOne($sql, ['yearGroupID' => $studentData['academic']['yearGroup']['id']]);
            if (empty($result['COUNT(*)'])) {
                $errors[] = __('Invalid Year Group ID').': '.$studentData['academic']['yearGroup']['id'];
            }
        }

        // Validate form group if provided
        if (!empty($studentData['academic']['formGroup']['id'])) {
            $sql = "SELECT COUNT(*) FROM gibbonFormGroup WHERE gibbonFormGroupID=:formGroupID";
            $result = $this->pdo->selectOne($sql, ['formGroupID' => $studentData['academic']['formGroup']['id']]);
            if (empty($result['COUNT(*)'])) {
                $errors[] = __('Invalid Form Group ID').': '.$studentData['academic']['formGroup']['id'];
            }
        }

        // Check family data
        if (!empty($studentData['family'])) {
            foreach ($studentData['family'] as $index => $member) {
                if (empty($member['adult']['email'])) {
                    $warnings[] = __('Family member').' '.($index + 1).': '.__('Email address is empty');
                } elseif (!filter_var($member['adult']['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = __('Family member').' '.($index + 1).': '.__('Invalid email format');
                }
            }
        }

        // Check attendance records if present
        if (!empty($studentData['attendance'])) {
            foreach ($studentData['attendance'] as $index => $record) {
                if (empty($record['date'])) {
                    $errors[] = __('Attendance record').' '.($index + 1).': '.__('Date is required');
                } else {
                    try {
                        new \DateTime($record['date']);
                    } catch (\Exception $e) {
                        $errors[] = __('Attendance record').' '.($index + 1).': '.__('Invalid date format');
                    }
                }
            }
        }

        // Check medical data
        if (!empty($studentData['medical']['conditions'])) {
            foreach ($studentData['medical']['conditions'] as $index => $condition) {
                if (empty($condition)) {
                    $warnings[] = __('Medical condition').' '.($index + 1).': '.__('Empty condition record');
                }
            }
        }

        // Check for duplicates
        $duplicates = $this->checkDuplicates($studentData['personal']);
        if (!empty($duplicates)) {
            foreach ($duplicates as $duplicate) {
                $warnings[] = __('Possible duplicate').': '.$duplicate['name'].' ('.__('matched by').' '.$duplicate['matchType'].')';
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'status' => empty($errors) || ($options['ignoreErrors'] ?? false) ? 'Ready' : 'Failed'
        ];
    }

    /**
     * Perform the actual import of student data
     * Creates application form and related records
     * 
     * @param array $studentData Student data to import
     * @param array $options Import options (mode, ignoreErrors)
     * @return array Results with any errors, warnings, and import status
     */
    public function liveRun(array $studentData, array $options = []): array
    {
        $errors = [];
        $warnings = [];
        $imported = 0;

        // First run validation
        $validation = $this->dryRun($studentData, $options);
        if (!empty($validation['errors']) && !($options['ignoreErrors'] ?? false)) {
            return [
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
                'imported' => 0,
                'status' => 'Failed'
            ];
        }

        try {
            // Begin transaction
            $this->pdo->getConnection()->beginTransaction();

            // Get current school year
            $sql = "SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status='Current'";
            $currentSchoolYear = $this->pdo->selectOne($sql);
            
            if (empty($currentSchoolYear['gibbonSchoolYearID'])) {
                throw new \Exception(__('Current school year could not be determined'));
            }

            // Create application form
            $data = [
                'gibbonSchoolYearID' => $currentSchoolYear['gibbonSchoolYearID'],
                'surname' => $studentData['personal']['surname'],
                'firstName' => $studentData['personal']['firstName'],
                'preferredName' => $studentData['personal']['preferredName'] ?? $studentData['personal']['firstName'],
                'officialName' => $studentData['personal']['officialName'],
                'nameInCharacters' => $studentData['personal']['nameInCharacters'] ?? '',
                'gender' => $studentData['personal']['gender'],
                'dob' => $studentData['personal']['dob'],
                'email' => $studentData['personal']['email'],
                'phone1' => $studentData['personal']['phone1'] ?? '',
                'phone2' => $studentData['personal']['phone2'] ?? '',
                'countryOfBirth' => $studentData['personal']['countryOfBirth'] ?? '',
                'citizenship1' => $studentData['personal']['citizenship1'] ?? '',
                'citizenship2' => $studentData['personal']['citizenship2'] ?? '',
                'transport' => 'N',
                'schoolDate' => date('Y-m-d'),
                'dateStart' => $studentData['academic']['dateStart'] ?? '',
                'gibbonYearGroupID' => $studentData['academic']['yearGroup']['id'],
                'gibbonFormGroupID' => $studentData['academic']['formGroup']['id'] ?? null,
                'previousSchool' => $studentData['schoolNameFrom'] ?? '',
                'status' => 'Pending',
                'priority' => '0',
                'milestones' => 'Transfer Import '.date('Y-m-d H:i:s')
            ];

            $fields = implode(',', array_keys($data));
            $values = ':'.implode(',:', array_keys($data));
            $sql = "INSERT INTO gibbonApplicationForm ($fields) VALUES ($values)";
            $inserted = $this->pdo->insert($sql, $data);

            if (!$inserted) {
                throw new \Exception(__('Failed to create application form'));
            }

            $applicationFormID = $this->pdo->getConnection()->lastInsertID();

            // Add medical conditions
            if (!empty($studentData['medical']['conditions'])) {
                foreach ($studentData['medical']['conditions'] as $condition) {
                    $medicalData = [
                        'gibbonApplicationFormID' => $applicationFormID,
                        'name' => $condition,
                        'alertLevel' => 'Low'
                    ];
                    
                    $fields = implode(',', array_keys($medicalData));
                    $values = ':'.implode(',:', array_keys($medicalData));
                    $sql = "INSERT INTO gibbonApplicationFormMedical ($fields) VALUES ($values)";
                    $this->pdo->insert($sql, $medicalData);
                }
            }

            // Add family members
            if (!empty($studentData['family'])) {
                foreach ($studentData['family'] as $member) {
                    if (empty($member['adult']['email'])) continue;

                    $familyData = [
                        'gibbonApplicationFormID' => $applicationFormID,
                        'title' => $member['adult']['title'] ?? '',
                        'surname' => $member['adult']['surname'] ?? '',
                        'firstName' => $member['adult']['preferredName'] ?? '',
                        'relationship' => $member['relationship'] ?? '',
                        'phone' => $member['adult']['phone1'] ?? '',
                        'email' => $member['adult']['email'],
                        'address' => $member['homeAddress'] ?? '',
                        'languageFirst' => $member['languageHomePrimary'] ?? '',
                        'languageSecond' => $member['languageHomeSecondary'] ?? ''
                    ];

                    $fields = implode(',', array_keys($familyData));
                    $values = ':'.implode(',:', array_keys($familyData));
                    $sql = "INSERT INTO gibbonApplicationFormFamily ($fields) VALUES ($values)";
                    $this->pdo->insert($sql, $familyData);
                }
            }

            // Process attachments if any
            if (!empty($studentData['attachments'])) {
                $attachmentDir = $this->settingGateway->getSettingByScope('System Admin', 'attachmentPath');
                if (empty($attachmentDir)) {
                    $attachmentDir = '/uploads/applications/';
                }

                foreach ($studentData['attachments'] as $attachment) {
                    // Copy file to permanent storage
                    $sourcePath = $attachment['path'];
                    $targetPath = $attachmentDir . basename($sourcePath);
                    
                    if (copy($sourcePath, $targetPath)) {
                        $fileData = [
                            'gibbonApplicationFormID' => $applicationFormID,
                            'name' => $attachment['name'],
                            'path' => $targetPath,
                            'timestamp' => date('Y-m-d H:i:s')
                        ];

                        $fields = implode(',', array_keys($fileData));
                        $values = ':'.implode(',:', array_keys($fileData));
                        $sql = "INSERT INTO gibbonFileUpload ($fields) VALUES ($values)";
                        $this->pdo->insert($sql, $fileData);
                    } else {
                        $warnings[] = __('Failed to copy attachment').': '.$attachment['name'];
                    }
                }
            }

            // Commit transaction
            $this->pdo->getConnection()->commit();
            $imported = 1;

        } catch (\Exception $e) {
            // Rollback on error
            $this->pdo->getConnection()->rollBack();
            $errors[] = $e->getMessage();
        }

        return [
            'errors' => $errors,
            'warnings' => array_merge($validation['warnings'] ?? [], $warnings),
            'imported' => $imported,
            'status' => empty($errors) || ($options['ignoreErrors'] ?? false) ? 'Complete' : 'Failed'
        ];
    }

    /**
     * Process a student transfer package
     * 
     * @param string $studentData JSON string of student data
     * @param string $studentTransferImportID
     * @param string $packagePasscode Package passcode
     * @return string|false
     */
    public function createApplicationForm($studentData, $studentTransferImportID, $packagePasscode)
    {
        try {
            // Decode student data if it's a string
            if (is_string($studentData)) {
                $studentData = json_decode($studentData, true);
            }

            if (empty($studentData) || !is_array($studentData)) {
                throw new \Exception('Invalid student data format');
            }

            // Validate required data structure
            $required = [
                'personal' => ['surname', 'firstName', 'dob', 'email'],
                'academic' => ['yearGroup'],
                'medical' => [],
                'family' => []
            ];

            foreach ($required as $section => $fields) {
                if (!isset($studentData[$section])) {
                    throw new \Exception("Missing required section: {$section}");
                }
                foreach ($fields as $field) {
                    if (!isset($studentData[$section][$field])) {
                        throw new \Exception("Missing required field: {$section}.{$field}");
                    }
                }
            }

            // Begin transaction
            $this->pdo->getConnection()->beginTransaction();

            // Create application form record
            $data = [
                'gibbonSchoolYearID' => $this->settingGateway->getSettingByScope('System', 'gibbonSchoolYearIDCurrent'),
                'surname' => strval($studentData['personal']['surname']),
                'firstName' => strval($studentData['personal']['firstName']),
                'dob' => strval($studentData['personal']['dob']),
                'email' => strval($studentData['personal']['email']),
                'schoolName' => strval($studentData['academic']['previousSchools'][0]['name'] ?? ''),
                'dateStart' => date('Y-m-d'),
                'gibbonYearGroupIDEntry' => strval($studentData['academic']['yearGroup']),
                'studentTransferImportID' => strval($studentTransferImportID),
                'packagePasscode' => password_hash($packagePasscode, PASSWORD_DEFAULT),
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $fields = implode(',', array_keys($data));
            $values = ':'.implode(',:', array_keys($data));
            $sql = "INSERT INTO gibbonApplicationForm ($fields) VALUES ($values)";
            
            $result = $this->pdo->insert($sql, $data);

            $applicationFormID = $this->pdo->getConnection()->lastInsertID();
            if (empty($applicationFormID)) {
                throw new \Exception('Failed to create application form');
            }

            // Process medical conditions
            if (!empty($studentData['medical'])) {
                foreach ($studentData['medical'] as $condition) {
                    if (empty($condition['name']) || empty($condition['details'])) continue;
                    
                    $medicalData = [
                        'gibbonApplicationFormID' => $applicationFormID,
                        'name' => strval($condition['name']),
                        'details' => strval($condition['details'])
                    ];
                    
                    $fields = implode(',', array_keys($medicalData));
                    $values = ':'.implode(',:', array_keys($medicalData));
                    $sql = "INSERT INTO gibbonApplicationFormMedical ($fields) VALUES ($values)";
                    
                    $this->pdo->insert($sql, $medicalData);
                }
            }

            // Process family members
            if (!empty($studentData['family'])) {
                foreach ($studentData['family'] as $member) {
                    if (empty($member['adult']['email'])) continue;
                    
                    $familyData = [
                        'gibbonApplicationFormID' => $applicationFormID,
                        'title' => strval($member['adult']['title']),
                        'surname' => strval($member['adult']['surname']),
                        'firstName' => strval($member['adult']['firstName']),
                        'relation' => strval($member['relation']),
                        'phone' => strval($member['adult']['phone'] ?? ''),
                        'email' => strval($member['adult']['email'])
                    ];

                    $fields = implode(',', array_keys($familyData));
                    $values = ':'.implode(',:', array_keys($familyData));
                    $sql = "INSERT INTO gibbonApplicationFormFamily ($fields) VALUES ($values)";
                    
                    $this->pdo->insert($sql, $familyData);
                }
            }

            $this->pdo->getConnection()->commit();
            return $applicationFormID;

        } catch (\Exception $e) {
            $this->pdo->getConnection()->rollBack();
            error_log("[Student Transfer] Application form creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate transfer package metadata
     * Ensures all required fields are present and properly formatted
     *
     * @param array $metadata Metadata from transfer package
     * @throws \RuntimeException if metadata is invalid
     */
    protected function validateMetadata(array $metadata)
    {
        // Required fields in metadata.json
        $requiredFields = [
            'timestamp' => 'Timestamp',
            'source' => 'Source school name',
            'version' => 'Package version',
            'publicKey' => 'Source school public key'
        ];

        // Check each required field
        foreach ($requiredFields as $field => $label) {
            if (!isset($metadata[$field]) || empty($metadata[$field])) {
                throw new \RuntimeException("Invalid metadata: missing {$field}");
            }
        }

        // Validate timestamp format
        if (!strtotime($metadata['timestamp'])) {
            throw new \RuntimeException('Invalid metadata: malformed timestamp');
        }

        // Validate version format (should be x.x.xx)
        if (!preg_match('/^\d+\.\d+\.\d+$/', $metadata['version'])) {
            throw new \RuntimeException('Invalid metadata: incorrect version format');
        }

        // Validate public key format (should be PEM format)
        if (!str_contains($metadata['publicKey'], '-----BEGIN PUBLIC KEY-----')) {
            throw new \RuntimeException('Invalid metadata: incorrect public key format');
        }
    }

    /**
     * Process the import of a student transfer package
     * 
     * @param string $tempDir Directory containing extracted files
     * @param string $packagePasscode Package passcode
     * @return array|false Import data or false on failure
     */
    public function processImport($tempDir, $packagePasscode)
    {
        try {
            // Check if the required files exist
            $requiredFiles = [
                'metadata.json',
                'student_data.json',
                'manifest.json'
            ];

            foreach ($requiredFiles as $file) {
                if (!file_exists($tempDir . '/' . $file)) {
                    throw new \Exception("Required file missing: {$file}");
                }
            }

            // First decrypt and read metadata to get the public key
            $metadata = $this->securityService->decryptJsonFile($tempDir . '/metadata.json', $packagePasscode);
            if (empty($metadata)) {
                throw new \Exception('Failed to decrypt metadata.json');
            }

            // Validate metadata structure
            if (empty($metadata['publicKey'])) {
                throw new \Exception('Invalid metadata: missing publicKey');
            }

            $this->validateMetadata($metadata);

            // Decrypt and read the remaining files
            $studentData = $this->securityService->decryptJsonFile($tempDir . '/student_data.json', $packagePasscode);
            if (empty($studentData)) {
                throw new \Exception('Failed to decrypt student_data.json');
            }

            $manifest = $this->securityService->decryptJsonFile($tempDir . '/manifest.json', $packagePasscode);
            if (empty($manifest)) {
                throw new \Exception('Failed to decrypt manifest.json');
            }

            // Verify file checksums and signatures
            if (empty($manifest['files'])) {
                throw new \Exception('Invalid manifest: missing files section');
            }

            foreach ($manifest['files'] as $file => $info) {
                if (!file_exists($tempDir . '/' . $file)) {
                    throw new \Exception("File missing: {$file}");
                }

                // Verify checksum
                if (empty($info['checksum'])) {
                    throw new \Exception("Missing checksum for file: {$file}");
                }
                
                $actualChecksum = hash_file('sha256', $tempDir . '/' . $file);
                if ($actualChecksum !== $info['checksum']) {
                    throw new \Exception("Checksum mismatch for file: {$file}");
                }

                // Verify signature using public key from metadata
                if (empty($info['signature'])) {
                    throw new \Exception("Missing signature for file: {$file}");
                }

                if (!$this->securityService->verifyDigitalSignature($tempDir . '/' . $file, $info['signature'], $metadata['publicKey'])) {
                    throw new \Exception("Invalid signature for file: {$file}");
                }
            }

            return [
                'metadata' => $metadata,
                'studentData' => $studentData
            ];

        } catch (\Exception $e) {
            error_log("[Student Transfer] Import processing failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate file checksums against manifest
     * 
     * @param string $tempDir Directory containing files
     * @param array $manifest Manifest data with checksums
     * @return bool True if all checksums match
     */
    private function validateChecksums(string $tempDir, array $manifest): bool
    {
        if (!isset($manifest['checksums']) || !is_array($manifest['checksums'])) {
            return false;
        }

        foreach ($manifest['checksums'] as $file => $expectedHash) {
            $filePath = $tempDir . '/' . $file;
            if (!file_exists($filePath)) {
                error_log("[Student Transfer] File missing for checksum validation: " . $file);
                return false;
            }

            $actualHash = hash_file('sha256', $filePath);
            if ($actualHash !== $expectedHash) {
                error_log("[Student Transfer] Checksum mismatch for file: " . $file);
                return false;
            }
        }

        return true;
    }
}

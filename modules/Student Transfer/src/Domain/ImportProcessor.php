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

            $sql = "INSERT INTO gibbonApplicationForm (
                    gibbonSchoolYearID, surname, firstName, dob, email,
                    schoolName, dateStart, gibbonYearGroupIDEntry,
                    studentTransferImportID, packagePasscode, timestamp
                ) VALUES (
                    :gibbonSchoolYearID, :surname, :firstName, :dob, :email,
                    :schoolName, :dateStart, :gibbonYearGroupIDEntry,
                    :studentTransferImportID, :packagePasscode, :timestamp
                )";
            
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
                        'gibbonApplicationFormID' => strval($applicationFormID),
                        'name' => strval($condition['name']),
                        'details' => strval($condition['details'])
                    ];
                    
                    $sql = "INSERT INTO gibbonApplicationFormMedical (
                            gibbonApplicationFormID, name, details
                        ) VALUES (
                            :gibbonApplicationFormID, :name, :details
                        )";
                    
                    $this->pdo->insert($sql, $medicalData);
                }
            }

            // Process family members
            if (!empty($studentData['family'])) {
                foreach ($studentData['family'] as $member) {
                    if (empty($member['relation']) || empty($member['title']) || 
                        empty($member['firstName']) || empty($member['surname'])) continue;
                    
                    $familyData = [
                        'gibbonApplicationFormID' => strval($applicationFormID),
                        'title' => strval($member['title']),
                        'surname' => strval($member['surname']),
                        'firstName' => strval($member['firstName']),
                        'relation' => strval($member['relation']),
                        'phone' => strval($member['phone'] ?? ''),
                        'email' => strval($member['email'] ?? '')
                    ];
                    
                    $sql = "INSERT INTO gibbonApplicationFormFamily (
                            gibbonApplicationFormID, title, surname, firstName,
                            relation, phone, email
                        ) VALUES (
                            :gibbonApplicationFormID, :title, :surname, :firstName,
                            :relation, :phone, :email
                        )";
                    
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

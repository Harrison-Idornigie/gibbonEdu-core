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
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Services\Format;
use PDO;

/**
 * Import Processor
 *
 * Handles duplicate checking and application form creation
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ImportProcessor extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonStudentTransferImport';
    private static $primaryKey = 'gibbonStudentTransferImportID';
    private static $searchableColumns = [''];

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
        parent::__construct($pdo);
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

        if (empty($studentData['personal'])) {
            return $duplicates;
        }

        // Check by name and DOB
        $sql = "SELECT gibbonPerson.gibbonPersonID, surname, firstName, dob, schoolName1,
                       gibbonStudentEnrolment.gibbonSchoolYearID,
                       (SELECT name FROM gibbonSchoolYear WHERE gibbonSchoolYearID=gibbonStudentEnrolment.gibbonSchoolYearID) as schoolYear
                FROM gibbonPerson 
                LEFT JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
                WHERE surname=:surname 
                AND firstName=:firstName";

        $result = $this->pdo->select($sql, [
            'surname' => $studentData['personal']['surname'],
            'firstName' => $studentData['personal']['firstName']
        ]);

        if ($result && $result->rowCount() > 0) {
            foreach ($result as $match) {
                $duplicates[] = $this->formatDuplicateMatch($match, $studentData);
            }
        }

        // Check previous school enrollment
        $previousSchool = $studentData['schoolNameFrom'] ?? '';
        if (!empty($previousSchool)) {
            $sql = "SELECT DISTINCT gibbonPerson.gibbonPersonID, surname, firstName, dob, schoolName1,
                           gibbonStudentEnrolment.gibbonSchoolYearID,
                           (SELECT name FROM gibbonSchoolYear WHERE gibbonSchoolYearID=gibbonStudentEnrolment.gibbonSchoolYearID) as schoolYear
                    FROM gibbonPerson 
                    LEFT JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
                    WHERE gibbonPerson.schoolName1=:previousSchool";
            
            $result = $this->pdo->select($sql, ['previousSchool' => $previousSchool]);
            
            if ($result && $result->rowCount() > 0) {
                foreach ($result as $match) {
                    $duplicates[] = $this->formatDuplicateMatch($match, $studentData);
                }
            }
        }

        return array_unique($duplicates, SORT_REGULAR);
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
        $matchTypes = [];

        if ($match['surname'] == ($studentData['personal']['surname'] ?? '')) {
            $matchTypes[] = 'surname';
        }
        if ($match['firstName'] == ($studentData['personal']['firstName'] ?? '')) {
            $matchTypes[] = 'first name';
        }
        if ($match['dob'] == ($studentData['personal']['dob'] ?? '')) {
            $matchTypes[] = 'dob';
        }
        if (!empty($match['schoolName1']) && $match['schoolName1'] == ($studentData['schoolNameFrom'] ?? '')) {
            $matchTypes[] = 'previous school';
        }

        return [
            'gibbonPersonID' => $match['gibbonPersonID'],
            'name' => $match['firstName'].' '.$match['surname'],
            'dob' => $match['dob'],
            'schoolYear' => $match['schoolYear'] ?? '',
            'matchTypes' => $matchTypes,
            'matchCount' => count($matchTypes)
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
        $formatted['firstName'] = strval($studentData['personal']['firstName'] ?? '');
        $formatted['surname'] = strval($studentData['personal']['surname'] ?? '');
        $formatted['preferredName'] = strval($studentData['personal']['preferredName'] ?? '');
        $formatted['officialName'] = strval($studentData['personal']['officialName'] ?? '');
        $formatted['nameInCharacters'] = strval($studentData['personal']['nameInCharacters'] ?? '');
        $formatted['dob'] = !empty($studentData['personal']['dob']) ? Format::date($studentData['personal']['dob']) : '';
        $formatted['gender'] = strval($studentData['personal']['gender'] ?? '');
        
        // Format contact information
        $formatted['email'] = strval($studentData['personal']['email'] ?? '');
        $formatted['phone'] = strval($studentData['personal']['phone'] ?? '');
        $formatted['address'] = strval($studentData['personal']['address'] ?? '');
        
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
                $warnings[] = __('Possible duplicate').': '.$duplicate['name'].' ('.__('matched by').' '.$duplicate['matchTypes'][0].')';
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

        try {
            // Check if student already exists in the school
            $sql = "SELECT p.*, sy.name as schoolYear, yg.name as yearGroup 
                   FROM gibbonPerson AS p 
                   JOIN gibbonStudentEnrolment AS se ON se.gibbonPersonID = p.gibbonPersonID 
                   JOIN gibbonSchoolYear AS sy ON sy.gibbonSchoolYearID = se.gibbonSchoolYearID
                   JOIN gibbonYearGroup AS yg ON yg.gibbonYearGroupID = se.gibbonYearGroupID
                   WHERE p.surname = :surname 
                   AND p.firstName = :firstName
                   AND p.dob = :dob
                   AND p.status = 'Full'
                   AND sy.status = 'Current'";

            $result = $this->pdo->select($sql, [
                'surname' => $studentData['personal']['surname'],
                'firstName' => $studentData['personal']['firstName'],
                'dob' => $studentData['personal']['dob']
            ]);

            if ($result->rowCount() > 0) {
                $existingStudent = $result->fetch();
                throw new \Exception(sprintf(
                    'This student already exists in the school. %s %s is currently enrolled in %s (%s). Please check existing student records before proceeding.',
                    $existingStudent['firstName'],
                    $existingStudent['surname'],
                    $existingStudent['yearGroup'],
                    $existingStudent['schoolYear']
                ));
            }

            // Check if student was previously imported
            $sql = "SELECT ti.*, p.title, p.preferredName, p.surname, p.username 
                   FROM gibbonStudentTransferImport AS ti 
                   JOIN gibbonPerson AS p ON p.gibbonPersonID = ti.gibbonPersonIDCreated 
                   WHERE ti.status = 'Complete' 
                   AND ti.studentData LIKE :searchKey";

            $searchKey = '%"surname":"' . $studentData['personal']['surname'] . 
                        '","firstName":"' . $studentData['personal']['firstName'] . 
                        '","dob":"' . $studentData['personal']['dob'] . '"%';

            $result = $this->pdo->select($sql, ['searchKey' => $searchKey]);

            if ($result->rowCount() > 0) {
                $previousImport = $result->fetch();
                $staffName = Format::name($previousImport['title'], $previousImport['preferredName'], $previousImport['surname'], 'Staff');
                throw new \Exception(sprintf(
                    'This student appears to have already been imported by %s on %s. Please check the import history before proceeding.',
                    $staffName,
                    Format::date($previousImport['timestampCreated'])
                ));
            }

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

            // Begin transaction
            $this->pdo->getConnection()->beginTransaction();

            // Get current school year
            $sql = "SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status='Current' LIMIT 1";
            $result = $this->pdo->select($sql);
            
            if ($result->rowCount() != 1) {
                throw new \Exception('Current school year could not be determined');
            }
            $currentSchoolYear = $result->fetch();

            // Create application form record
            $data = [
                'gibbonSchoolYearIDEntry' => $currentSchoolYear['gibbonSchoolYearID'],
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
                'dateStart' => $studentData['academic']['dateStart'] ?? '',
                'gibbonYearGroupIDEntry' => $studentData['academic']['yearGroup']['id'],
                'gibbonFormGroupID' => $studentData['academic']['formGroup']['id'] ?? null,
                'schoolName1' => $studentData['schoolNameFrom'] ?? '',
                'schoolAddress1' => $studentData['schoolAddressFrom'] ?? '',
                'status' => 'Pending',
                'priority' => '0',
                'milestones' => 'Transfer Import '.date('Y-m-d H:i:s')
            ];

            // Add parent 1 information
            if (!empty($studentData['family']['parent1'])) {
                $data['parent1title'] = $studentData['family']['parent1']['title'] ?? '';
                $data['parent1surname'] = $studentData['family']['parent1']['surname'] ?? '';
                $data['parent1firstName'] = $studentData['family']['parent1']['firstName'] ?? '';
                $data['parent1preferredName'] = $studentData['family']['parent1']['preferredName'] ?? '';
                $data['parent1officialName'] = $studentData['family']['parent1']['officialName'] ?? '';
                $data['parent1nameInCharacters'] = $studentData['family']['parent1']['nameInCharacters'] ?? '';
                $data['parent1gender'] = $studentData['family']['parent1']['gender'] ?? '';
                $data['parent1relationship'] = $studentData['family']['parent1']['relationship'] ?? '';
                $data['parent1languageFirst'] = $studentData['family']['parent1']['languageFirst'] ?? '';
                $data['parent1languageSecond'] = $studentData['family']['parent1']['languageSecond'] ?? '';
                $data['parent1email'] = $studentData['family']['parent1']['email'] ?? '';
                $data['parent1phone1'] = $studentData['family']['parent1']['phone1'] ?? '';
                $data['parent1phone2'] = $studentData['family']['parent1']['phone2'] ?? '';
                $data['parent1profession'] = $studentData['family']['parent1']['profession'] ?? '';
                $data['parent1employer'] = $studentData['family']['parent1']['employer'] ?? '';
            }

            // Add parent 2 information
            if (!empty($studentData['family']['parent2'])) {
                $data['parent2title'] = $studentData['family']['parent2']['title'] ?? '';
                $data['parent2surname'] = $studentData['family']['parent2']['surname'] ?? '';
                $data['parent2firstName'] = $studentData['family']['parent2']['firstName'] ?? '';
                $data['parent2preferredName'] = $studentData['family']['parent2']['preferredName'] ?? '';
                $data['parent2officialName'] = $studentData['family']['parent2']['officialName'] ?? '';
                $data['parent2nameInCharacters'] = $studentData['family']['parent2']['nameInCharacters'] ?? '';
                $data['parent2gender'] = $studentData['family']['parent2']['gender'] ?? '';
                $data['parent2relationship'] = $studentData['family']['parent2']['relationship'] ?? '';
                $data['parent2languageFirst'] = $studentData['family']['parent2']['languageFirst'] ?? '';
                $data['parent2languageSecond'] = $studentData['family']['parent2']['languageSecond'] ?? '';
                $data['parent2email'] = $studentData['family']['parent2']['email'] ?? '';
                $data['parent2phone1'] = $studentData['family']['parent2']['phone1'] ?? '';
                $data['parent2phone2'] = $studentData['family']['parent2']['phone2'] ?? '';
                $data['parent2profession'] = $studentData['family']['parent2']['profession'] ?? '';
                $data['parent2employer'] = $studentData['family']['parent2']['employer'] ?? '';
            }

            // Add medical information if available
            if (!empty($studentData['medical']['conditions'])) {
                $data['medicalInformation'] = implode("\n", array_map(function($condition) {
                    return $condition['name'] . ': ' . ($condition['details'] ?? 'No details provided');
                }, $studentData['medical']['conditions']));
            }

            $fields = implode(',', array_keys($data));
            $values = ':'.implode(',:', array_keys($data));
            $sql = "INSERT INTO gibbonApplicationForm ($fields) VALUES ($values)";
            
            $result = $this->pdo->insert($sql, $data);

            if (!$result) {
                throw new \Exception(__('Failed to create application form'));
            }

            $applicationFormID = $this->pdo->getConnection()->lastInsertID();

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
                'gibbonSchoolYearIDEntry' => $this->settingGateway->getSettingByScope('System', 'gibbonSchoolYearIDCurrent'),
                'surname' => strval($studentData['personal']['surname']),
                'firstName' => strval($studentData['personal']['firstName']),
                'dob' => strval($studentData['personal']['dob']),
                'email' => strval($studentData['personal']['email']),
                'schoolName1' => strval($studentData['academic']['previousSchools'][0]['name'] ?? ''),
                'schoolAddress1' => strval($studentData['academic']['previousSchools'][0]['address'] ?? ''),
                'dateStart' => date('Y-m-d'),
                'gibbonYearGroupIDEntry' => strval($studentData['academic']['yearGroup']),
                'studentTransferImportID' => strval($studentTransferImportID),
                'packagePasscode' => password_hash($packagePasscode, PASSWORD_DEFAULT),
                'timestamp' => date('Y-m-d H:i:s')
            ];

            // Add parent 1 information
            if (!empty($studentData['family']['parent1'])) {
                $data['parent1title'] = $studentData['family']['parent1']['title'] ?? '';
                $data['parent1surname'] = $studentData['family']['parent1']['surname'] ?? '';
                $data['parent1firstName'] = $studentData['family']['parent1']['firstName'] ?? '';
                $data['parent1preferredName'] = $studentData['family']['parent1']['preferredName'] ?? '';
                $data['parent1officialName'] = $studentData['family']['parent1']['officialName'] ?? '';
                $data['parent1nameInCharacters'] = $studentData['family']['parent1']['nameInCharacters'] ?? '';
                $data['parent1gender'] = $studentData['family']['parent1']['gender'] ?? '';
                $data['parent1relationship'] = $studentData['family']['parent1']['relationship'] ?? '';
                $data['parent1languageFirst'] = $studentData['family']['parent1']['languageFirst'] ?? '';
                $data['parent1languageSecond'] = $studentData['family']['parent1']['languageSecond'] ?? '';
                $data['parent1email'] = $studentData['family']['parent1']['email'] ?? '';
                $data['parent1phone1'] = $studentData['family']['parent1']['phone1'] ?? '';
                $data['parent1phone2'] = $studentData['family']['parent1']['phone2'] ?? '';
                $data['parent1profession'] = $studentData['family']['parent1']['profession'] ?? '';
                $data['parent1employer'] = $studentData['family']['parent1']['employer'] ?? '';
            }

            // Add parent 2 information
            if (!empty($studentData['family']['parent2'])) {
                $data['parent2title'] = $studentData['family']['parent2']['title'] ?? '';
                $data['parent2surname'] = $studentData['family']['parent2']['surname'] ?? '';
                $data['parent2firstName'] = $studentData['family']['parent2']['firstName'] ?? '';
                $data['parent2preferredName'] = $studentData['family']['parent2']['preferredName'] ?? '';
                $data['parent2officialName'] = $studentData['family']['parent2']['officialName'] ?? '';
                $data['parent2nameInCharacters'] = $studentData['family']['parent2']['nameInCharacters'] ?? '';
                $data['parent2gender'] = $studentData['family']['parent2']['gender'] ?? '';
                $data['parent2relationship'] = $studentData['family']['parent2']['relationship'] ?? '';
                $data['parent2languageFirst'] = $studentData['family']['parent2']['languageFirst'] ?? '';
                $data['parent2languageSecond'] = $studentData['family']['parent2']['languageSecond'] ?? '';
                $data['parent2email'] = $studentData['family']['parent2']['email'] ?? '';
                $data['parent2phone1'] = $studentData['family']['parent2']['phone1'] ?? '';
                $data['parent2phone2'] = $studentData['family']['parent2']['phone2'] ?? '';
                $data['parent2profession'] = $studentData['family']['parent2']['profession'] ?? '';
                $data['parent2employer'] = $studentData['family']['parent2']['employer'] ?? '';
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

            $fields = implode(',', array_keys($data));
            $values = ':'.implode(',:', array_keys($data));
            $sql = "INSERT INTO gibbonApplicationForm ($fields) VALUES ($values)";
            
            $result = $this->pdo->insert($sql, $data);

            $applicationFormID = $this->pdo->getConnection()->lastInsertID();
            if (empty($applicationFormID)) {
                throw new \Exception('Failed to create application form');
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
        if (strpos($metadata['publicKey'], '-----BEGIN PUBLIC KEY-----') === false) {
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

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
use Gibbon\Domain\Students\MedicalGateway;
use Gibbon\Domain\Students\FirstAidGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\School\FacilityGateway;
use Gibbon\Domain\System\CustomFieldGateway;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\DataSet;
use Gibbon\Services\Format;
use Gibbon\Session\Session;
use ZipArchive;
use Gibbon\Module\StudentTransfer\Domain\SecurityService;

/**
 * Student Exporter
 *
 * Handles the export of student data for transfer
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class StudentExporter
{
    protected $connection;
    protected $settingGateway;
    protected $studentGateway;
    protected $facilityGateway;
    protected $userGateway;
    protected $customFieldGateway;
    protected $medicalGateway;
    protected $firstAidGateway;
    protected $session;
    protected $securityService;

    /**
     * Create a new StudentExporter instance.
     *
     * @param Connection $connection
     * @param SettingGateway $settingGateway
     * @param StudentGateway $studentGateway
     * @param FacilityGateway $facilityGateway
     * @param UserGateway $userGateway
     * @param CustomFieldGateway $customFieldGateway
     * @param MedicalGateway $medicalGateway
     * @param FirstAidGateway $firstAidGateway
     * @param Session $session
     * @param SecurityService $securityService
     */
    public function __construct(
        Connection $connection,
        SettingGateway $settingGateway,
        StudentGateway $studentGateway,
        FacilityGateway $facilityGateway,
        UserGateway $userGateway,
        CustomFieldGateway $customFieldGateway,
        MedicalGateway $medicalGateway,
        FirstAidGateway $firstAidGateway,
        Session $session,
        SecurityService $securityService
    ) {
        $this->connection = $connection;
        $this->settingGateway = $settingGateway;
        $this->studentGateway = $studentGateway;
        $this->facilityGateway = $facilityGateway;
        $this->userGateway = $userGateway;
        $this->customFieldGateway = $customFieldGateway;
        $this->medicalGateway = $medicalGateway;
        $this->firstAidGateway = $firstAidGateway;
        $this->session = $session;
        $this->securityService = $securityService;
    }

    /**
     * Export student data to ZIP file.
     *
     * @param string $studentID
     * @param string $transferID
     * @param array|null $existingPassword Array containing both hash and plain password
     * @param array|null $existingToken Existing token data to reuse
     * @return array Path to the generated ZIP file and password
     */
    public function exportToZip($studentID, $transferID, $existingPassword = null, $existingToken = null)
    {
        // Check for required PHP extensions
        if (!extension_loaded('zip')) {
            throw new \RuntimeException('PHP ZIP extension is required for student transfer exports.');
        }

        // Get student data
        $data = $this->exportStudentData($studentID);

        // Create temporary directory with proper error handling
        $tempDir = sys_get_temp_dir() . '/student_transfer_' . $transferID;
        if (file_exists($tempDir)) {
            // Clean up any existing temp directory
            $this->cleanupTempDir($tempDir);
        }
        
        if (!mkdir($tempDir, 0755, true)) {
            throw new \RuntimeException('Failed to create temporary directory for export.');
        }

        try {
            // Create student_data.json
            $jsonFile = $tempDir . '/student_data.json';
            if (file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT)) === false) {
                throw new \RuntimeException('Failed to write student data file.');
            }

            // Create metadata.json
            $metadata = [
                'exportTimestamp' => date('Y-m-d H:i:s'),
                'transferID' => $transferID,
                'sourceSchool' => $this->settingGateway->getSettingByScope('System', 'organisationName'),
                'version' => '1.0.0'
            ];
            $metadataFile = $tempDir . '/metadata.json';
            if (file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT)) === false) {
                throw new \RuntimeException('Failed to write metadata file.');
            }

            // Create manifest.json with checksums and digital signatures
            $manifest = [
                'files' => [
                    'student_data.json' => [
                        'checksum' => hash_file('sha256', $jsonFile),
                        'signature' => $this->securityService->createDigitalSignature($jsonFile)
                    ],
                    'metadata.json' => [
                        'checksum' => hash_file('sha256', $metadataFile),
                        'signature' => $this->securityService->createDigitalSignature($metadataFile)
                    ]
                ]
            ];
            $manifestFile = $tempDir . '/manifest.json';
            if (file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT)) === false) {
                throw new \RuntimeException('Failed to write manifest file.');
            }

            // Generate or reuse password
            if ($existingPassword && !empty($existingPassword['plain'])) {
                $password = $existingPassword['plain'];
            } else {
                $password = $this->securityService->generateSecurePassword();
            }
            
            // Create password-protected ZIP file
            $zipFile = $tempDir . '.zip';
            $zip = new ZipArchive();
            $zipResult = $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            
            if ($zipResult !== true) {
                throw new \RuntimeException('Failed to create ZIP archive: ' . $this->getZipErrorMessage($zipResult));
            }

            try {
                // Enable encryption
                if (!$zip->setPassword($password)) {
                    throw new \RuntimeException('Failed to set ZIP password. Your PHP ZIP extension may not support encryption.');
                }
                
                // Add files with encryption
                $zip->setEncryptionName('student_data.json', ZipArchive::EM_AES_256);
                $zip->setEncryptionName('metadata.json', ZipArchive::EM_AES_256);
                $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256);
                
                $zip->addFile($jsonFile, 'student_data.json');
                $zip->addFile($metadataFile, 'metadata.json');
                $zip->addFile($manifestFile, 'manifest.json');

                // Add attachments if any
                if (!empty($data['attachments'])) {
                    $zip->addEmptyDir('attachments');
                    foreach ($data['attachments'] as $attachment) {
                        if (file_exists($attachment['path'])) {
                            $zip->setEncryptionName('attachments/' . basename($attachment['path']), ZipArchive::EM_AES_256);
                            $zip->addFile($attachment['path'], 'attachments/' . basename($attachment['path']));
                            
                            // Add attachment signature to manifest
                            $manifest['files']['attachments/' . basename($attachment['path'])] = [
                                'checksum' => hash_file('sha256', $attachment['path']),
                                'signature' => $this->securityService->createDigitalSignature($attachment['path'])
                            ];
                        }
                    }
                    // Update manifest with attachment signatures
                    file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT));
                    $zip->addFile($manifestFile, 'manifest.json');
                }
            } finally {
                $zip->close();
            }

            // Generate or reuse download token
            if ($existingToken && !empty($existingToken['token']) && !empty($existingToken['expiry'])) {
                $token = $existingToken['token'];
                $expiry = $existingToken['expiry'];
            } else {
                $tokenData = $this->securityService->generatePublicDownloadToken($transferID);
                $token = $tokenData['token'];
                $expiry = $tokenData['expiry'];
            }

            return [
                'path' => $zipFile,
                'password' => $password,
                'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
                'token' => $token,
                'expiry' => $expiry
            ];
        } finally {
            // Clean up temporary files
            $this->cleanupTempDir($tempDir);
        }
    }

    /**
     * Export student data to array format.
     *
     * @param string $studentID
     * @return array
     */
    public function exportStudentData($studentID)
    {
        $data = [
            'personal' => $this->exportPersonalData($studentID),
            'academic' => $this->exportAcademicData($studentID),
            'medical' => $this->exportMedicalData($studentID),
            'family' => $this->exportFamilyData($studentID),
            'custom' => $this->exportCustomFieldData($studentID),
            'attachments' => $this->exportAttachments($studentID),
            'grades' => $this->exportGradesData($studentID),
            'behavior' => $this->exportBehaviorData($studentID),
            'attendance' => $this->exportAttendanceData($studentID)
        ];

        return $data;
    }

    /**
     * Export personal data.
     *
     * @param string $studentID
     * @return array
     */
    protected function exportPersonalData($studentID)
    {
        $student = $this->userGateway->getByID($studentID);

        if (empty($student)) {
            throw new \InvalidArgumentException('Student not found');
        }

        return [
            'gibbonPersonID' => $student['gibbonPersonID'] ?? '',
            'title' => $student['title'] ?? '',
            'surname' => $student['surname'] ?? '',
            'firstName' => $student['firstName'] ?? '',
            'preferredName' => $student['preferredName'] ?? '',
            'officialName' => $student['officialName'] ?? '',
            'nameInCharacters' => $student['nameInCharacters'] ?? '',
            'gender' => $student['gender'] ?? '',
            'dob' => $student['dob'] ?? '',
            'email' => $student['email'] ?? '',
            'emailAlternate' => $student['emailAlternate'] ?? '',
            'phone1' => $student['phone1'] ?? '',
            'phone2' => $student['phone2'] ?? '',
            'countryOfBirth' => $student['countryOfBirth'] ?? '',
            'ethnicity' => $student['ethnicity'] ?? '',
            'religion' => $student['religion'] ?? '',
            'citizenship1' => $student['citizenship1'] ?? '',
            'citizenship2' => $student['citizenship2'] ?? ''
        ];
    }

    /**
     * Export academic data.
     *
     * @param string $studentID
     * @return array
     */
    protected function exportAcademicData($studentID)
    {
        // Get active student record for current school year
        $result = $this->studentGateway->selectActiveStudentByPerson(
            $this->session->get('gibbonSchoolYearID'),
            $studentID
        );

        $student = $result->fetch();

        if (empty($student)) {
            return [
                'yearGroup' => ['id' => '', 'name' => ''],
                'formGroup' => ['id' => '', 'name' => ''],
                'rollOrder' => ''
            ];
        }

        return [
            'yearGroup' => [
                'id' => $student['gibbonYearGroupID'] ?? '',
                'name' => $student['yearGroup'] ?? ''
            ],
            'formGroup' => [
                'id' => $student['gibbonFormGroupID'] ?? '',
                'name' => $student['formGroup'] ?? ''
            ],
            'rollOrder' => $student['rollOrder'] ?? ''
        ];
    }

    /**
     * Export medical data.
     *
     * @param string $studentID
     * @return array
     */
    protected function exportMedicalData($studentID)
    {
        $medical = [
            'conditions' => [],
            'firstAid' => []
        ];

        // Get medical form
        $medicalForm = $this->medicalGateway->getMedicalFormByPerson($studentID);

        if (!empty($medicalForm)) {
            // Get medical conditions
            $conditions = $this->medicalGateway->selectMedicalConditionsByID($medicalForm['gibbonPersonMedicalID']);

            if ($conditions && $conditions->rowCount() > 0) {
                while ($condition = $conditions->fetch()) {
                    $medical['conditions'][] = [
                        'name' => $condition['name'] ?? '',
                        'details' => $condition['details'] ?? '',
                        'severity' => $condition['risk'] ?? '',
                        'emergencyContact' => $condition['emergencyContact'] ?? ''
                    ];
                }
            }

            // Add medical form data
            $medical['form'] = [
                'bloodType' => $medicalForm['bloodType'] ?? '',
                'longTermMedication' => $medicalForm['longTermMedication'] ?? '',
                'longTermMedicationDetails' => $medicalForm['longTermMedicationDetails'] ?? '',
                'comment' => $medicalForm['comment'] ?? ''
            ];
        }

        // Get first aid records using criteria
        $criteria = $this->firstAidGateway->newQueryCriteria()
            ->sortBy('gibbonFirstAid.timestamp', 'DESC')
            ->fromPOST();
        
        $firstAidRecords = $this->firstAidGateway->queryFirstAidByStudent($criteria, $this->session->get('gibbonSchoolYearID'), $studentID);

        if ($firstAidRecords->count() > 0) {
            foreach ($firstAidRecords as $record) {
                $medical['firstAid'][] = [
                    'date' => $record['date'] ?? '',
                    'type' => $record['type'] ?? '',
                    'description' => $record['description'] ?? '',
                    'treatment' => $record['treatment'] ?? '',
                    'followUp' => $record['followUp'] ?? ''
                ];
            }
        }

        return $medical;
    }

    /**
     * Export family data.
     *
     * @param string $studentID
     * @return array
     */
    protected function exportFamilyData($studentID)
    {
        $family = [];

        // Get family relationships
        $sql = "SELECT r.relationship, p.title, p.surname, p.preferredName, p.email, p.phone1, p.phone2,
                       f.name as familyName, f.nameAddress, f.homeAddress, f.languageHomePrimary, f.languageHomeSecondary
                FROM gibbonFamilyChild AS fc
                JOIN gibbonFamily AS f ON (f.gibbonFamilyID=fc.gibbonFamilyID)
                JOIN gibbonFamilyAdult AS fa ON (fa.gibbonFamilyID=f.gibbonFamilyID)
                JOIN gibbonPerson AS p ON (p.gibbonPersonID=fa.gibbonPersonID)
                LEFT JOIN gibbonFamilyRelationship AS r ON (r.gibbonFamilyID=f.gibbonFamilyID 
                    AND r.gibbonPersonID1=:studentID 
                    AND r.gibbonPersonID2=fa.gibbonPersonID)
                WHERE fc.gibbonPersonID=:studentID";

        $relationships = $this->connection->select($sql, ['studentID' => $studentID]);

        if (!empty($relationships)) {
            foreach ($relationships as $relative) {
                $family[] = [
                    'relationship' => $relative['relationship'] ?? '',
                    'familyName' => $relative['familyName'] ?? '',
                    'nameAddress' => $relative['nameAddress'] ?? '',
                    'homeAddress' => $relative['homeAddress'] ?? '',
                    'languageHomePrimary' => $relative['languageHomePrimary'] ?? '',
                    'languageHomeSecondary' => $relative['languageHomeSecondary'] ?? '',
                    'adult' => [
                        'title' => $relative['title'] ?? '',
                        'surname' => $relative['surname'] ?? '',
                        'preferredName' => $relative['preferredName'] ?? '',
                        'email' => $relative['email'] ?? '',
                        'phone1' => $relative['phone1'] ?? '',
                        'phone2' => $relative['phone2'] ?? ''
                    ]
                ];
            }
        }

        return $family;
    }

    /**
     * Export custom field data.
     *
     * @param string $studentID
     * @return array
     */
    protected function exportCustomFieldData($studentID)
    {
        $customFields = [];

        // Get custom field definitions
        $fields = $this->customFieldGateway->selectCustomFields('Person', [
            'student' => 1,
            'active' => 'Y'
        ])->fetchAll();

        // Get person record with custom field values
        $sql = "SELECT fields FROM gibbonPerson WHERE gibbonPersonID=:studentID";
        $result = $this->connection->selectOne($sql, ['studentID' => $studentID]);
        $fieldValues = !empty($result['fields']) ? json_decode($result['fields'], true) : [];

        // Match field definitions with values
        foreach ($fields as $field) {
            $customFields[$field['name']] = [
                'type' => $field['type'] ?? '',
                'value' => $fieldValues[$field['gibbonCustomFieldID']] ?? ''
            ];
        }

        return $customFields;
    }

    /**
     * Export student attachments.
     *
     * @param string $studentID
     * @return array
     */
    protected function exportAttachments($studentID)
    {
        $attachments = [];

        // Get any existing attachments from the transfer log
        $sql = "SELECT a.name, a.type, a.path, a.size
                FROM gibbonStudentTransferAttachment AS a 
                JOIN gibbonStudentTransferLog AS l ON (l.gibbonStudentTransferLogID=a.gibbonStudentTransferLogID)
                WHERE l.gibbonPersonID=:studentID
                AND l.status='Pending'";

        $result = $this->connection->select($sql, ['studentID' => $studentID]);

        if (!empty($result)) {
            foreach ($result as $file) {
                $attachments[] = [
                    'name' => $file['name'] ?? '',
                    'type' => $file['type'] ?? '',
                    'path' => $file['path'] ?? '',
                    'size' => $file['size'] ?? 0
                ];
            }
        }

        return $attachments;
    }

    /**
     * Export grades data.
     *
     * @param string $studentID
     * @return array
     */
    protected function exportGradesData($studentID)
    {
        $grades = [];

        $sql = "SELECT c.name as courseName, c.nameShort as courseNameShort, 
                       mc.name as gradeName, me.comment, me.attainmentValue,
                       me.attainmentDescriptor, mc.date as gradeDate,
                       sg.descriptor as scaleGrade, sg.value as scaleValue
                FROM gibbonCourseClassPerson AS p
                JOIN gibbonCourseClass AS cc ON (cc.gibbonCourseClassID=p.gibbonCourseClassID)
                JOIN gibbonCourse AS c ON (c.gibbonCourseID=cc.gibbonCourseID)
                JOIN gibbonMarkbookColumn AS mc ON (mc.gibbonCourseClassID=cc.gibbonCourseClassID)
                JOIN gibbonMarkbookEntry AS me ON (me.gibbonMarkbookColumnID=mc.gibbonMarkbookColumnID 
                    AND me.gibbonPersonIDStudent=p.gibbonPersonID)
                LEFT JOIN gibbonScale AS s ON (s.gibbonScaleID=mc.gibbonScaleIDAttainment)
                LEFT JOIN gibbonScaleGrade AS sg ON (sg.gibbonScaleID=s.gibbonScaleID 
                    AND sg.value=me.attainmentValue)
                WHERE p.gibbonPersonID=:studentID
                AND c.gibbonSchoolYearID=:gibbonSchoolYearID
                ORDER BY mc.date DESC";

        $result = $this->connection->select($sql, [
            'studentID' => $studentID,
            'gibbonSchoolYearID' => $this->session->get('gibbonSchoolYearID')
        ]);

        if (!empty($result)) {
            foreach ($result as $grade) {
                $grades[] = [
                    'course' => [
                        'name' => $grade['courseName'],
                        'nameShort' => $grade['courseNameShort']
                    ],
                    'grade' => [
                        'name' => $grade['gradeName'],
                        'comment' => $grade['comment'],
                        'value' => $grade['attainmentValue'],
                        'description' => $grade['attainmentDescriptor'] ?? $grade['scaleGrade'],
                        'date' => $grade['gradeDate']
                    ]
                ];
            }
        }

        return $grades;
    }

    /**
     * Export behavior records.
     *
     * @param string $studentID
     * @return array
     */
    protected function exportBehaviorData($studentID)
    {
        $behavior = [];

        $sql = "SELECT b.*
                FROM gibbonBehaviour AS b
                WHERE b.gibbonPersonID=:studentID
                AND b.gibbonSchoolYearID=:gibbonSchoolYearID
                ORDER BY b.timestamp DESC";

        $result = $this->connection->select($sql, [
            'studentID' => $studentID,
            'gibbonSchoolYearID' => $this->session->get('gibbonSchoolYearID')
        ]);

        if (!empty($result)) {
            foreach ($result as $record) {
                $behavior[] = [
                    'type' => $record['type'],
                    'descriptor' => $record['descriptor'],
                    'level' => $record['level'],
                    'comment' => $record['comment'],
                    'followup' => $record['followup'],
                    'date' => $record['timestamp'],
                    'created' => $record['timestampCreated']
                ];
            }
        }

        return $behavior;
    }

    /**
     * Export attendance data.
     *
     * @param string $studentID
     * @return array
     */
    protected function exportAttendanceData($studentID)
    {
        $attendance = [];

        $sql = "SELECT a.date, a.direction, a.type, a.reason, a.context, a.comment,
                       ac.name as codeName, ac.nameShort as codeNameShort,
                       ac.direction as codeDirection, ac.scope as codeScope
                FROM gibbonAttendanceLogPerson AS a
                LEFT JOIN gibbonAttendanceCode AS ac ON (ac.gibbonAttendanceCodeID=a.gibbonAttendanceCodeID)
                JOIN gibbonSchoolYear AS sy ON (sy.gibbonSchoolYearID=:gibbonSchoolYearID)
                WHERE a.gibbonPersonID=:studentID
                AND a.date BETWEEN sy.firstDay AND sy.lastDay
                ORDER BY a.date DESC, a.timestampTaken DESC";

        $result = $this->connection->select($sql, [
            'studentID' => $studentID,
            'gibbonSchoolYearID' => $this->session->get('gibbonSchoolYearID')
        ]);

        if (!empty($result)) {
            foreach ($result as $record) {
                $attendance[] = [
                    'date' => $record['date'],
                    'direction' => $record['direction'],
                    'type' => $record['type'],
                    'reason' => $record['reason'],
                    'context' => $record['context'],
                    'comment' => $record['comment'],
                    'code' => [
                        'name' => $record['codeName'],
                        'nameShort' => $record['codeNameShort'],
                        'direction' => $record['codeDirection'],
                        'scope' => $record['codeScope']
                    ]
                ];
            }
        }

        return $attendance;
    }

    /**
     * Clean up temporary directory and its contents
     * 
     * @param string $tempDir Path to temporary directory
     */
    private function cleanupTempDir(string $tempDir): void
    {
        if (!file_exists($tempDir)) {
            return;
        }

        $files = glob($tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($tempDir);
    }

    /**
     * Get human-readable ZIP error message
     * 
     * @param int $code ZipArchive error code
     * @return string Error message
     */
    private function getZipErrorMessage(int $code): string
    {
        $messages = [
            ZipArchive::ER_EXISTS => 'File already exists',
            ZipArchive::ER_INCONS => 'Zip archive inconsistent',
            ZipArchive::ER_INVAL => 'Invalid argument',
            ZipArchive::ER_MEMORY => 'Memory allocation failure',
            ZipArchive::ER_NOENT => 'No such file',
            ZipArchive::ER_NOZIP => 'Not a zip archive',
            ZipArchive::ER_OPEN => 'Can\'t open file',
            ZipArchive::ER_READ => 'Read error',
            ZipArchive::ER_SEEK => 'Seek error'
        ];
        
        return $messages[$code] ?? 'Unknown error';
    }

    /**
     * Extract original password from hash by finding a matching 6-digit number
     * Only used when reusing an existing password
     *
     * @param string $hash Password hash from database
     * @return string|null Original password if found, null otherwise
     */
    private function getPasswordFromHash($hash)
    {
        // Try all possible 6-digit numbers
        for ($i = 100000; $i <= 999999; $i++) {
            if (password_verify((string)$i, $hash)) {
                return (string)$i;
            }
        }
        return null;
    }
}

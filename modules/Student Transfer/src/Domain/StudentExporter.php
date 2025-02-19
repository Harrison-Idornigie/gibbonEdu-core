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
     * @return array Path to the generated ZIP file and password
     */
    public function exportToZip($studentID, $transferID)
    {
        // Get student data
        $data = $this->exportStudentData($studentID);

        // Create temporary directory
        $tempDir = sys_get_temp_dir() . '/student_transfer_' . $transferID;
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // Create student_data.json
        $jsonFile = $tempDir . '/student_data.json';
        file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

        // Create metadata.json
        $metadata = [
            'exportTimestamp' => date('Y-m-d H:i:s'),
            'transferID' => $transferID,
            'sourceSchool' => $this->settingGateway->getSettingByScope('System', 'organisationName'),
            'version' => '1.0.0'
        ];
        $metadataFile = $tempDir . '/metadata.json';
        file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));

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
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT));

        // Generate random password for ZIP
        $password = $this->securityService->generateSecurePassword();

        // Create password-protected ZIP file
        $zipFile = $tempDir . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            // Enable encryption
            $zip->setPassword($password);
            
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

            $zip->close();
        }

        // Clean up temporary files
        unlink($jsonFile);
        unlink($metadataFile);
        unlink($manifestFile);
        rmdir($tempDir);

        // Generate download token
        $token = $this->securityService->generateDownloadToken($transferID);

        return [
            'path' => $zipFile,
            'password' => $password,
            'token' => $token['token'],
            'expiry' => $token['expiry']
        ];
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
            ->sortBy('timestampCreated', 'DESC')
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

        $sql = "SELECT f.name, f.type, v.value
                FROM gibbonCustomField AS f
                JOIN gibbonPersonField AS v ON (v.gibbonCustomFieldID=f.gibbonCustomFieldID)
                WHERE v.gibbonPersonID=:studentID
                AND f.active='Y'";

        $fields = $this->connection->select($sql, ['studentID' => $studentID]);

        if (!empty($fields)) {
            foreach ($fields as $field) {
                $customFields[$field['name']] = [
                    'type' => $field['type'] ?? '',
                    'value' => $field['value'] ?? ''
                ];
            }
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

        $sql = "SELECT DISTINCT f.gibbonFileUploadID, f.name, f.type, f.path
                FROM gibbonFileUpload AS f
                WHERE f.gibbonPersonID=:studentID";

        $files = $this->connection->select($sql, ['studentID' => $studentID]);

        if (!empty($files)) {
            foreach ($files as $file) {
                $attachments[] = [
                    'id' => $file['gibbonFileUploadID'] ?? '',
                    'name' => $file['name'] ?? '',
                    'type' => $file['type'] ?? '',
                    'path' => $file['path'] ?? ''
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
                       g.name as gradeName, g.comment, g.timestampCreated,
                       s.name as scaleGrade, s.value as scaleValue
                FROM gibbonCourseClassPerson AS p
                JOIN gibbonCourseClass AS cc ON (cc.gibbonCourseClassID=p.gibbonCourseClassID)
                JOIN gibbonCourse AS c ON (c.gibbonCourseID=cc.gibbonCourseID)
                JOIN gibbonMarkbookEntry AS g ON (g.gibbonCourseClassID=cc.gibbonCourseClassID)
                LEFT JOIN gibbonScaleGrade AS s ON (s.value=g.attainmentValue)
                WHERE p.gibbonPersonID=:studentID
                AND g.gibbonSchoolYearID=:gibbonSchoolYearID
                ORDER BY g.timestampCreated DESC";

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
                        'value' => $grade['scaleValue'],
                        'description' => $grade['scaleGrade'],
                        'date' => $grade['timestampCreated']
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

        $sql = "SELECT b.type, b.descriptor, b.level, b.comment,
                       b.followup, b.timestamp, b.timestampCreated
                FROM gibbonBehaviour AS b
                WHERE b.gibbonPersonID=:studentID
                AND b.gibbonSchoolYearID=:gibbonSchoolYearID
                ORDER BY b.timestampCreated DESC";

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
     * Export attendance records.
     *
     * @param string $studentID
     * @return array
     */
    protected function exportAttendanceData($studentID)
    {
        $attendance = [];

        $sql = "SELECT a.date, a.type, a.reason, a.comment,
                       a.timestampTaken, a.context
                FROM gibbonAttendanceLogPerson AS a
                WHERE a.gibbonPersonID=:studentID
                AND a.gibbonSchoolYearID=:gibbonSchoolYearID
                ORDER BY a.date DESC";

        $result = $this->connection->select($sql, [
            'studentID' => $studentID,
            'gibbonSchoolYearID' => $this->session->get('gibbonSchoolYearID')
        ]);

        if (!empty($result)) {
            foreach ($result as $record) {
                $attendance[] = [
                    'date' => $record['date'],
                    'type' => $record['type'],
                    'reason' => $record['reason'],
                    'comment' => $record['comment'],
                    'context' => $record['context'],
                    'taken' => $record['timestampTaken']
                ];
            }
        }

        return $attendance;
    }
}

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
use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\DataSet;

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
    protected $pdo;
    protected $userGateway;
    protected $studentGateway;
    protected $medicalGateway;
    protected $firstAidGateway;
    protected $settingGateway;

    public function __construct(
        Connection $pdo,
        UserGateway $userGateway,
        StudentGateway $studentGateway,
        MedicalGateway $medicalGateway,
        FirstAidGateway $firstAidGateway,
        SettingGateway $settingGateway
    ) {
        $this->pdo = $pdo;
        $this->userGateway = $userGateway;
        $this->studentGateway = $studentGateway;
        $this->medicalGateway = $medicalGateway;
        $this->firstAidGateway = $firstAidGateway;
        $this->settingGateway = $settingGateway;
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
            'attachments' => $this->exportAttachments($studentID)
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
            'gibbonPersonID' => $student['gibbonPersonID'],
            'title' => $student['title'],
            'surname' => $student['surname'],
            'firstName' => $student['firstName'],
            'preferredName' => $student['preferredName'],
            'officialName' => $student['officialName'],
            'nameInCharacters' => $student['nameInCharacters'],
            'gender' => $student['gender'],
            'dob' => $student['dob'],
            'email' => $student['email'],
            'emailAlternate' => $student['emailAlternate'],
            'phone1' => $student['phone1'],
            'phone2' => $student['phone2'],
            'countryOfBirth' => $student['countryOfBirth'],
            'ethnicity' => $student['ethnicity'],
            'religion' => $student['religion'],
            'citizenship1' => $student['citizenship1'],
            'citizenship2' => $student['citizenship2']
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
        // Get academic data directly from student gateway
        $student = $this->studentGateway->selectBy(['gibbonPersonID' => $studentID])->fetch();
        
        return [
            'yearGroup' => $student['gibbonYearGroupID'] ?? '',
            'formGroup' => $student['gibbonFormGroupID'] ?? '',
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
        $medical = [];

        // Get medical conditions using selectBy
        $conditions = $this->medicalGateway->selectBy(['gibbonPersonID' => $studentID])->fetchAll();
        foreach ($conditions as $condition) {
            $medical['conditions'][] = [
                'name' => $condition['name'],
                'details' => $condition['details'],
                'severity' => $condition['severity'],
                'emergencyContact' => $condition['emergencyContact']
            ];
        }

        // Get first aid records using selectBy
        $firstAid = $this->firstAidGateway->selectBy(['gibbonPersonID' => $studentID])->fetchAll();
        foreach ($firstAid as $record) {
            $medical['firstAid'][] = [
                'date' => $record['date'],
                'details' => $record['details'],
                'followUp' => $record['followUp']
            ];
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
        $familyData = [];
        
        // Get family data directly from database since we don't have FamilyGateway
        $sql = "SELECT f.* FROM gibbonFamily f 
                JOIN gibbonFamilyChild fc ON (fc.gibbonFamilyID=f.gibbonFamilyID) 
                WHERE fc.gibbonPersonID=:studentID";
                
        $result = $this->pdo->select($sql, ['studentID' => $studentID]);
        
        while ($family = $result->fetch()) {
            $familyData[] = [
                'name' => $family['name'],
                'nameAddress' => $family['nameAddress'],
                'homeAddress' => $family['homeAddress'],
                'languageHomePrimary' => $family['languageHomePrimary'],
                'languageHomeSecondary' => $family['languageHomeSecondary']
            ];
        }

        return $familyData;
    }

    /**
     * Export custom field data.
     *
     * @param string $studentID
     * @return array
     */
    protected function exportCustomFieldData($studentID)
    {
        $customData = [];
        
        // Get custom fields directly from database since we don't have CustomFieldGateway
        $sql = "SELECT f.name, v.value 
                FROM gibbonCustomField f 
                JOIN gibbonPersonField v ON (v.gibbonCustomFieldID=f.gibbonCustomFieldID) 
                WHERE v.gibbonPersonID=:studentID";
                
        $result = $this->pdo->select($sql, ['studentID' => $studentID]);
        
        while ($field = $result->fetch()) {
            $customData[$field['name']] = $field['value'];
        }

        return $customData;
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
        
        // Get file attachments from various tables
        $sql = "SELECT DISTINCT a.* FROM gibbonFileAttachment AS a 
                JOIN gibbonPersonAttachment AS pa ON (a.gibbonFileAttachmentID=pa.gibbonFileAttachmentID) 
                WHERE pa.gibbonPersonID=:studentID";
                
        $result = $this->pdo->select($sql, ['studentID' => $studentID]);
        
        while ($attachment = $result->fetch()) {
            $attachments[] = [
                'name' => $attachment['name'],
                'path' => $attachment['path'],
                'type' => $attachment['type'],
                'size' => $attachment['size']
            ];
        }

        return $attachments;
    }
}

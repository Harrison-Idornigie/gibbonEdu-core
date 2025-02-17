<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\StudentTransfer\Domain;

use Gibbon\Domain\User\UserGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\Students\ApplicationFormGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Contracts\Database\Connection;

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
     * Check for potential duplicate students.
     *
     * @param array $studentData
     * @return array
     */
    public function checkDuplicates($studentData)
    {
        $duplicates = [];
        
        // Check by official name
        $nameMatches = $this->userGateway->selectBy([
            'surname' => $studentData['surname'],
            'firstName' => $studentData['firstName']
        ])->fetchAll();
        
        if (!empty($nameMatches)) {
            $duplicates['name'] = $nameMatches;
        }
        
        // Check by date of birth
        $dobMatches = $this->userGateway->selectBy([
            'dob' => $studentData['dob']
        ])->fetchAll();
        
        if (!empty($dobMatches)) {
            $duplicates['dob'] = $dobMatches;
        }
        
        // Check by previous school
        $sql = "SELECT DISTINCT p.* FROM gibbonPerson p 
                JOIN gibbonStudentEnrolment e ON (p.gibbonPersonID=e.gibbonPersonID)
                WHERE e.schoolName=:schoolName";
        
        $schoolMatches = $this->pdo->select($sql, [
            'schoolName' => $studentData['previousSchool']
        ])->fetchAll();
        
        if (!empty($schoolMatches)) {
            $duplicates['school'] = $schoolMatches;
        }
        
        return $duplicates;
    }

    /**
     * Create an application form from transfer data.
     *
     * @param array $transferData
     * @param string $transferID
     * @return string Application ID
     */
    public function createApplicationForm($transferData, $transferID)
    {
        // Start transaction
        $this->pdo->getConnection()->beginTransaction();
        
        try {
            // Create application form
            $applicationData = [
                'gibbonSchoolYearID' => $this->settingGateway->getSettingByScope('System', 'gibbonSchoolYearIDCurrent'),
                'gibbonFormGroupID' => null, // To be assigned
                'firstName' => $transferData['personal']['firstName'],
                'surname' => $transferData['personal']['surname'],
                'preferredName' => $transferData['personal']['preferredName'],
                'officialName' => $transferData['personal']['officialName'],
                'nameInCharacters' => $transferData['personal']['nameInCharacters'],
                'gender' => $transferData['personal']['gender'],
                'dob' => $transferData['personal']['dob'],
                'email' => $transferData['personal']['email'],
                'phone1' => $transferData['personal']['phone1'],
                'phone1Type' => $transferData['personal']['phone1Type'],
                'phone2' => $transferData['personal']['phone2'],
                'phone2Type' => $transferData['personal']['phone2Type'],
                'countryOfBirth' => $transferData['personal']['countryOfBirth'],
                'citizenship1' => $transferData['personal']['citizenship1'],
                'citizenship1Passport' => $transferData['personal']['citizenship1Passport'],
                'nationalIDCardNumber' => $transferData['personal']['nationalIDCardNumber'],
                'residencyStatus' => $transferData['personal']['residencyStatus'],
                'visaExpiryDate' => $transferData['personal']['visaExpiryDate'],
                'address1' => $transferData['personal']['address1'],
                'address1District' => $transferData['personal']['address1District'],
                'address1Country' => $transferData['personal']['address1Country'],
                'address2' => $transferData['personal']['address2'],
                'address2District' => $transferData['personal']['address2District'],
                'address2Country' => $transferData['personal']['address2Country'],
                'languageFirst' => $transferData['personal']['languageFirst'],
                'languageSecond' => $transferData['personal']['languageSecond'],
                'languageThird' => $transferData['personal']['languageThird'],
                'previousSchool' => $transferData['academic']['enrolments'][0]['schoolName'],
                'dateStart' => date('Y-m-d'),
                'notes' => "Transfer from {$transferData['academic']['enrolments'][0]['schoolName']}. Transfer ID: {$transferID}",
                'status' => 'Pending',
                'studentTransferLogID' => $transferID
            ];
            
            $applicationID = $this->applicationFormGateway->insert($applicationData);
            
            // Add medical information
            if (!empty($transferData['medical'])) {
                $this->processMedicalData($applicationID, $transferData['medical']);
            }
            
            // Add family information
            if (!empty($transferData['family'])) {
                $this->processFamilyData($applicationID, $transferData['family']);
            }
            
            // Add custom field data
            if (!empty($transferData['custom'])) {
                $this->processCustomFieldData($applicationID, $transferData['custom']);
            }
            
            // Commit transaction
            $this->pdo->getConnection()->commit();
            
            return $applicationID;
        } catch (\Exception $e) {
            $this->pdo->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * Process medical data for the application.
     *
     * @param string $applicationID
     * @param array $medicalData
     */
    protected function processMedicalData($applicationID, $medicalData)
    {
        foreach ($medicalData['conditions'] as $condition) {
            $data = [
                'gibbonApplicationFormID' => $applicationID,
                'name' => $condition['name'],
                'details' => $condition['details']
            ];
            
            $sql = "INSERT INTO gibbonApplicationFormMedical SET 
                    gibbonApplicationFormID=:gibbonApplicationFormID,
                    name=:name, details=:details";
            
            $this->pdo->insert($sql, $data);
        }
    }

    /**
     * Process family data for the application.
     *
     * @param string $applicationID
     * @param array $familyData
     */
    protected function processFamilyData($applicationID, $familyData)
    {
        foreach ($familyData['adults'] as $adult) {
            $data = [
                'gibbonApplicationFormID' => $applicationID,
                'title' => $adult['title'],
                'surname' => $adult['surname'],
                'firstName' => $adult['firstName'],
                'preferredName' => $adult['preferredName'],
                'officialName' => $adult['officialName'],
                'nameInCharacters' => $adult['nameInCharacters'],
                'gender' => $adult['gender'],
                'relationship' => $adult['relationship'],
                'email' => $adult['email'],
                'phone1' => $adult['phone1'],
                'phone1Type' => $adult['phone1Type'],
                'phone2' => $adult['phone2'],
                'phone2Type' => $adult['phone2Type'],
                'profession' => $adult['profession'],
                'employer' => $adult['employer']
            ];
            
            $sql = "INSERT INTO gibbonApplicationFormRelationship SET 
                    gibbonApplicationFormID=:gibbonApplicationFormID,
                    title=:title, surname=:surname, firstName=:firstName,
                    preferredName=:preferredName, officialName=:officialName,
                    nameInCharacters=:nameInCharacters, gender=:gender,
                    relationship=:relationship, email=:email,
                    phone1=:phone1, phone1Type=:phone1Type,
                    phone2=:phone2, phone2Type=:phone2Type,
                    profession=:profession, employer=:employer";
            
            $this->pdo->insert($sql, $data);
        }
    }

    /**
     * Process custom field data for the application.
     *
     * @param string $applicationID
     * @param array $customData
     */
    protected function processCustomFieldData($applicationID, $customData)
    {
        foreach ($customData as $fieldName => $field) {
            $data = [
                'gibbonApplicationFormID' => $applicationID,
                'fieldName' => $fieldName,
                'value' => is_array($field['value']) ? implode(',', $field['value']) : $field['value']
            ];
            
            $sql = "INSERT INTO gibbonApplicationFormCustomField SET 
                    gibbonApplicationFormID=:gibbonApplicationFormID,
                    fieldName=:fieldName, value=:value";
            
            $this->pdo->insert($sql, $data);
        }
    }

    /**
     * Process a student import from transfer data.
     *
     * @param string $studentID Student ID to import
     * @param array $options Import options
     * @return bool Success/failure
     */
    public function processImport($studentID, array $options)
    {
        try {
            // Get transfer data
            $transferData = $this->securityService->decryptTransferData($options['transferData']);
            
            // Check for duplicates
            $duplicates = $this->checkDuplicates($transferData['personal']);
            
            if (!empty($duplicates) && !$options['forceDuplicates']) {
                throw new \Exception('Potential duplicate students found. Set forceDuplicates option to proceed.');
            }
            
            // Create application form
            $applicationID = $this->createApplicationForm($transferData, $studentID);
            
            // Process additional data
            if (!empty($transferData['medical'])) {
                $this->processMedicalData($applicationID, $transferData['medical']);
            }
            
            if (!empty($transferData['family'])) {
                $this->processFamilyData($applicationID, $transferData['family']);
            }
            
            if (!empty($transferData['custom'])) {
                $this->processCustomFieldData($applicationID, $transferData['custom']);
            }
            
            return true;
            
        } catch (\Exception $e) {
            // Log error and return false
            error_log($e->getMessage());
            return false;
        }
    }
}

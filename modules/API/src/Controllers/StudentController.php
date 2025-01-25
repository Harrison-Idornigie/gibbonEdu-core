<?php
namespace Gibbon\Module\API\Controllers;

use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\School\SchoolYearGateway;
use Exception;

class StudentController {
    private $studentGateway;
    private $schoolYearGateway;

    public function __construct() {
        global $container;
        
        if (!isset($container)) {
            throw new Exception("Container not initialized");
        }
        
        $this->studentGateway = $container->get(StudentGateway::class);
        $this->schoolYearGateway = $container->get(SchoolYearGateway::class);
        
        if (!$this->studentGateway || !$this->schoolYearGateway) {
            throw new Exception("Failed to initialize gateways");
        }
    }

    public function index() {
        try {
            // Create criteria with joins for all the data we need
            $criteria = $this->studentGateway->newQueryCriteria(true)
                ->fromPOST()
                ->sortBy(['gibbonYearGroup.sequenceNumber', 'gibbonFormGroup.name', 'surname', 'preferredName']);
            
            // Add filter if specified
            if (isset($_GET['filter']) && $_GET['filter'] === 'active') {
                $criteria->filterBy('status', 'Full');
            }
            
            // Get school year
            $schoolYear = $_GET['schoolYear'] ?? null;
            if (empty($schoolYear)) {
                $currentYear = $this->schoolYearGateway->getCurrentSchoolYear();
                if (!$currentYear) {
                    throw new Exception("Could not determine current school year");
                }
                $schoolYear = $currentYear['gibbonSchoolYearID'];
            }
            
            // Query students
            $students = $this->studentGateway->queryStudentsBySchoolYear($criteria, $schoolYear);
            $studentArray = $students->toArray();
            
            // Format response with meta and status
            return [
                'meta' => [
                    'total' => $students->getPageCount(),
                    'page' => $criteria->getPage(),
                    'pageSize' => $criteria->getPageSize(),
                    'totalPages' => ceil($students->getPageCount() / $criteria->getPageSize()),
                    'schoolYear' => [
                        'id' => $schoolYear,
                        'name' => $currentYear['name'] ?? null
                    ],
                    'filters' => [
                        'status' => isset($_GET['filter']) ? $_GET['filter'] : 'all'
                    ]
                ],
                'status' => [
                    'code' => 200,
                    'message' => 'Success'
                ],
                'data' => $this->formatStudents($studentArray)
            ];
            
        } catch (Exception $e) {
            return [
                'meta' => [],
                'status' => [
                    'code' => 500,
                    'message' => $e->getMessage()
                ],
                'data' => []
            ];
        }
    }

    public function show($id) {
        try {
            $student = $this->studentGateway->getByID($id);
            if (empty($student)) {
                return [
                    'meta' => [],
                    'status' => [
                        'code' => 404,
                        'message' => 'Student not found'
                    ],
                    'data' => null
                ];
            }

            return [
                'meta' => [
                    'timestamp' => date('c')
                ],
                'status' => [
                    'code' => 200,
                    'message' => 'Success'
                ],
                'data' => $this->formatStudent($student)
            ];
            
        } catch (Exception $e) {
            return [
                'meta' => [],
                'status' => [
                    'code' => 500,
                    'message' => $e->getMessage()
                ],
                'data' => null
            ];
        }
    }

    private function formatStudent($student) {
        return [
            'id' => $student['gibbonPersonID'] ?? null,
            'name' => [
                'title' => $student['title'] ?? null,
                'surname' => $student['surname'] ?? null,
                'preferredName' => $student['preferredName'] ?? null
            ],
            'email' => $student['email'] ?? null,
            'yearGroup' => [
                'id' => $student['gibbonYearGroupID'] ?? null,
                'name' => $student['yearGroupName'] ?? null
            ],
            'formGroup' => [
                'id' => $student['gibbonFormGroupID'] ?? null,
                'name' => $student['formGroupName'] ?? null
            ],
            'schoolYear' => [
                'id' => $student['gibbonSchoolYearID'] ?? null,
                'name' => $student['schoolYearName'] ?? null
            ],
            'status' => [
                'current' => $student['status'] ?? null,
                'reason' => $student['departureReason'] ?? null,
                'enrollment' => [
                    'start' => $student['dateStart'] ?? null,
                    'end' => $student['dateEnd'] ?? null,
                    'active' => (
                        (!empty($student['dateStart']) && $student['dateStart'] <= date('Y-m-d')) &&
                        (empty($student['dateEnd']) || $student['dateEnd'] >= date('Y-m-d'))
                    ),
                    'leaving' => (
                        !empty($student['dateEnd']) && 
                        $student['dateEnd'] <= date('Y-m-d', strtotime('today + 60 days'))
                    )
                ]
            ],
            'image' => $student['image_240'] ?? null
        ];
    }

    private function formatStudents($students) {
        return array_map([$this, 'formatStudent'], $students);
    }
}

<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Allow CORS for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Get the Gibbon root directory
    $rootPath = dirname(dirname(dirname(__FILE__)));

    // Include Gibbon bootstrap
    if (! file_exists($rootPath . '/gibbon.php')) {
        throw new Exception('Gibbon bootstrap file not found at: ' . $rootPath . '/gibbon.php');
    }

    require_once $rootPath . '/gibbon.php';

    // Test database connection
    global $pdo;
    if (! isset($pdo)) {
        throw new Exception('Database connection not initialized');
    }

    // Test container
    if (! isset($container)) {
        throw new Exception('Container not initialized');
    }

    // Get the requested endpoint
    $endpoint = $_GET['endpoint'] ?? '';

    switch ($endpoint) {
        case 'students':
            try {
                $studentGateway    = $container->get(\Gibbon\Domain\Students\StudentGateway::class);
                $schoolYearGateway = $container->get(\Gibbon\Domain\School\SchoolYearGateway::class);

                // Get current school year
                $currentSchoolYear = $schoolYearGateway->getByID($session->get('gibbonSchoolYearID'));
                if (! $currentSchoolYear) {
                    throw new Exception('Current school year not found');
                }

                // Handle specific student request
                if (! empty($_GET['id'])) {
                    $student = $studentGateway->getByID($_GET['id']);
                    if (! $student) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Student not found']);
                        exit();
                    }

                    $data = [
                        'id'         => $student['gibbonPersonID'],
                        'name'       => [
                            'title'         => $student['title'],
                            'surname'       => $student['surname'],
                            'preferredName' => $student['preferredName'],
                        ],
                        'email'      => $student['email'],
                        'yearGroup'  => [
                            'id'   => $student['gibbonYearGroupID'],
                            'name' => $student['yearGroupName'],
                        ],
                        'formGroup'  => [
                            'id'   => $student['gibbonFormGroupID'],
                            'name' => $student['formGroupName'],
                        ],
                        'schoolYear' => [
                            'id'   => $student['gibbonSchoolYearID'],
                            'name' => $student['schoolYearName'],
                        ],
                        'status'     => [
                            'current'    => $student['status'],
                            'reason'     => $student['departureReason'] ?? null,
                            'enrollment' => [
                                'start'   => $student['dateStart'],
                                'end'     => $student['dateEnd'],
                                'active'  => (
                                    (! empty($student['dateStart']) && $student['dateStart'] <= date('Y-m-d')) &&
                                    (! empty($student['dateEnd']) || $student['dateEnd'] >= date('Y-m-d'))
                                ),
                                'leaving' => (
                                    ! empty($student['dateEnd']) &&
                                    $student['dateEnd'] <= date('Y-m-d', strtotime('today + 60 days'))
                                ),
                            ],
                        ],
                        'image'      => $student['image'],
                    ];

                    echo json_encode([
                        'data' => $data,
                    ]);
                    exit();
                }

                // Set up criteria
                $criteria = new \Gibbon\Domain\QueryCriteria();

                // Add search if provided
                if (! empty($_GET['q'])) {
                    $criteria->searchBy(['preferredName', 'surname', 'email'], $_GET['q']);
                }

                // Add year group filter
                if (! empty($_GET['yearGroup'])) {
                    $criteria->addFilterRules([
                        'yearGroup' => function ($query, $yearGroup) {
                            return $query->where('gibbonYearGroup.gibbonYearGroupID = :yearGroup')
                                ->bindValue('yearGroup', $yearGroup);
                        },
                    ]);
                }

                // Add form group filter
                if (! empty($_GET['formGroup'])) {
                    $criteria->addFilterRules([
                        'formGroup' => function ($query, $formGroup) {
                            return $query->where('gibbonFormGroup.gibbonFormGroupID = :formGroup')
                                ->bindValue('formGroup', $formGroup);
                        },
                    ]);
                }

                // Add sorting
                $sort  = $_GET['sort'] ?? 'surname';
                $order = strtoupper($_GET['order'] ?? 'ASC');
                if (! in_array($order, ['ASC', 'DESC'])) {
                    $order = 'ASC';
                }

                $criteria->sortBy($sort, $order);

                // Set page size
                $pageSize = min(50, intval($_GET['pageSize'] ?? 25));
                $page     = max(1, intval($_GET['page'] ?? 1));
                $criteria->page($page)->pageSize($pageSize);

                // Query students
                $students = $studentGateway->queryStudentsBySchoolYear($criteria, $currentSchoolYear['gibbonSchoolYearID']);

                $data = [];
                foreach ($students->toArray() as $student) {
                    $data[] = [
                        'id'         => $student['gibbonPersonID'],
                        'name'       => [
                            'title'         => $student['title'],
                            'surname'       => $student['surname'],
                            'preferredName' => $student['preferredName'],
                        ],
                        'email'      => $student['email'],
                        'yearGroup'  => [
                            'id'   => $student['gibbonYearGroupID'],
                            'name' => $student['yearGroupName'],
                        ],
                        'formGroup'  => [
                            'id'   => $student['gibbonFormGroupID'],
                            'name' => $student['formGroupName'],
                        ],
                        'schoolYear' => [
                            'id'   => $student['gibbonSchoolYearID'],
                            'name' => $student['schoolYearName'],
                        ],
                        'status'     => [
                            'current'    => $student['status'],
                            'reason'     => $student['departureReason'] ?? null,
                            'enrollment' => [
                                'start'   => $student['dateStart'],
                                'end'     => $student['dateEnd'],
                                'active'  => (
                                    (! empty($student['dateStart']) && $student['dateStart'] <= date('Y-m-d')) &&
                                    (! empty($student['dateEnd']) || $student['dateEnd'] >= date('Y-m-d'))
                                ),
                                'leaving' => (
                                    ! empty($student['dateEnd']) &&
                                    $student['dateEnd'] <= date('Y-m-d', strtotime('today + 60 days'))
                                ),
                            ],
                        ],
                        'image'      => $student['image'],
                    ];
                }

                echo json_encode([
                    'data' => $data,
                    'meta' => [
                        'total'    => $students->getTotalCount(),
                        'page'     => $page,
                        'pageSize' => $pageSize,
                    ],
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'error'   => 'Internal server error',
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTrace(),
                ]);
            }
            break;

        default:
            if (empty($endpoint)) {
                echo json_encode([
                    'status'    => 'success',
                    'message'   => 'API endpoint is working',
                    'version'   => 'v1',
                    'endpoints' => [
                        'students'     => '/api/v1/students',
                        'students/:id' => '/api/v1/students/{id}',
                    ],
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Internal server error',
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
        'trace'   => $e->getTrace(),
    ]);
}
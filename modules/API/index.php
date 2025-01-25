<?php
// Initialize Gibbon core
require_once '../../gibbon.php';

// Initialize autoloader
require_once __DIR__ . '/vendor/autoload.php';

use Gibbon\Module\API\Router;
use Gibbon\Module\API\Controllers\StudentController;
use Gibbon\Domain\School\SchoolYearGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\QueryBuilder;

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set JSON content type
header('Content-Type: application/json');

try {
    // Initialize Gibbon session if not already done
    if (!isset($session)) {
        $session = $container->get('session');
    }
    
    // Register dependencies
    $container->add('QueryBuilder', QueryBuilder::class);

    $container->add(StudentGateway::class)
        ->addArgument($pdo)
        ->addArgument($container->get('QueryBuilder'));

    $container->add(SchoolYearGateway::class)
        ->addArgument($pdo)
        ->addArgument($container->get('QueryBuilder'));

    // Get current school year if not set
    if (empty($session->get('gibbonSchoolYearID'))) {
        $schoolYearGateway = $container->get(SchoolYearGateway::class);
        $schoolYear = $schoolYearGateway->getDefaultYear();
        $session->set('gibbonSchoolYearID', $schoolYear['gibbonSchoolYearID']);
        error_log("Set school year ID to: " . $schoolYear['gibbonSchoolYearID']);
    }

    // Create router
    $router = new Router();

    // Define routes
    $router->get('/students', [StudentController::class, 'index']);
    $router->get('/students/{id}', [StudentController::class, 'show']);

    // Dispatch request and send response
    echo json_encode($router->dispatch());
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

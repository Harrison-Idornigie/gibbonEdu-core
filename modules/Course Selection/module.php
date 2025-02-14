<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

// Include the Composer autoloader
$autoloader = require __DIR__ . '/vendor/autoload.php';

// Module Functions
require_once __DIR__ . '/moduleFunctions.php';

// Add module namespace to Gibbon autoloader
$autoloader->addPsr4('CourseSelection\\', $session->get('absolutePath').'/modules/Course Selection/src/');

// Register the core services as instances
$container->share('Gibbon\Contracts\Database\Connection', $pdo);
$container->share('Gibbon\session', $gibbon->session);
$container->add('pdo', $pdo, true);

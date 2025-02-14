<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Domain;

use Gibbon\Contracts\Database\Connection;

/**
 * Course Selection: gibbonSettings Table Gateway
 *
 * @version v14
 * @since   17th April 2017
 * @author  Sandra Kuipers
 *
 * @uses  gibbonSetting
 */
class SettingsGateway
{
    protected $pdo;

    public function __construct(Connection $pdo)
    {
        $this->pdo = $pdo;
    }

    public function update($scope, $name, $value)
    {
        $data = array('scope' => $scope, 'name' => $name, 'value' => $value);
        $sql = "UPDATE gibbonSetting SET value=:value WHERE scope=:scope AND name=:name";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getQuerySuccess();
    }
}

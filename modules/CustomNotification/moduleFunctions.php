<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

/**
 * Check if user is a parent
 *
 * @param string $role
 * @return bool
 */
function isParent($role)
{
    return $role == 'Parent';
}

/**
 * Check if user is a teacher or admin
 *
 * @param string $role
 * @return bool
 */
function isTeacherOrAdmin($role)
{
    return in_array($role, ['Teacher', 'Administrator']);
}

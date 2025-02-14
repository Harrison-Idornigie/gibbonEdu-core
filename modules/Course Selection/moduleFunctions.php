<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

/**
 * From JSfiddle http://jsfiddle.net/9uvfP/ shared by Andrew Chiu
 */
function cartesian($args) {
    $r = array();

    $helper = function($arr, $i, $max) use (&$helper, &$args, &$r) {
        for ($j=0; $j<count($args[$i]); $j++) {
            $a = array_slice($arr, 0); // clone arr
            array_push($a, $args[$i][$j]);
            if ($i==$max) {
                array_push($r, $a);
            } else {
                $helper($a, $i+1, $max);
            }
        }
    };

    $helper([], 0, count($args)-1);

    return $r;
};

function isCommandLine()
{
    if ( defined('STDIN') )
    {
        return true;
    }

    if ( php_sapi_name() === 'cli' )
    {
        return true;
    }

    if ( array_key_exists('SHELL', $_ENV) ) {
        return true;
    }

    if ( empty($_SERVER['REMOTE_ADDR']) and !isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv']) > 0)
    {
        return true;
    }

    if ( !array_key_exists('REQUEST_METHOD', $_SERVER) )
    {
        return true;
    }

    return false;
}


<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Services\Format;
use Gibbon\Module\Rubrics\Visualise;
use Gibbon\Domain\Rubrics\RubricGateway;
use Gibbon\Module\ATL\Domain\ATLColumnGateway;

//Module includes
require_once './modules/ATL/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/ATL/atl_view.php') == false) {
    //Acess denied
    echo "<div class='error'>";
    echo 'You do not have access to this action.';
    echo '</div>';
} else {
    // Register scripts available to the core, but not included by default
    $page->scripts->add('chart');

    $roleCategory = $session->get('gibbonRoleIDCurrentCategory');
    if ($roleCategory == 'Staff') {
        echo Format::alert(__m('As a staff member, your view of this ATL diagram accounts for all current ATL records, including those before their complete date. Parents and students will only see the ATL diagram based on completed data.'), 'message');
    }

    echo visualiseATL($container, $gibbonPersonID);
    
    echo getATLRecord($guid, $connection2, $gibbonPersonID);
}

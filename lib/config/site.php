<?php

return [
    'vars' => [
        'sandbox_smarty' => [
            '$wa->sandbox->getVar(\'name\')'            => _w('Get a variable of the current user by name'),
            '$wa->sandbox->getVar(\'name\', \'default\')' => _w('Get a variable with a fallback value'),
            '$wa->sandbox->getAllVars()'                 => _w('Get all variables of the current user (array)'),
            '$wa->sandbox->setVar(\'name\', value)'     => _w('Save a variable for the current user'),
            '$wa->sandbox->deleteVar(\'name\')'         => _w('Delete a variable of the current user'),
        ],
    ],
    'vars_tab_names' => [
        'sandbox_smarty' => _w('Sandbox'),
    ],
];

<?php

return [
    'vars' => [
        'sandbox_smarty' => [
            '$wa->sandbox->getVar(\'name\')'            => _w('Получить переменную текущего пользователя по имени'),
            '$wa->sandbox->getVar(\'name\', \'default\')' => _w('Получить переменную с fallback-значением'),
            '$wa->sandbox->getAllVars()'                 => _w('Получить все переменные текущего пользователя (массив)'),
            '$wa->sandbox->setVar(\'name\', value)'     => _w('Сохранить переменную для текущего пользователя'),
            '$wa->sandbox->deleteVar(\'name\')'         => _w('Удалить переменную текущего пользователя'),
        ],
    ],
    'vars_tab_names' => [
        'sandbox_smarty' => 'Sandbox Helper',
    ],
];

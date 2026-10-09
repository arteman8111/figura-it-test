<?php

// Основная логика находится в файле задачи 3.
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/figura/common.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/figura/tasks/03_equipment_component.php';

class FiguraEquipmentListComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        \Figura\Equipment\showEquipmentList($this);
    }
}

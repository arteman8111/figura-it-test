<?php

/**
 * Регистрация задач 4 и 5. Добавить в существующий /local/php_interface/init.php:
 * require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/figura/init.php';
 * Установщик здесь не запускается: обычное открытие страницы не создаёт данные.
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/tasks/04_smart_process_tab.php';
require_once __DIR__ . '/tasks/05_deal_equipment_count.php';

\Figura\Equipment\SmartProcessTab::register();
\Figura\Equipment\DealEquipmentCount::register();

<?php

// Подключить этот файл из /local/php_interface/init.php. Он регистрирует обработчики карточек.
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/tasks/04_smart_process_tab.php';
require_once __DIR__ . '/tasks/05_deal_equipment_count.php';

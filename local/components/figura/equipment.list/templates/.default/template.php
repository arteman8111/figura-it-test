<?php

/**
 * Шаблон задачи 3: одинаковый GRID_ID связывает main.ui.filter с main.ui.grid.
 * Компонент отвечает только за чтение, поэтому выбор строк и групповые действия отключены.
 * @var CMain $APPLICATION
 * @var array $arResult
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

$APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', [
    'FILTER_ID' => $arResult['GRID_ID'],
    'GRID_ID' => $arResult['GRID_ID'],
    'FILTER' => $arResult['FILTER_FIELDS'],
    'ENABLE_LIVE_SEARCH' => true,
    'ENABLE_LABEL' => true,
], $component);

$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', [
    'GRID_ID' => $arResult['GRID_ID'],
    'COLUMNS' => [
        ['id' => 'ID', 'name' => 'ID', 'sort' => 'ID', 'default' => true],
        ['id' => 'UF_NAME', 'name' => 'Название', 'sort' => 'UF_NAME', 'default' => true],
        ['id' => 'UF_TYPE', 'name' => 'Тип оборудования', 'sort' => 'UF_TYPE', 'default' => true],
        ['id' => 'UF_CREATED_TIME', 'name' => 'Дата создания', 'sort' => 'UF_CREATED_TIME', 'default' => true],
        ['id' => 'UF_DEAL_ID', 'name' => 'ID сделки', 'sort' => 'UF_DEAL_ID', 'default' => true],
    ],
    'ROWS' => $arResult['ROWS'],
    'SORT' => $arResult['SORT'],
    'SORT_VARS' => $arResult['SORT_VARS'],
    'NAV_OBJECT' => $arResult['NAV_OBJECT'],
    'TOTAL_ROWS_COUNT' => $arResult['TOTAL'],
    'SHOW_PAGINATION' => true,
    'SHOW_TOTAL_COUNTER' => true,
    'SHOW_PAGESIZE' => true,
    'SHOW_NAVIGATION_PANEL' => true,
    'SHOW_ROW_CHECKBOXES' => false,
    'SHOW_CHECK_ALL_CHECKBOXES' => false,
    'SHOW_SELECTED_COUNTER' => false,
    'SHOW_ACTION_PANEL' => false,
    'PAGE_SIZES' => [
        ['NAME' => '10', 'VALUE' => '10'], ['NAME' => '20', 'VALUE' => '20'],
        ['NAME' => '50', 'VALUE' => '50'], ['NAME' => '100', 'VALUE' => '100'],
    ],
    'AJAX_MODE' => 'Y',
    'AJAX_OPTION_JUMP' => 'N',
    'AJAX_OPTION_HISTORY' => 'N',
], $component);

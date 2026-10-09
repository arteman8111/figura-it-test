<?php

/**
 * AJAX-вход вкладки из задачи 4. Не принимает имя компонента или DEAL_ID от клиента.
 * Сессионная проверка защищает запрос, а компонент проверяет права на обе сущности.
 * Буфер нужен, чтобы ShowAjaxHead вывел зависимости грида, зарегистрированные при его рендере.
 */
use Bitrix\Main\Context;

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('PUBLIC_AJAX_MODE', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Cache-Control: no-store, private');
header('Content-Type: text/html; charset=UTF-8');
if (!$USER->IsAuthorized() || !check_bitrix_sessid()) {
    http_response_code(403);
    $APPLICATION->RestartBuffer();
    echo 'Доступ запрещён. Обновите карточку.';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    exit;
}

$request = Context::getCurrent()->getRequest();
ob_start();
$APPLICATION->IncludeComponent('figura:equipment.list', '', [
    'SMART_ENTITY_TYPE_ID' => (int)$request->getQuery('entityTypeId'),
    'SMART_ITEM_ID' => (int)$request->getQuery('itemId'),
    'CACHE_TYPE' => 'N',
], false, ['HIDE_ICONS' => 'Y']);
$content = ob_get_clean();
$APPLICATION->RestartBuffer();
$APPLICATION->ShowAjaxHead();
echo $content;
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';

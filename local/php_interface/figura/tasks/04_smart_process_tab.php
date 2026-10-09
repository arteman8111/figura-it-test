<?php
/**
 Задача 4. 
 Добавляем вкладку только в наш смарт-процесс.
 При открытии вкладки AJAX-скрипт запускает компонент и определяет сделку по связи CRM.
 */

namespace Figura\Equipment;

use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
    exit;

EventManager::getInstance()->addEventHandler('crm', 'onEntityDetailsTabsInitialized', function (Event $event) {

    $entityTypeId = (int) $event->getParameter('entityTypeID');
    if ($entityTypeId <= 0)
        return;
    $itemId = (int) $event->getParameter('entityID');
    $tabs = (array) $event->getParameter('tabs');

    $query = http_build_query([
        'entityTypeId' => $entityTypeId,
        'itemId' => $itemId,
        'site' => SITE_ID,
        'sessid' => bitrix_sessid(),
    ]);

    $tabs[] = [
        'id' => 'figura_equipment',
        'name' => 'Оборудование',
        'enabled' => $itemId > 0,
        'loader' => [
            'serviceUrl' => '/local/components/figura/equipment.list/lazyload.ajax.php?' . $query,
            'componentData' => ['template' => '.default'],
        ],
    ];
    return new EventResult(EventResult::SUCCESS, ['tabs' => $tabs]);
});

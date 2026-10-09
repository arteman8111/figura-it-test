<?php

declare(strict_types=1);

namespace Figura\Equipment;

/**
 * Задача 4. Подписывается на onEntityDetailsTabsInitialized и добавляет вкладку
 * только для созданного смарт-процесса. Loader загружает компонент по требованию.
 * В URL передаётся элемент смарт-процесса; DEAL_ID вычисляется сервером заново
 * при каждом запросе, поэтому смена связи не оставит таблицу на старой сделке.
 * Подключение обработчика: require_once figura/init.php из php_interface/init.php.
 * Документация события: https://dev.1c-bitrix.ru/api_d7/bitrix/crm/systemcomponents/crmentitydetails.php
 */

use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

final class SmartProcessTab
{
    public static function register(): void
    {
        EventManager::getInstance()->addEventHandler('crm', 'onEntityDetailsTabsInitialized', [self::class, 'addTab']);
    }

    public static function addTab(Event $event): ?EventResult
    {
        if (!Loader::includeModule('crm')) {
            return null;
        }
        $entityTypeId = (int)$event->getParameter('entityTypeID');
        if ($entityTypeId <= 0 || $entityTypeId !== Config::entityTypeId()) {
            return null;
        }
        $itemId = (int)$event->getParameter('entityID');
        $tabs = (array)$event->getParameter('tabs');
        foreach ($tabs as $tab) {
            if (($tab['id'] ?? '') === 'figura_equipment') {
                return null;
            }
        }
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
    }
}

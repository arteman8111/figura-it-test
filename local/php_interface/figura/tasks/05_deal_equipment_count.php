<?php

declare(strict_types=1);

namespace Figura\Equipment;

/**
 * Задача 5. OnProlog подключает счётчик только на странице сохранённой сделки.
 * COUNT выполняется в БД с обязательным UF_DEAL_ID и проверкой прав текущего пользователя.
 * JS добавляет информационный блок сразу после «Клиента»; это не сохраняемое UF-поле,
 * поэтому у него нет редактора, input, обработчика клика или дублируемого значения в CRM.
 * Признак выделения вычисляется на сервере по ID просматривающего пользователя (5).
 * Число обновляется при открытии/перезагрузке карточки. DOM-селекторы собраны в одном JS-файле.
 */

use Bitrix\Main\EventManager;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Web\Json;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

final class DealEquipmentCount
{
    public static function register(): void
    {
        EventManager::getInstance()->addEventHandler('main', 'OnProlog', [self::class, 'load']);
    }

    public static function load(): void
    {
        global $APPLICATION, $USER;

        if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
            return;
        }
        if (!preg_match('~^/crm/deal/details/([1-9][0-9]*)/(?:index\.php)?$~D', $APPLICATION->GetCurPage(false), $match)) {
            return;
        }
        try {
            Config::requireModules();
            $dealId = (int)$match[1];
            $count = EquipmentRepository::countForDeal($dealId);
            $settings = Json::encode([
                'dealId' => $dealId,
                'count' => $count,
                'highlight' => (int)$USER->GetID() === 5,
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            \CJSCore::Init(['core']);
            $asset = Asset::getInstance();
            $asset->addString('<script>window.FiguraDealEquipmentSettings = ' . $settings . ';</script>');
            $asset->addJs('/local/js/figura/equipment/deal-count.js');
            $asset->addCss('/local/js/figura/equipment/deal-count.css');
        } catch (\DomainException $exception) {
            // Недоступная сделка не должна раскрывать даже количество привязанного оборудования.
            return;
        } catch (\Throwable $exception) {
            AddMessage2Log((string)$exception, 'figura.equipment');
        }
    }
}

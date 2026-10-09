<?php

namespace Figura\Equipment;

use Bitrix\Main\EventManager;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Web\Json;

/**
 * Задача 5. Считаем оборудование при открытии сделки и подключаем JS/CSS.
 * JS вставляет обычный текстовый блок под «Клиентом». Пользователь 5 видит его выделенным.
 * Количество пересчитывается при перезагрузке карточки.
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

EventManager::getInstance()->addEventHandler('main', 'OnProlog', function () {
    global $APPLICATION, $USER;

    if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
        return;
    }
    if (!preg_match('~^/crm/deal/details/([1-9][0-9]*)/(?:index\.php)?$~D', $APPLICATION->GetCurPage(false), $match)) {
        return;
    }
    try {
        loadModules();
        $dealId = (int)$match[1];
        checkReadAccess(\CCrmOwnerType::Deal, $dealId);
        $dataClass = getEquipmentClass();
        $settings = Json::encode([
            'dealId' => $dealId,
            'count' => $dataClass::getCount(['=UF_DEAL_ID' => $dealId]),
            'highlight' => (int)$USER->GetID() === 5,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        \CJSCore::Init(['core']);
        $asset = Asset::getInstance();
        $asset->addString('<script>window.FiguraDealEquipmentSettings = ' . $settings . ';</script>');
        $asset->addJs('/local/js/figura/equipment/deal-count.js');
        $asset->addCss('/local/js/figura/equipment/deal-count.css');
    } catch (\DomainException $exception) {
        return;
    } catch (\Throwable $exception) {
        AddMessage2Log((string)$exception, 'figura.equipment');
    }
});

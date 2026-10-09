<?php

declare(strict_types=1);

namespace Figura\Equipment;

/**
 * Задача 2. Создаёт тип CRM «Учёт оборудования», тестовую сделку и связанный элемент.
 * Тип создаётся через TypeTable: его обработчики создают необходимые таблицы CRM.
 * Элементы сохраняются через Factory/Operation, чтобы сработала штатная логика CRM.
 * RelationManager связывает типы и конкретные элементы (сделка — родитель).
 * Смарт-процесс — сущность CRM; бизнес-процесс — сценарий действий над её элементом.
 * Дизайнер БП включён, но отдельный шаблон БП в задании не требуется.
 */

use Bitrix\Crm\ItemIdentifier;
use Bitrix\Crm\Model\Dynamic\TypeTable;
use Bitrix\Crm\Relation;
use Bitrix\Crm\RelationIdentifier;
use Bitrix\Crm\Service\Container;
use Bitrix\Main\Config\Option;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

final class CreateSmartProcess
{
    public static function ensureType(int $userId): int
    {
        $existingId = Config::entityTypeId();
        if ($existingId > 0) {
            return $existingId;
        }
        $entityTypeId = TypeTable::getNextAvailableEntityTypeId();
        if (!$entityTypeId) {
            throw new \RuntimeException('Нет свободного идентификатора смарт-процесса.');
        }
        $result = TypeTable::add([
            'TITLE' => 'Учёт оборудования',
            'CODE' => Config::TYPE_CODE,
            'ENTITY_TYPE_ID' => $entityTypeId,
            'CREATED_BY' => $userId,
            'IS_STAGES_ENABLED' => 'Y',
            'IS_CATEGORIES_ENABLED' => 'N',
            'IS_BIZ_PROC_ENABLED' => 'Y',
            'IS_AUTOMATION_ENABLED' => 'Y',
            'IS_SET_OPEN_PERMISSIONS' => 'N',
        ]);
        Config::check($result);

        return (int)$entityTypeId;
    }

    /** Запускается отдельным HTTP-запросом после создания структуры: метаданные ORM уже актуальны. */
    public static function seed(int $userId): array
    {
        $entityTypeId = Config::entityTypeId();
        if ($entityTypeId <= 0) {
            throw new \RuntimeException('Сначала создайте структуру.');
        }
        $container = Container::getInstance();
        $relations = $container->getRelationManager();
        $relationId = new RelationIdentifier(\CCrmOwnerType::Deal, $entityTypeId);
        if (!$relations->areTypesBound($relationId)) {
            Config::check($relations->bindTypes(Relation::create(\CCrmOwnerType::Deal, $entityTypeId)));
        }

        $dealFactory = $container->getFactory(\CCrmOwnerType::Deal);
        $factory = $container->getFactory($entityTypeId);
        if (!$dealFactory || !$factory) {
            throw new \RuntimeException('Не удалось получить фабрику CRM.');
        }
        $dealId = (int)Option::get('main', Config::OPTION_PREFIX . 'demo_deal_id', '0');
        if ($dealId <= 0 || !$dealFactory->getItem($dealId)) {
            $deal = $dealFactory->createItem([
                'TITLE' => 'Тестовая сделка: оборудование',
                'ASSIGNED_BY_ID' => $userId,
            ]);
            Config::check($dealFactory->getAddOperation($deal)->launch());
            $dealId = $deal->getId();
            Option::set('main', Config::OPTION_PREFIX . 'demo_deal_id', (string)$dealId);
        }

        $itemId = (int)Option::get('main', Config::OPTION_PREFIX . 'demo_item_id', '0');
        if ($itemId <= 0 || !$factory->getItem($itemId)) {
            $item = $factory->createItem([
                'TITLE' => 'Оборудование тестовой сделки',
                'ASSIGNED_BY_ID' => $userId,
            ]);
            Config::check($factory->getAddOperation($item)->launch());
            $itemId = $item->getId();
            Option::set('main', Config::OPTION_PREFIX . 'demo_item_id', (string)$itemId);
        }

        $parent = new ItemIdentifier(\CCrmOwnerType::Deal, $dealId);
        $child = new ItemIdentifier($entityTypeId, $itemId);
        if (!$relations->areItemsBound($parent, $child)) {
            Config::check($relations->bindItems($parent, $child));
        }

        return ['ENTITY_TYPE_ID' => $entityTypeId, 'DEAL_ID' => $dealId, 'ITEM_ID' => $itemId];
    }
}

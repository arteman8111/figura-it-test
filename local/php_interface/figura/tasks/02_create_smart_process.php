<?php

namespace Figura\Equipment;

use Bitrix\Crm\ItemIdentifier;
use Bitrix\Crm\Model\Dynamic\TypeTable;
use Bitrix\Crm\Relation;
use Bitrix\Crm\RelationIdentifier;
use Bitrix\Crm\Service\Container;
use Bitrix\Main\Config\Option;

/**
 * Задача 2. Создаём смарт-процесс, сделку и элемент. Сделка становится родителем элемента.
 * Для сохранения используем операции CRM, для связей — RelationManager.
 * ID тестовых записей сохраняем в опциях, чтобы при повторном запуске использовать их снова.
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

function createSmartProcess(int $userId): int
{
    $entityTypeId = getSmartProcessId();
    if ($entityTypeId > 0) {
        return $entityTypeId;
    }
    $entityTypeId = TypeTable::getNextAvailableEntityTypeId();
    if (!$entityTypeId) {
        throw new \RuntimeException('Нет свободного ID смарт-процесса.');
    }
    checkResult(TypeTable::add([
        'TITLE' => 'Учёт оборудования',
        'CODE' => TYPE_CODE,
        'ENTITY_TYPE_ID' => $entityTypeId,
        'CREATED_BY' => $userId,
        'IS_STAGES_ENABLED' => 'Y',
        'IS_CATEGORIES_ENABLED' => 'N',
        'IS_BIZ_PROC_ENABLED' => 'Y',
        'IS_AUTOMATION_ENABLED' => 'Y',
        'IS_SET_OPEN_PERMISSIONS' => 'N',
    ]));
    return (int)$entityTypeId;
}

function createTestDealAndItem(int $userId): array
{
    $entityTypeId = getSmartProcessId();
    if ($entityTypeId <= 0) {
        throw new \RuntimeException('Сначала создайте структуру.');
    }
    $container = Container::getInstance();
    $relations = $container->getRelationManager();
    if (!$relations->areTypesBound(new RelationIdentifier(\CCrmOwnerType::Deal, $entityTypeId))) {
        checkResult($relations->bindTypes(Relation::create(\CCrmOwnerType::Deal, $entityTypeId)));
    }

    $ids = [];
    foreach ([
        'deal' => [\CCrmOwnerType::Deal, 'Тестовая сделка: оборудование'],
        'item' => [$entityTypeId, 'Оборудование тестовой сделки'],
    ] as $key => [$typeId, $title]) {
        $factory = $container->getFactory($typeId);
        if (!$factory) {
            throw new \RuntimeException('Не найдена фабрика CRM для типа ' . $typeId);
        }
        $option = 'figura_equipment_demo_' . $key . '_id';
        $id = (int)Option::get('main', $option, '0');
        if ($id <= 0 || !$factory->getItem($id)) {
            $item = $factory->createItem(['TITLE' => $title, 'ASSIGNED_BY_ID' => $userId]);
            checkResult($factory->getAddOperation($item)->launch());
            $id = $item->getId();
            Option::set('main', $option, (string)$id);
        }
        $ids[$key] = $id;
    }

    $parent = new ItemIdentifier(\CCrmOwnerType::Deal, $ids['deal']);
    $child = new ItemIdentifier($entityTypeId, $ids['item']);
    if (!$relations->areItemsBound($parent, $child)) {
        checkResult($relations->bindItems($parent, $child));
    }
    return ['ENTITY_TYPE_ID' => $entityTypeId, 'DEAL_ID' => $ids['deal'], 'ITEM_ID' => $ids['item']];
}

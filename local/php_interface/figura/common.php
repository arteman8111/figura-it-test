<?php

namespace Figura\Equipment;

use Bitrix\Crm\ItemIdentifier;
use Bitrix\Crm\Model\Dynamic\TypeTable;
use Bitrix\Crm\Service\Container;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Result;

// Общие функции для работы с оборудованием и проверки прав CRM.
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

const HL_NAME = 'FiguraEquipment';
const HL_TABLE = 'b_figura_equipment';
const TYPE_CODE = 'FIGURA_EQUIPMENT';

function loadModules(): void
{
    if (!Loader::includeModule('crm') || !Loader::includeModule('highloadblock')) {
        throw new \RuntimeException('Нужны модули CRM и Highload-блоки.');
    }
}

function checkResult(Result $result): void
{
    if (!$result->isSuccess()) {
        throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
    }
}

function getSmartProcessId(): int
{
    $type = TypeTable::getList(['filter' => ['=CODE' => TYPE_CODE], 'limit' => 1])->fetch();
    return (int)($type['ENTITY_TYPE_ID'] ?? 0);
}

function getEquipmentBlock(): array
{
    $block = HighloadBlockTable::getList([
        'filter' => ['=NAME' => HL_NAME, '=TABLE_NAME' => HL_TABLE], 'limit' => 1,
    ])->fetch();
    if (!$block) {
        throw new \RuntimeException('Сначала создайте HL-блок через установщик.');
    }
    return $block;
}

function getEquipmentClass(): string
{
    static $dataClass;
    if ($dataClass === null) {
        $dataClass = HighloadBlockTable::compileEntity(getEquipmentBlock())->getDataClass();
    }
    return $dataClass;
}

function getEquipmentTypes(): array
{
    $field = \CUserTypeEntity::GetList([], [
        'ENTITY_ID' => 'HLBLOCK_' . getEquipmentBlock()['ID'], 'FIELD_NAME' => 'UF_TYPE',
    ])->Fetch();
    if (!$field) {
        throw new \RuntimeException('Не создано поле UF_TYPE.');
    }
    $types = [];
    $result = (new \CUserFieldEnum())->GetList(['SORT' => 'ASC', 'ID' => 'ASC'], ['USER_FIELD_ID' => $field['ID']]);
    while ($row = $result->Fetch()) {
        $types[(int)$row['ID']] = $row;
    }
    return $types;
}

function checkReadAccess(int $entityTypeId, int $entityId): void
{
    global $USER;
    if (!$USER->IsAuthorized() || $entityId <= 0) {
        throw new \DomainException('Элемент не найден или нет доступа.');
    }
    $container = Container::getInstance();
    $factory = $container->getFactory($entityTypeId);
    $permissions = $container->getUserPermissions((int)$USER->GetID());
    $canRead = method_exists($permissions, 'item')
        ? $permissions->item()->canRead($entityTypeId, $entityId)
        : $permissions->checkReadPermissions($entityTypeId, $entityId);
    if (!$canRead || !$factory || !$factory->getItem($entityId)) {
        throw new \DomainException('Элемент не найден или нет доступа.');
    }
}

function getLinkedDealId(int $entityTypeId, int $itemId): int
{
    if ($entityTypeId <= 0 || $entityTypeId !== getSmartProcessId()) {
        throw new \DomainException('Неизвестный смарт-процесс.');
    }
    checkReadAccess($entityTypeId, $itemId);
    $parents = Container::getInstance()->getRelationManager()->getParentElements(new ItemIdentifier($entityTypeId, $itemId));
    $dealIds = [];
    foreach ($parents as $parent) {
        if ($parent->getEntityTypeId() === \CCrmOwnerType::Deal) {
            $dealIds[$parent->getEntityId()] = $parent->getEntityId();
        }
    }
    if (count($dealIds) !== 1) {
        throw new \DomainException('Привяжите элемент к одной сделке.');
    }
    $dealId = (int)reset($dealIds);
    checkReadAccess(\CCrmOwnerType::Deal, $dealId);
    return $dealId;
}

<?php

declare(strict_types=1);

namespace Figura\Equipment;

/**
 * Общие настройки, доступ к HL-блоку и проверки прав для задач 1–5.
 * Подключается после пролога Битрикса. ORM сама не проверяет права CRM,
 * поэтому перед чтением оборудования обязательно вызывается Access::requireRead().
 */

use Bitrix\Crm\ItemIdentifier;
use Bitrix\Crm\Model\Dynamic\TypeTable;
use Bitrix\Crm\Service\Container;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Result;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

final class Config
{
    public const HL_NAME = 'FiguraEquipment';
    public const HL_TABLE = 'b_figura_equipment';
    public const TYPE_CODE = 'FIGURA_EQUIPMENT';
    public const OPTION_PREFIX = 'figura_equipment_';

    public static function requireModules(): void
    {
        foreach (['crm', 'highloadblock'] as $module) {
            if (!Loader::includeModule($module)) {
                throw new \RuntimeException('Не установлен модуль ' . $module);
            }
        }
    }

    public static function entityTypeId(): int
    {
        $row = TypeTable::getList([
            'select' => ['ENTITY_TYPE_ID'],
            'filter' => ['=CODE' => self::TYPE_CODE],
            'limit' => 1,
        ])->fetch();

        return (int)($row['ENTITY_TYPE_ID'] ?? 0);
    }

    public static function check(Result $result): void
    {
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }
}

final class Access
{
    public static function requireRead(int $entityTypeId, int $entityId): void
    {
        global $USER;

        if (!$USER instanceof \CUser || !$USER->IsAuthorized() || $entityId <= 0) {
            throw new \DomainException('Элемент не найден или нет прав на его просмотр.');
        }

        $factory = Container::getInstance()->getFactory($entityTypeId);
        $permissions = Container::getInstance()->getUserPermissions((int)$USER->GetID());
        // В новых версиях CRM права вынесены в item(); старый API оставлен для коробок до миграции.
        $canRead = method_exists($permissions, 'item')
            ? $permissions->item()->canRead($entityTypeId, $entityId)
            : $permissions->checkReadPermissions($entityTypeId, $entityId);

        if (!$canRead || !$factory || !$factory->getItem($entityId)) {
            throw new \DomainException('Элемент не найден или нет прав на его просмотр.');
        }
    }

    public static function linkedDealId(int $entityTypeId, int $itemId): int
    {
        if ($entityTypeId <= 0 || $entityTypeId !== Config::entityTypeId()) {
            throw new \DomainException('Неизвестный смарт-процесс.');
        }

        self::requireRead($entityTypeId, $itemId);
        $parents = Container::getInstance()->getRelationManager()->getParentElements(
            new ItemIdentifier($entityTypeId, $itemId)
        );
        $dealIds = [];
        foreach ($parents as $parent) {
            if ($parent->getEntityTypeId() === \CCrmOwnerType::Deal) {
                $dealIds[$parent->getEntityId()] = $parent->getEntityId();
            }
        }

        if (count($dealIds) !== 1) {
            throw new \DomainException('Для просмотра оборудования привяжите элемент ровно к одной сделке.');
        }

        $dealId = (int)reset($dealIds);
        self::requireRead(\CCrmOwnerType::Deal, $dealId);

        return $dealId;
    }
}

final class EquipmentRepository
{
    public static function block(): array
    {
        $block = HighloadBlockTable::getList([
            'filter' => ['=NAME' => Config::HL_NAME, '=TABLE_NAME' => Config::HL_TABLE],
            'limit' => 1,
        ])->fetch();

        if (!$block) {
            throw new \RuntimeException('HL-блок не создан. Выполните первый шаг установки.');
        }

        return $block;
    }

    public static function dataClass(): string
    {
        static $dataClass;
        if ($dataClass === null) {
            $dataClass = HighloadBlockTable::compileEntity(self::block())->getDataClass();
        }

        return $dataClass;
    }

    /** ID списка хранится в UF_TYPE; XML_ID нужен только для устойчивого заполнения демоданных. */
    public static function types(): array
    {
        $field = \CUserTypeEntity::GetList([], [
            'ENTITY_ID' => 'HLBLOCK_' . self::block()['ID'],
            'FIELD_NAME' => 'UF_TYPE',
        ])->Fetch();
        if (!$field) {
            throw new \RuntimeException('Не создано поле UF_TYPE.');
        }

        $result = [];
        $values = (new \CUserFieldEnum())->GetList(['SORT' => 'ASC', 'ID' => 'ASC'], ['USER_FIELD_ID' => $field['ID']]);
        while ($value = $values->Fetch()) {
            $result[(int)$value['ID']] = $value;
        }

        return $result;
    }

    public static function countForDeal(int $dealId): int
    {
        Access::requireRead(\CCrmOwnerType::Deal, $dealId);
        $dataClass = self::dataClass();

        return $dataClass::getCount(['=UF_DEAL_ID' => $dealId]);
    }
}

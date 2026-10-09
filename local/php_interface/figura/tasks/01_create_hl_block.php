<?php

declare(strict_types=1);

namespace Figura\Equipment;

/**
 * Задача 1. Создаёт HL-блок «Оборудование» и четыре пользовательских поля.
 * Техническое имя HL-блока латинское; русское название хранится в LangTable.
 * UF_TYPE — enumeration, а UF_DEAL_ID — integer по брифу, не CRM-поле.
 * run() вызывается установщиком на шаге 1, seed() — на шаге 2 после создания сделки.
 * Повторный последовательный запуск сохраняет существующие поля и тестовые записи.
 */

use Bitrix\Highloadblock\HighloadBlockLangTable;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Type\DateTime;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

final class CreateHighloadBlock
{
    public static function run(): int
    {
        $block = HighloadBlockTable::getList([
            'filter' => ['LOGIC' => 'OR', '=NAME' => Config::HL_NAME, '=TABLE_NAME' => Config::HL_TABLE],
        ])->fetch();
        if ($block && ($block['NAME'] !== Config::HL_NAME || $block['TABLE_NAME'] !== Config::HL_TABLE)) {
            throw new \RuntimeException('Имя или таблица HL-блока уже заняты другой сущностью.');
        }
        if (!$block) {
            $result = HighloadBlockTable::add(['NAME' => Config::HL_NAME, 'TABLE_NAME' => Config::HL_TABLE]);
            Config::check($result);
            $block = ['ID' => (int)$result->getId()];
        }
        $blockId = (int)$block['ID'];
        $langKey = ['ID' => $blockId, 'LID' => 'ru'];
        if (!HighloadBlockLangTable::getByPrimary($langKey)->fetch()) {
            Config::check(HighloadBlockLangTable::add($langKey + ['NAME' => 'Оборудование']));
        }

        $fields = [
            'UF_NAME' => ['string', 'Название'],
            'UF_TYPE' => ['enumeration', 'Тип оборудования'],
            'UF_CREATED_TIME' => ['datetime', 'Дата создания'],
            'UF_DEAL_ID' => ['integer', 'ID сделки'],
        ];
        $fieldIds = [];
        $userFields = new \CUserTypeEntity();
        foreach ($fields as $name => [$type, $title]) {
            $existing = \CUserTypeEntity::GetList([], [
                'ENTITY_ID' => 'HLBLOCK_' . $blockId, 'FIELD_NAME' => $name,
            ])->Fetch();
            if ($existing) {
                if ($existing['USER_TYPE_ID'] !== $type || $existing['MULTIPLE'] !== 'N') {
                    throw new \RuntimeException('Поле ' . $name . ' имеет несовместимый тип.');
                }
                $fieldIds[$name] = (int)$existing['ID'];
                continue;
            }
            $settings = $type === 'datetime' ? ['DEFAULT_VALUE' => ['TYPE' => 'NOW', 'VALUE' => '']] : [];
            $id = $userFields->Add([
                'ENTITY_ID' => 'HLBLOCK_' . $blockId,
                'FIELD_NAME' => $name,
                'USER_TYPE_ID' => $type,
                'XML_ID' => $name,
                'SORT' => (count($fieldIds) + 1) * 100,
                'MULTIPLE' => 'N',
                'MANDATORY' => 'Y',
                'SHOW_FILTER' => 'I',
                'SHOW_IN_LIST' => 'Y',
                'EDIT_IN_LIST' => 'Y',
                'SETTINGS' => $settings,
                'EDIT_FORM_LABEL' => ['ru' => $title, 'en' => $title],
                'LIST_COLUMN_LABEL' => ['ru' => $title, 'en' => $title],
                'LIST_FILTER_LABEL' => ['ru' => $title, 'en' => $title],
            ]);
            if (!$id) {
                global $APPLICATION;
                throw new \RuntimeException($APPLICATION->GetException()?->GetString() ?? ('Ошибка создания ' . $name));
            }
            $fieldIds[$name] = (int)$id;
        }

        // Сохраняем ID существующих вариантов, чтобы не нарушить уже заполненные UF_TYPE.
        $values = [];
        $byXmlId = [];
        $enum = new \CUserFieldEnum();
        $cursor = $enum->GetList([], ['USER_FIELD_ID' => $fieldIds['UF_TYPE']]);
        while ($row = $cursor->Fetch()) {
            $values[$row['ID']] = array_intersect_key($row, array_flip(['VALUE', 'XML_ID', 'SORT', 'DEF']));
            $byXmlId[$row['XML_ID']] = $row['ID'];
        }
        $sort = 100;
        foreach (['router' => 'Роутер', 'laptop' => 'Ноутбук', 'monitor' => 'Монитор'] as $xmlId => $title) {
            $key = $byXmlId[$xmlId] ?? ('n' . count($values));
            $values[$key] = ['XML_ID' => $xmlId, 'VALUE' => $title, 'SORT' => $sort, 'DEF' => 'N'];
            $sort += 100;
        }
        if (!$enum->SetEnumValues($fieldIds['UF_TYPE'], $values)) {
            throw new \RuntimeException('Не удалось сохранить варианты UF_TYPE.');
        }

        return $blockId;
    }

    public static function seed(int $dealId): array
    {
        Access::requireRead(\CCrmOwnerType::Deal, $dealId);
        $dataClass = EquipmentRepository::dataClass();
        $types = [];
        foreach (EquipmentRepository::types() as $id => $value) {
            $types[$value['XML_ID']] = $id;
        }
        $ids = [];
        foreach (['router' => 'Тестовый роутер', 'laptop' => 'Тестовый ноутбук', 'monitor' => 'Тестовый монитор'] as $type => $name) {
            if (!isset($types[$type])) {
                throw new \RuntimeException('В списке UF_TYPE отсутствует ' . $type);
            }
            $fields = ['UF_NAME' => $name, 'UF_TYPE' => $types[$type], 'UF_DEAL_ID' => $dealId];
            $existing = $dataClass::getList(['select' => ['ID'], 'filter' => [
                '=UF_NAME' => $name, '=UF_TYPE' => $types[$type], '=UF_DEAL_ID' => $dealId,
            ], 'limit' => 1])->fetch();
            if ($existing) {
                $ids[] = (int)$existing['ID'];
                continue;
            }
            $result = $dataClass::add($fields + ['UF_CREATED_TIME' => new DateTime()]);
            Config::check($result);
            $ids[] = (int)$result->getId();
        }

        return $ids;
    }
}

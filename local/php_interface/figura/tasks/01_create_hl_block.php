<?php
/**
 * Задача 1. 
Создайте HL-блок «Оборудование» со следующими полями:
•	UF_NAME — «Название», строка.
•	UF_TYPE — «Тип оборудования», список со значениями: 
o	Роутер
o	Ноутбук
o	Монитор
•	UF_CREATED_TIME — «Дата создания», дата/время.
•	UF_DEAL_ID — «ID сделки», число.
Наполните HL-блок тестовыми записями.
 */

namespace Figura\Equipment;

use Bitrix\Highloadblock\HighloadBlockLangTable;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Type\DateTime;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
    exit;

// создание HL-блока и настрйока его
function createEquipmentBlock(): int
{
    $block = HighloadBlockTable::getList([
        'filter' => [
            'LOGIC' => 'OR',
            '=NAME' => HL_NAME,
            '=TABLE_NAME' => HL_TABLE
        ],
    ])->fetch();
    if ($block && ($block['NAME'] !== HL_NAME || $block['TABLE_NAME'] !== HL_TABLE)) {
        throw new \RuntimeException('Имя или таблица HL-блока уже заняты.');
    }
    if (!$block) {
        $result = HighloadBlockTable::add(['NAME' => HL_NAME, 'TABLE_NAME' => HL_TABLE]);
        checkResult($result);
        $block = ['ID' => $result->getId()];
    }
    $blockId = (int) $block['ID'];
    $langKey = ['ID' => $blockId, 'LID' => 'ru'];

    // проверка ru
    if (!HighloadBlockLangTable::getByPrimary($langKey)->fetch()) {
        checkResult(HighloadBlockLangTable::add($langKey + ['NAME' => 'Оборудование']));
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
        $existing = \CUserTypeEntity::GetList(
            [],
            [
                'ENTITY_ID' => 'HLBLOCK_' . $blockId,
                'FIELD_NAME' => $name,
            ]
        )->Fetch();

        if ($existing) {
            if ($existing['USER_TYPE_ID'] !== $type || $existing['MULTIPLE'] !== 'N') {
                throw new \RuntimeException('Неверный тип поля ' . $name);
            }
            $fieldIds[$name] = (int) $existing['ID'];
            continue;
        }

        $id = $userFields->Add([
            'ENTITY_ID' => 'HLBLOCK_' . $blockId,
            'FIELD_NAME' => $name,
            'USER_TYPE_ID' => $type,
            'SORT' => (count($fieldIds) + 1) * 100,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'Y',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'EDIT_FORM_LABEL' => ['ru' => $title],
            'LIST_COLUMN_LABEL' => ['ru' => $title],
            'LIST_FILTER_LABEL' => ['ru' => $title],
            'SETTINGS' => ['DEFAULT_VALUE' => ['TYPE' => 'NOW', 'VALUE' => '']]
        ]);
        if (!$id) {
            global $APPLICATION;
            throw new \RuntimeException($APPLICATION->GetException()?->GetString());
        }
        $fieldIds[$name] = (int) $id;
    }

    $enum = new \CUserFieldEnum();
    $values = [];
    $existingTypes = $enum->GetList([], ['USER_FIELD_ID' => $fieldIds['UF_TYPE']]);
    while ($row = $existingTypes->Fetch()) {
        $values[$row['ID']] = ['VALUE' => $row['VALUE'], 'XML_ID' => $row['XML_ID'], 'SORT' => $row['SORT'], 'DEF' => $row['DEF']];
    }
    $xmlIds = array_column($values, 'XML_ID');
    foreach (['router' => 'Роутер', 'laptop' => 'Ноутбук', 'monitor' => 'Монитор'] as $xmlId => $name) {
        if (!in_array($xmlId, $xmlIds, true)) {
            $values['n' . count($values)] = ['XML_ID' => $xmlId, 'VALUE' => $name, 'SORT' => (count($values) + 1) * 100, 'DEF' => 'N'];
        }
    }
    if (!$enum->SetEnumValues($fieldIds['UF_TYPE'], $values)) {
        throw new \RuntimeException('Не удалось сохранить типы оборудования.');
    }
    return $blockId;
}

// Можно передать свои названия оборудования вторым аргументом.
function addTestEquipment(int $dealId): void
{
    checkReadAccess(\CCrmOwnerType::Deal, $dealId);
    $dataClass = getEquipmentClass();

    // хардкод пример
    $names = [
        'router' => 'Тестовый роутер',
        'laptop' => 'Тестовый ноутбук',
        'monitor' => 'Тестовый монитор',
    ];

    foreach (getEquipmentTypes() as $typeId => $type) {
        if (!isset($names[$type['XML_ID']]))
            continue;

        $fields = [
            'UF_NAME' => $names[$type['XML_ID']],
            'UF_TYPE' => $typeId,
            'UF_DEAL_ID' => $dealId
        ];

        if (!$dataClass::getList(['filter' => $fields, 'limit' => 1])->fetch()) {
            checkResult($dataClass::add($fields + ['UF_CREATED_TIME' => new DateTime()]));
        }
    }
}

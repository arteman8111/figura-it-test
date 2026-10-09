<?php
/**
Задача 3. Получаем оборудование из HL-блока и передаём строки в шаблон с гридом и фильтром.
 Условие по сделке всегда добавляем на сервере. Сброс фильтра его не убирает.
 */

namespace Figura\Equipment;

use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
    exit;

// Собираем условия для поиска оборудования в HL-блоке
function buildEquipmentFilter(int $dealId, array $values): array
{
    if ($dealId <= 0) {
        throw new \DomainException('Не задана сделка.');
    }
    $filter = ['=UF_DEAL_ID' => $dealId];

    foreach (['FIND', 'UF_NAME'] as $key) {
        if (isset($values[$key]) && is_scalar($values[$key]) && trim((string) $values[$key]) !== '') {
            $filter[] = ['%UF_NAME' => trim((string) $values[$key])];
        }
    }
    if (isset($values['UF_TYPE']) && $values['UF_TYPE'] !== '') {
        $typeId = filter_var($values['UF_TYPE'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($typeId === false) {
            throw new \DomainException('Некорректный тип оборудования.');
        }
        $filter['=UF_TYPE'] = $typeId;
    }
    foreach (['from' => '>=', 'to' => '<='] as $suffix => $operator) {
        $value = $values['UF_CREATED_TIME_' . $suffix] ?? '';
        if ($value === '') {
            continue;
        }
        if (!is_string($value)) {
            throw new \DomainException('Некорректная дата.');
        }
        try {
            $date = DateTime::createFromUserTime($value);
        } catch (\Throwable $exception) {
            throw new \DomainException('Некорректная дата.', 0, $exception);
        }
        if (!$date) {
            throw new \DomainException('Некорректная дата.');
        }
        $filter[$operator . 'UF_CREATED_TIME'] = $date;
    }
    return $filter;
}

// сортировка для запроса к HL-блоку
function getEquipmentOrder(array $sort): array
{
    $field = array_key_first($sort);
    if (!in_array($field, ['ID', 'UF_NAME', 'UF_TYPE', 'UF_CREATED_TIME', 'UF_DEAL_ID'], true)) {
        return ['ID' => 'DESC'];
    }
    $direction = is_string($sort[$field]) && strtoupper($sort[$field]) === 'ASC' ? 'ASC' : 'DESC';
    return [$field => $direction] + ['ID' => 'DESC'];
}

// полная логика для вывода таблицы оборудования
function showEquipmentList(\CBitrixComponent $component): void
{
    try {
        loadModules();
        $params = $component->arParams;
        if ((int) ($params['SMART_ITEM_ID'] ?? 0) > 0) {
            $dealId = getLinkedDealId((int) ($params['SMART_ENTITY_TYPE_ID'] ?? 0), (int) $params['SMART_ITEM_ID']);
        } else {
            $dealId = (int) ($params['DEAL_ID'] ?? 0);
            checkReadAccess(\CCrmOwnerType::Deal, $dealId);
        }

        $gridId = 'FIGURA_EQUIPMENT_DEAL_' . $dealId;
        $types = array_column(getEquipmentTypes(), 'VALUE', 'ID');
        $fields = [
            ['id' => 'UF_NAME', 'name' => 'Название', 'type' => 'string', 'default' => true],
            ['id' => 'UF_TYPE', 'name' => 'Тип оборудования', 'type' => 'list', 'items' => $types, 'default' => true],
            ['id' => 'UF_CREATED_TIME', 'name' => 'Дата создания', 'type' => 'date', 'default' => true],
        ];
        $filter = buildEquipmentFilter($dealId, (new FilterOptions($gridId))->getFilter($fields));
        $gridOptions = new GridOptions($gridId);
        $sorting = $gridOptions->getSorting(['sort' => ['ID' => 'desc'], 'vars' => ['by' => 'by', 'order' => 'order']]);
        $order = getEquipmentOrder($sorting['sort']);
        $navParams = $gridOptions->getNavParams(['nPageSize' => 20]);
        $pageSize = in_array((int) $navParams['nPageSize'], [10, 20, 50, 100], true) ? (int) $navParams['nPageSize'] : 20;
        $nav = new PageNavigation($gridId);
        $nav->allowAllRecords(false)->setPageSize($pageSize)->initFromUri();

        $dataClass = getEquipmentClass();
        $total = $dataClass::getCount($filter);
        $nav->setRecordCount($total);
        $result = $dataClass::getList([
            'select' => ['ID', 'UF_NAME', 'UF_TYPE', 'UF_CREATED_TIME', 'UF_DEAL_ID'],
            'filter' => $filter,
            'order' => $order,
            'limit' => $nav->getLimit(),
            'offset' => $nav->getOffset(),
        ]);
        $rows = [];
        while ($row = $result->fetch()) {
            $rows[] = [
                'id' => (int) $row['ID'],
                'data' => $row,
                'columns' => [
                    'ID' => (int) $row['ID'],
                    'UF_NAME' => htmlspecialcharsbx((string) $row['UF_NAME']),
                    'UF_TYPE' => htmlspecialcharsbx((string) ($types[$row['UF_TYPE']] ?? 'Не указан')),
                    'UF_CREATED_TIME' => htmlspecialcharsbx((string) $row['UF_CREATED_TIME']),
                    'UF_DEAL_ID' => (int) $row['UF_DEAL_ID'],
                ],
            ];
        }
        $component->arResult = [
            'GRID_ID' => $gridId,
            'FILTER_FIELDS' => $fields,
            'ROWS' => $rows,
            'SORT' => $order,
            'SORT_VARS' => $sorting['vars'],
            'NAV_OBJECT' => $nav,
            'TOTAL' => $total,
        ];
        $component->includeComponentTemplate();
    } catch (\DomainException $exception) {
        ShowError(htmlspecialcharsbx($exception->getMessage()));
    } catch (\Throwable $exception) {
        AddMessage2Log((string) $exception, 'figura.equipment');
        ShowError('Не удалось загрузить оборудование. Проверьте установку.');
    }
}

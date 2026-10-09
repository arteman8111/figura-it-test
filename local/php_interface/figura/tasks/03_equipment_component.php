<?php

declare(strict_types=1);

namespace Figura\Equipment;

/**
 * Задача 3. Логика компонента figura:equipment.list.
 * main.ui.filter сохраняет настройки, EquipmentQuery переводит их в условия ORM.
 * Обязательное условие по сделке задаётся сервером и не может быть сброшено фильтром.
 * main.ui.grid получает только одну страницу, сортировка ограничена известными полями.
 * Представление находится в templates/.default/template.php, вход — class.php.
 * Кеш данных отключён: права и актуальная привязка проверяются при каждом AJAX-запросе.
 */

use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

final class EquipmentQuery
{
    public static function filter(int $dealId, array $values): array
    {
        if ($dealId <= 0) {
            throw new \DomainException('Не задана сделка.');
        }
        $filter = ['=UF_DEAL_ID' => $dealId];
        foreach (['FIND', 'UF_NAME'] as $key) {
            if (isset($values[$key]) && is_scalar($values[$key]) && trim((string)$values[$key]) !== '') {
                // Отдельные группы AND: поиск и фильтр по названию могут использоваться вместе.
                $filter[] = ['%UF_NAME' => trim((string)$values[$key])];
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
                throw new \DomainException('Некорректная дата в фильтре.');
            }
            try {
                $date = DateTime::createFromUserTime($value);
            } catch (\Throwable $exception) {
                throw new \DomainException('Некорректная дата в фильтре.', 0, $exception);
            }
            if (!$date) {
                throw new \DomainException('Некорректная дата в фильтре.');
            }
            $filter[$operator . 'UF_CREATED_TIME'] = $date;
        }

        return $filter;
    }

    public static function order(array $requested): array
    {
        $allowed = ['ID', 'UF_NAME', 'UF_TYPE', 'UF_CREATED_TIME', 'UF_DEAL_ID'];
        $field = array_key_first($requested);
        if (!in_array($field, $allowed, true)) {
            return ['ID' => 'DESC'];
        }
        $direction = is_string($requested[$field]) && strtoupper($requested[$field]) === 'ASC' ? 'ASC' : 'DESC';

        // ID обеспечивает стабильный порядок при одинаковых значениях основной колонки.
        return [$field => $direction] + ['ID' => 'DESC'];
    }
}

final class EquipmentList
{
    public static function execute(\CBitrixComponent $component): void
    {
        try {
            Config::requireModules();
            $params = $component->arParams;
            if ((int)($params['SMART_ITEM_ID'] ?? 0) > 0) {
                $dealId = Access::linkedDealId((int)($params['SMART_ENTITY_TYPE_ID'] ?? 0), (int)$params['SMART_ITEM_ID']);
            } else {
                $dealId = (int)($params['DEAL_ID'] ?? 0);
                Access::requireRead(\CCrmOwnerType::Deal, $dealId);
            }

            $gridId = 'FIGURA_EQUIPMENT_DEAL_' . $dealId;
            $types = [];
            foreach (EquipmentRepository::types() as $id => $type) {
                $types[$id] = $type['VALUE'];
            }
            $fields = [
                ['id' => 'UF_NAME', 'name' => 'Название', 'type' => 'string', 'default' => true],
                ['id' => 'UF_TYPE', 'name' => 'Тип оборудования', 'type' => 'list', 'items' => $types, 'default' => true],
                ['id' => 'UF_CREATED_TIME', 'name' => 'Дата создания', 'type' => 'date', 'default' => true],
            ];
            $filter = EquipmentQuery::filter($dealId, (new FilterOptions($gridId))->getFilter($fields));
            $gridOptions = new GridOptions($gridId);
            $sorting = $gridOptions->getSorting(['sort' => ['ID' => 'desc'], 'vars' => ['by' => 'by', 'order' => 'order']]);
            $order = EquipmentQuery::order($sorting['sort']);
            $navParams = $gridOptions->getNavParams(['nPageSize' => 20]);
            $pageSize = (int)$navParams['nPageSize'];
            $pageSize = in_array($pageSize, [10, 20, 50, 100], true) ? $pageSize : 20;
            $nav = new PageNavigation($gridId);
            $nav->allowAllRecords(false)->setPageSize($pageSize)->initFromUri();

            $dataClass = EquipmentRepository::dataClass();
            $total = $dataClass::getCount($filter);
            $nav->setRecordCount($total);
            $cursor = $dataClass::getList([
                'select' => ['ID', 'UF_NAME', 'UF_TYPE', 'UF_CREATED_TIME', 'UF_DEAL_ID'],
                'filter' => $filter,
                'order' => $order,
                'limit' => $nav->getLimit(),
                'offset' => $nav->getOffset(),
            ]);
            $rows = [];
            while ($row = $cursor->fetch()) {
                $rows[] = [
                    'id' => (int)$row['ID'],
                    'data' => $row,
                    // Грид принимает HTML в columns, поэтому строки экранируются здесь.
                    'columns' => [
                        'ID' => (int)$row['ID'],
                        'UF_NAME' => htmlspecialcharsbx((string)$row['UF_NAME']),
                        'UF_TYPE' => htmlspecialcharsbx((string)($types[(int)$row['UF_TYPE']] ?? 'Не указан')),
                        'UF_CREATED_TIME' => htmlspecialcharsbx((string)$row['UF_CREATED_TIME']),
                        'UF_DEAL_ID' => (int)$row['UF_DEAL_ID'],
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
                'DEAL_ID' => $dealId,
            ];
            $component->includeComponentTemplate();
        } catch (\DomainException $exception) {
            ShowError(htmlspecialcharsbx($exception->getMessage()));
        } catch (\Throwable $exception) {
            AddMessage2Log((string)$exception, 'figura.equipment');
            ShowError('Не удалось загрузить оборудование. Проверьте установку и журнал ошибок.');
        }
    }
}

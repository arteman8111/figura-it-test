<?php

declare(strict_types=1);

/**
 * Автономные проверки границы фильтра и сортировки задачи 3: php tests/query_test.php.
 * Ядро Bitrix не имитируется. Ветки с DateTime, БД, правами и UI проверяются на коробке.
 * B_PROLOG_INCLUDED разрешает загрузить файл с классами, но не запускает компонент.
 */
use Figura\Equipment\EquipmentQuery;

define('B_PROLOG_INCLUDED', true);
require dirname(__DIR__) . '/local/php_interface/figura/tasks/03_equipment_component.php';

$checks = 0;
function same(mixed $actual, mixed $expected, string $description): void
{
    global $checks;
    if ($actual !== $expected) {
        throw new RuntimeException($description . '\n' . var_export($actual, true));
    }
    $checks++;
}

function rejects(callable $action, string $description): void
{
    global $checks;
    try {
        $action();
    } catch (DomainException $exception) {
        $checks++;
        return;
    }
    throw new RuntimeException($description);
}

same(EquipmentQuery::filter(42, []), ['=UF_DEAL_ID' => 42], 'Пустой фильтр остаётся в сделке.');
same(EquipmentQuery::filter(42, [
    'UF_DEAL_ID' => 99, '=UF_DEAL_ID' => 99, 'LOGIC' => 'OR', '=ID' => 1,
]), ['=UF_DEAL_ID' => 42], 'Пользователь не может подменить область выборки или ORM-условия.');
same(EquipmentQuery::filter(42, ['FIND' => ' Роутер ', 'UF_NAME' => ' Тестовый ', 'UF_TYPE' => '7']), [
    '=UF_DEAL_ID' => 42, ['%UF_NAME' => 'Роутер'], ['%UF_NAME' => 'Тестовый'], '=UF_TYPE' => 7,
], 'Поиск, название и тип сочетаются через AND.');
same(EquipmentQuery::filter(42, ['UF_TYPE' => '', 'UF_NAME' => '   ']), ['=UF_DEAL_ID' => 42], 'Сброс полей фильтра.');
rejects(fn() => EquipmentQuery::filter(0, []), 'Нельзя запросить все сделки через ID 0.');
rejects(fn() => EquipmentQuery::filter(-1, []), 'Отрицательная сделка запрещена.');
rejects(fn() => EquipmentQuery::filter(42, ['UF_TYPE' => '1 OR 1=1']), 'Недопустимый ID списка отклоняется.');
rejects(fn() => EquipmentQuery::filter(42, ['UF_TYPE' => ['1', '2']]), 'Массив не является одиночным значением списка.');
rejects(fn() => EquipmentQuery::filter(42, ['UF_CREATED_TIME_from' => []]), 'Дата не может быть массивом.');
same(EquipmentQuery::order(['UF_NAME' => 'asc']), ['UF_NAME' => 'ASC', 'ID' => 'DESC'], 'Стабильная сортировка.');
same(EquipmentQuery::order(['ID' => 'ASC']), ['ID' => 'ASC'], 'Направление сортировки ID не перезаписывается.');
same(EquipmentQuery::order(['UNKNOWN' => 'ASC']), ['ID' => 'DESC'], 'Неизвестная колонка игнорируется.');
same(EquipmentQuery::order(['ID' => 'DESC; DROP TABLE b_figura_equipment']), ['ID' => 'DESC'], 'Направление не становится SQL.');
same(EquipmentQuery::order([]), ['ID' => 'DESC'], 'Сортировка по умолчанию.');

echo 'OK: ' . $checks . " checks\n";

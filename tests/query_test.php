<?php

declare(strict_types=1);

/**
 * Проверки фильтра и сортировки: php tests/query_test.php.
 * Даты, права, БД и интерфейс нужно проверять на коробке.
 */
use function Figura\Equipment\buildEquipmentFilter;
use function Figura\Equipment\getEquipmentOrder;

define('B_PROLOG_INCLUDED', true);
require dirname(__DIR__) . '/local/php_interface/figura/tasks/03_equipment_component.php';

$checks = 0;
function same(mixed $actual, mixed $expected, string $description): void
{
    global $checks;
    if ($actual !== $expected) {
        throw new RuntimeException($description . "\n" . var_export($actual, true));
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

same(buildEquipmentFilter(42, []), ['=UF_DEAL_ID' => 42], 'Пустой фильтр остаётся в сделке.');
same(buildEquipmentFilter(42, [
    'UF_DEAL_ID' => 99, '=UF_DEAL_ID' => 99, 'LOGIC' => 'OR', '=ID' => 1,
]), ['=UF_DEAL_ID' => 42], 'Пользователь не может подменить область выборки или ORM-условия.');
same(buildEquipmentFilter(42, ['FIND' => ' Роутер ', 'UF_NAME' => ' Тестовый ', 'UF_TYPE' => '7']), [
    '=UF_DEAL_ID' => 42, ['%UF_NAME' => 'Роутер'], ['%UF_NAME' => 'Тестовый'], '=UF_TYPE' => 7,
], 'Поиск, название и тип сочетаются через AND.');
same(buildEquipmentFilter(42, ['UF_TYPE' => '', 'UF_NAME' => '   ']), ['=UF_DEAL_ID' => 42], 'Сброс полей фильтра.');
rejects(fn() => buildEquipmentFilter(0, []), 'Нельзя запросить все сделки через ID 0.');
rejects(fn() => buildEquipmentFilter(-1, []), 'Отрицательная сделка запрещена.');
rejects(fn() => buildEquipmentFilter(42, ['UF_TYPE' => '1 OR 1=1']), 'Недопустимый ID списка отклоняется.');
rejects(fn() => buildEquipmentFilter(42, ['UF_TYPE' => ['1', '2']]), 'Массив не является одиночным значением списка.');
rejects(fn() => buildEquipmentFilter(42, ['UF_CREATED_TIME_from' => []]), 'Дата не может быть массивом.');
same(getEquipmentOrder(['UF_NAME' => 'asc']), ['UF_NAME' => 'ASC', 'ID' => 'DESC'], 'Стабильная сортировка.');
same(getEquipmentOrder(['ID' => 'ASC']), ['ID' => 'ASC'], 'Направление сортировки ID не перезаписывается.');
same(getEquipmentOrder(['UNKNOWN' => 'ASC']), ['ID' => 'DESC'], 'Неизвестная колонка игнорируется.');
same(getEquipmentOrder(['ID' => 'DESC; DROP TABLE b_figura_equipment']), ['ID' => 'DESC'], 'Направление не становится SQL.');
same(getEquipmentOrder([]), ['ID' => 'DESC'], 'Сортировка по умолчанию.');

echo 'OK: ' . $checks . " checks\n";

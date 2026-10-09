<?php

/**
 * Установщик задач 1–2. Структура и данные создаются в два шага,
 * чтобы между запросами обновились метаданные ORM. Запускать администратором.
 */
use Bitrix\Crm\Service\Container;
use Bitrix\Main\Context;
use function Figura\Equipment\loadModules;
use function Figura\Equipment\createEquipmentBlock;
use function Figura\Equipment\createSmartProcess;
use function Figura\Equipment\createTestDealAndItem;
use function Figura\Equipment\addTestEquipment;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
if (!$USER->IsAuthorized() || !$USER->IsAdmin()) {
    http_response_code(403);
    echo 'Установка доступна только администратору.';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/figura/common.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/figura/tasks/01_create_hl_block.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/figura/tasks/02_create_smart_process.php';

$message = '';
$error = '';
$demo = null;
$request = Context::getCurrent()->getRequest();
if ($request->isPost()) {
    try {
        if (!check_bitrix_sessid()) {
            throw new RuntimeException('Сессия истекла. Обновите страницу.');
        }
        loadModules();
        if ($request->getPost('step') === 'schema') {
            $blockId = createEquipmentBlock();
            $entityTypeId = createSmartProcess((int)$USER->GetID());
            $message = 'Структура создана. ID HL-блока: ' . $blockId . '; entityTypeId: ' . $entityTypeId . '. Теперь выполните шаг 2.';
        } elseif ($request->getPost('step') === 'data') {
            $demo = createTestDealAndItem((int)$USER->GetID());
            addTestEquipment($demo['DEAL_ID']);
            $message = 'Тестовые данные готовы.';
        } else {
            throw new RuntimeException('Неизвестный шаг установки.');
        }
    } catch (Throwable $exception) {
        AddMessage2Log((string)$exception, 'figura.equipment.install');
        $error = $exception->getMessage();
    }
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_after.php';
$APPLICATION->SetTitle('Тестовое задание: оборудование');
?>
<p>Выполните два шага по очереди. Повторный запуск использует уже созданные тестовые данные.</p>
<?php if ($error !== ''): ?>
    <p role="alert"><?= htmlspecialcharsbx($error) ?></p>
<?php endif; ?>
<?php if ($message !== ''): ?>
    <p><?= htmlspecialcharsbx($message) ?></p>
<?php endif; ?>
<form method="post">
    <?= bitrix_sessid_post() ?>
    <button type="submit" name="step" value="schema">1. Создать структуру</button>
    <button type="submit" name="step" value="data">2. Создать тестовые данные</button>
</form>
<?php if ($demo !== null): ?>
    <?php $router = Container::getInstance()->getRouter(); ?>
    <p>
        <a href="<?= htmlspecialcharsbx((string)$router->getItemDetailUrl(CCrmOwnerType::Deal, $demo['DEAL_ID'])) ?>">Открыть сделку №<?= (int)$demo['DEAL_ID'] ?></a>
        ·
        <a href="<?= htmlspecialcharsbx((string)$router->getItemDetailUrl($demo['ENTITY_TYPE_ID'], $demo['ITEM_ID'])) ?>">Открыть элемент смарт-процесса №<?= (int)$demo['ITEM_ID'] ?></a>
    </p>
<?php endif; ?>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php'; ?>

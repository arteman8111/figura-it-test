/**
 * Представление задачи 5. PHP передаёт уже проверенное количество и признак пользователя 5.
 * MutationObserver восстанавливает блок после переключения режима/перерисовки карточки.
 * Позиция определяется кодом CLIENT, а не переводимым текстом «Клиент».
 */
(function () {
    'use strict';

    if (window.FiguraDealEquipmentCountLoaded) {
        return;
    }
    window.FiguraDealEquipmentCountLoaded = true;

    BX.ready(function () {
        const settings = window.FiguraDealEquipmentSettings;
        if (!settings || !Number.isInteger(settings.count) || settings.count < 0) {
            return;
        }

        const clientSelector = [
            '.ui-entity-editor-content-block[data-cid="CLIENT"]',
            '.ui-entity-editor-content-block[data-cid="CLIENT_ID"]'
        ].join(',');
        const blockId = 'figura-equipment-count-' + settings.dealId;

        function mount() {
            const client = document.querySelector(clientSelector);
            let block = document.getElementById(blockId);
            if (!client || !client.parentNode) {
                // Если пользователь скрыл «Клиента», без него нет нужной точки вставки.
                if (block) {
                    block.remove();
                }
                return;
            }
            if (!block) {
                block = document.createElement('div');
                block.id = blockId;
                block.className = 'ui-entity-editor-content-block figura-equipment-count';
                if (settings.highlight === true) {
                    block.classList.add('figura-equipment-count--highlight');
                }

                const title = document.createElement('div');
                title.className = 'ui-entity-editor-block-title';
                title.textContent = 'Количество оборудования';

                const value = document.createElement('div');
                value.className = 'ui-entity-editor-content-block-text figura-equipment-count__value';
                value.textContent = String(settings.count);
                value.setAttribute('aria-label', 'Количество оборудования: ' + settings.count);
                block.append(title, value);
            }
            // Проверка позиции предотвращает бесконечный цикл MutationObserver.
            if (client.nextElementSibling !== block) {
                client.insertAdjacentElement('afterend', block);
            }
        }

        let scheduled = false;
        const observer = new MutationObserver(function () {
            if (!scheduled) {
                scheduled = true;
                window.requestAnimationFrame(function () {
                    scheduled = false;
                    mount();
                });
            }
        });
        mount();
        observer.observe(document.body, {childList: true, subtree: true});
        window.addEventListener('pagehide', function () { observer.disconnect(); }, {once: true});
    });
}());

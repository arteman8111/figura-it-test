// Добавляем счётчик под «Клиентом» и восстанавливаем его после перерисовки карточки.
(function () {
    if (window.FiguraDealEquipmentCountLoaded) {
        return;
    }
    window.FiguraDealEquipmentCountLoaded = true;

    BX.ready(function () {
        const settings = window.FiguraDealEquipmentSettings;
        if (!settings) {
            return;
        }
        let block;

        function showCount() {
            const client = document.querySelector(
                '.ui-entity-editor-content-block[data-cid="CLIENT"], ' +
                '.ui-entity-editor-content-block[data-cid="CLIENT_ID"]'
            );
            if (!client) {
                if (block) {
                    block.remove();
                }
                return;
            }
            if (!block) {
                block = document.createElement('div');
                block.className = 'ui-entity-editor-content-block figura-equipment-count';
                if (settings.highlight) {
                    block.classList.add('figura-equipment-count--highlight');
                }

                const title = document.createElement('div');
                title.className = 'ui-entity-editor-block-title';
                title.textContent = 'Количество оборудования';

                const value = document.createElement('div');
                value.className = 'ui-entity-editor-content-block-text figura-equipment-count__value';
                value.textContent = String(settings.count);
                block.append(title, value);
            }
            if (client.nextElementSibling !== block) {
                client.after(block);
            }
        }

        const observer = new MutationObserver(showCount);
        showCount();
        observer.observe(document.body, {childList: true, subtree: true});
        window.addEventListener('pagehide', function () { observer.disconnect(); }, {once: true});
    });
}());

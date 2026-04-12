/**
 * Лёгкие toast-уведомления для Sandbox.
 * Использование: SandboxToast.show('Сохранено') или SandboxToast.show('Ошибка', 'error')
 * Типы: 'success' (по умолчанию), 'error', 'info'
 */
window.SandboxToast = (() => {
    let $container = null;

    function getContainer() {
        if (!$container || !$container.length) {
            $container = $('<div id="sandbox-toast-container"></div>').appendTo(document.body);
        }
        return $container;
    }

    function show(message, type = 'success') {
        const $toast = $(`<div class="sandbox-toast sandbox-toast--${type}"></div>`).text(message);
        getContainer().append($toast);

        // Запускаем появление в следующем кадре, чтобы transition сработал
        requestAnimationFrame(() => {
            requestAnimationFrame(() => $toast.addClass('sandbox-toast--visible'));
        });

        setTimeout(() => {
            $toast.removeClass('sandbox-toast--visible');
            $toast.one('transitionend', () => $toast.remove());
        }, 3000);
    }

    return { show };
})();

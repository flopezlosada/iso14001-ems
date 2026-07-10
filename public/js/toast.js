/* Toasts de acción (guardado correcto, error…). El servidor pinta los avisos dentro de
 * [data-toasts] a partir del sistema de flash de Symfony; aquí solo se les da vida: los de éxito
 * e info se cierran solos tras unos segundos, los de error persisten hasta que se pulsa la «×»
 * (suelen ser mensajes largos que hay que leer). El cierre (auto y manual) es cosa de este script.
 *
 * Sin JS el toast se queda visible en esa vista (no hay cierre), pero el flash ya se consumió de la
 * sesión, así que no reaparece al recargar. El resto de la app también asume JS (menú, quickjump,
 * diálogos de confirmación), así que es una degradación aceptable. */
(function () {
    'use strict';

    var DISMISS_MS = 4000;

    function dismiss(toast) {
        // Evita cerrar dos veces (auto-cierre + clic): la clase actúa de candado.
        if (toast.classList.contains('is-leaving')) {
            return;
        }
        toast.classList.add('is-leaving');
        // Espera al fin de la animación de salida; si no hay (reduce-motion), el evento igual llega
        // al no haber transición y se retira en el siguiente tick vía fallback.
        var removed = false;
        var remove = function () {
            if (removed) {
                return;
            }
            removed = true;
            toast.remove();
        };
        toast.addEventListener('animationend', remove);
        window.setTimeout(remove, 300);
    }

    function init() {
        var container = document.querySelector('[data-toasts]');
        if (!container) {
            return;
        }

        Array.prototype.slice.call(container.querySelectorAll('.toast')).forEach(function (toast, index) {
            var closeBtn = toast.querySelector('.toast__close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () { dismiss(toast); });
            }
            // Los errores no se auto-cierran: se quedan hasta que el usuario los cierra, para que dé
            // tiempo a leerlos. El resto (éxito/info) desaparece solo, escalonado si hay varios.
            if (!toast.classList.contains('toast--error')) {
                window.setTimeout(function () { dismiss(toast); }, DISMISS_MS + index * 600);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

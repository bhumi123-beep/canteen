// CanteenPro - client-side helpers
document.addEventListener('DOMContentLoaded', function () {
    // Auto-dismiss alerts after 4 seconds
    document.querySelectorAll('.alert').forEach(function (el) {
        setTimeout(function () { el.style.display = 'none'; }, 4000);
    });
});

// Preserve the staff workspace position when an action submits a form.
(function () {
    var scrollStorageKey = 'scroll-position:' + window.location.pathname;
    var actionButtonSelector = 'button[name="queue_status"], button[name="collect_reservation_deposit"], button[name="cashier_finalize"]';

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (form.closest('.data-table') || form.querySelector(actionButtonSelector)) {
            sessionStorage.setItem(scrollStorageKey, String(window.scrollY));
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        var savedScrollY = sessionStorage.getItem(scrollStorageKey);

        if (savedScrollY !== null) {
            window.scrollTo(0, Number(savedScrollY));
            sessionStorage.removeItem(scrollStorageKey);
        }
    });
})();

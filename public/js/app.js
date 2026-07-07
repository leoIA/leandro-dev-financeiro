// CSRF global para AJAX
window.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content ||
                    document.querySelector('input[name="_csrf"]')?.value;

// Helper fetch com CSRF
window.fetchCsrf = function(url, options = {}) {
    options.headers = options.headers || {};
    options.headers['X-CSRF-Token'] = window.CSRF_TOKEN;
    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(options.body);
    }
    return fetch(url, options);
};

// Auto-dismiss toasts após 5s (exceto errors)
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toast.show').forEach(function(t) {
        if (!t.querySelector('.toast-header.bg-danger')) {
            setTimeout(() => {
                const toast = bootstrap.Toast.getInstance(t) || new bootstrap.Toast(t);
                toast.hide();
            }, 5000);
        }
    });

    // Confirmar antes de ações destrutivas
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(el.dataset.confirm)) e.preventDefault();
        });
    });

    // Relógio no topbar
    const clock = document.getElementById('topbar-clock');
    if (clock) {
        setInterval(() => {
            clock.textContent = new Date().toLocaleString('pt-BR', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
        }, 1000);
    }

    // DataTables default
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery.extend(true, jQuery.fn.dataTable.defaults, {
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
            },
            pageLength: 25,
            responsive: true
        });
    }
});

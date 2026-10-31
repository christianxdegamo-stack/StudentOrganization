// OrgSync - small helper scripts (no database logic here; PHP handles all data).
document.addEventListener('DOMContentLoaded', function () {
    // Mobile sidebar toggle
    var sb = document.getElementById('sidebar');
    var bd = document.getElementById('sidebarBackdrop');
    var tg = document.getElementById('sidebarToggle');
    function toggle() { sb.classList.toggle('open'); bd.classList.toggle('show'); }
    if (tg && sb && bd) { tg.addEventListener('click', toggle); bd.addEventListener('click', toggle); }

    // Confirm before dangerous actions: <form data-confirm="Are you sure?">
    document.querySelectorAll('form[data-confirm]').forEach(function (f) {
        f.addEventListener('submit', function (ev) {
            if (!window.confirm(f.getAttribute('data-confirm'))) { ev.preventDefault(); }
        });
    });

    // Fill add/edit modals: <button data-bs-toggle="modal" data-bs-target="#m" data-action="edit" data-fill='{"name":"x"}'>
    document.addEventListener('show.bs.modal', function (ev) {
        var modal = ev.target, btn = ev.relatedTarget;
        var form = modal.querySelector('form');
        if (!form || !btn) { return; }
        form.reset();
        var action = btn.getAttribute('data-action') || 'add';
        if (form.elements['action']) { form.elements['action'].value = action; }
        var title = btn.getAttribute('data-title');
        var t = modal.querySelector('.modal-title');
        if (title && t) { t.textContent = title; }
        var fill = btn.getAttribute('data-fill');
        if (fill) {
            var data = JSON.parse(fill);
            Object.keys(data).forEach(function (k) {
                var el = form.elements[k];
                if (el) { el.value = (data[k] === null ? '' : data[k]); }
            });
        }
        form.querySelectorAll('[data-lock-on-edit]').forEach(function (el) { el.readOnly = (action === 'edit'); });
        form.querySelectorAll('[data-required-on-add]').forEach(function (el) { el.required = (action === 'add'); });
        form.querySelectorAll('[data-show-on-add]').forEach(function (el) { el.classList.toggle('d-none', action !== 'add'); });
    });

    // Auto-submit filter selects: <select data-autosubmit>
    document.querySelectorAll('select[data-autosubmit]').forEach(function (s) {
        s.addEventListener('change', function () { s.form.submit(); });
    });
});

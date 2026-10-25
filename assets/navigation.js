document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('.menu-toggle');
    const sidebar = document.getElementById('site-sidebar');
    if (!toggle || !sidebar) return;
    if (typeof HTMLDialogElement === 'undefined' || !HTMLDialogElement.prototype.showModal) {
        sidebar.style.display = 'flex';
        return;
    }
    const mobile = window.matchMedia('(max-width:700px)');
    const marker = document.createComment('sidebar');
    sidebar.before(marker);
    const drawer = document.createElement('dialog');
    drawer.className = 'nav-drawer';
    drawer.setAttribute('aria-label', 'Navigation');
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'nav-close';
    close.setAttribute('aria-label', 'Close navigation');
    const icon = document.createElement('iconify-icon');
    icon.setAttribute('icon', 'solar:close-circle-outline');
    icon.setAttribute('aria-hidden', 'true');
    close.append(icon);
    drawer.append(close);
    document.body.append(drawer);
    function sync() {
        if (drawer.open) drawer.close();
        if (mobile.matches) drawer.append(sidebar);
        else marker.after(sidebar);
        toggle.hidden = !mobile.matches;
        toggle.setAttribute('aria-expanded', 'false');
    }
    toggle.addEventListener('click', function () {
        drawer.showModal();
        toggle.setAttribute('aria-expanded', 'true');
    });
    close.addEventListener('click', function () { drawer.close(); });
    drawer.addEventListener('click', function (event) {
        if (event.target !== drawer) return;
        const rect = drawer.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) drawer.close();
    });
    drawer.addEventListener('close', function () {
        toggle.setAttribute('aria-expanded', 'false');
        if (mobile.matches) toggle.focus();
    });
    mobile.addEventListener('change', sync);
    sync();
});

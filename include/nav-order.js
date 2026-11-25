/**
 * nav-order.js - réorganisation des boutons de l'en-tête (index.php)
 * Seuls les boutons / menus de la cellule d'en-tête peuvent changer de place (glisser-déposer ou flèches).
 * Fixes : date et heure, visiteurs, météo (en tête), bouton d'organisation et Déconnexion (toujours en dernier).
 * L'ordre choisi est mémorisé dans ce navigateur (localStorage) ; « Réinitialiser » rétablit l'ordre d'origine.
 */
(function () {
    var KEY = 'svxreflector_nav_order';
    var box = document.querySelector('header .header-right');
    var editBtn = document.getElementById('navEdit');
    if (!box || !editBtn) return;

    var FIXED = '.datetime-pill, #visitorsPill, #weatherPill, #navEdit, .btn-logout, #navReset';
    function movables() {
        return Array.prototype.filter.call(box.children, function (el) { return !el.matches(FIXED) && !/^(SCRIPT|STYLE)$/.test(el.tagName); });
    }
    function idOf(el, i) {
        if (el.id) return el.id;
        if (el.tagName === 'A') { var h = (el.getAttribute('href') || '').split('?')[0].split('/').pop(); if (h) return 'a:' + h; }
        if (el.classList.contains('dd')) { var t = el.querySelector('.dd-toggle'); return 'dd:' + (t ? t.textContent.trim() : i); }
        return 'x:' + i;
    }
    var resetBtn = document.createElement('button');
    resetBtn.type = 'button'; resetBtn.id = 'navReset'; resetBtn.className = 'btn-action';
    resetBtn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> <span>Réinitialiser</span>';
    resetBtn.style.display = 'none';
    box.insertBefore(resetBtn, editBtn);

    var items = movables();
    items.forEach(function (el, i) { el.dataset.navId = idOf(el, i); });
    var defaultOrder = items.map(function (el) { return el.dataset.navId; });

    function read() { try { var v = JSON.parse(localStorage.getItem(KEY) || 'null'); return Array.isArray(v) ? v : null; } catch (e) { return null; } }
    function write(v) { try { if (v) localStorage.setItem(KEY, JSON.stringify(v)); else localStorage.removeItem(KEY); } catch (e) {} }
    function current() { return movables().map(function (el) { return el.dataset.navId; }); }

    function apply(order) {
        var byId = {};
        movables().forEach(function (el) { byId[el.dataset.navId] = el; });
        var seq = [];
        (order || []).forEach(function (id) { if (byId[id]) { seq.push(byId[id]); delete byId[id]; } });
        defaultOrder.forEach(function (id) { if (byId[id]) { seq.push(byId[id]); delete byId[id]; } });   // boutons nouveaux : à leur place d'origine
        seq.forEach(function (el) { box.insertBefore(el, resetBtn); });
    }
    var saved = read();
    if (saved) apply(saved);

    /* ----- mode organisation ----- */
    var editing = false, dragEl = null;
    function arrows(el) {
        var w = document.createElement('span');
        w.className = 'nav-move';
        w.innerHTML = '<b data-d="-1" title="Avancer">◀</b><b data-d="1" title="Reculer">▶</b>';
        el.appendChild(w);
    }
    function save() { var c = current(); write(c.join('|') === defaultOrder.join('|') ? null : c); }
    function move(el, dir) {
        var list = movables(), i = list.indexOf(el), j = i + dir;
        if (j < 0 || j >= list.length) return;
        if (dir > 0) box.insertBefore(el, list[j].nextSibling); else box.insertBefore(el, list[j]);
        save();
    }

    function setEditing(on) {
        editing = on;
        box.classList.toggle('nav-editing', on);
        resetBtn.style.display = on ? '' : 'none';
        editBtn.innerHTML = on ? '<i class="fa-solid fa-check"></i> <span>Terminé</span>' : '<i class="fa-solid fa-arrows-up-down-left-right"></i>';
        movables().forEach(function (el) {
            el.draggable = on;
            var old = el.querySelector(':scope > .nav-move'); if (old) old.remove();
            if (on) arrows(el);
        });
    }
    editBtn.addEventListener('click', function () { setEditing(!editing); });
    resetBtn.addEventListener('click', function () { apply(defaultOrder); write(null); });

    // En mode organisation : ni navigation ni ouverture de menu (capture avant les autres écouteurs)
    box.addEventListener('click', function (e) {
        if (!editing) return;
        var mv = e.target.closest('.nav-move b');
        var el = e.target.closest('[data-nav-id]');
        if (mv && el) { e.preventDefault(); e.stopPropagation(); move(el, parseInt(mv.dataset.d, 10)); return; }
        if (el) { e.preventDefault(); e.stopPropagation(); }
    }, true);

    box.addEventListener('dragstart', function (e) {
        var el = e.target.closest && e.target.closest('[data-nav-id]');
        if (!editing || !el || el.parentNode !== box) return;
        dragEl = el; el.classList.add('nav-drag');
        try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', el.dataset.navId); } catch (x) {}
    });
    box.addEventListener('dragover', function (e) {
        if (!editing || !dragEl) return;
        var el = e.target.closest('[data-nav-id]');
        e.preventDefault();
        if (!el || el === dragEl || el.parentNode !== box) return;
        var r = el.getBoundingClientRect();
        var after = (e.clientX - r.left) > r.width / 2;
        box.insertBefore(dragEl, after ? el.nextSibling : el);
    });
    box.addEventListener('drop', function (e) { if (editing && dragEl) { e.preventDefault(); save(); } });
    box.addEventListener('dragend', function () { if (dragEl) { dragEl.classList.remove('nav-drag'); save(); dragEl = null; } });
})();

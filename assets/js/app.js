/* Devs Arun — public site script (no dependencies). */
(function () {
  'use strict';

  var BASE = document.body.getAttribute('data-base') || '';
  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  function api(path, data) {
    return fetch(BASE + '/api/' + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(data || {})
    }).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'Unexpected server response.' }; });
    }).catch(function () { return { ok: false, error: 'Network error. Please check your connection.' }; });
  }

  function toast(msg, type) {
    var t = document.getElementById('toast');
    if (!t) return;
    t.className = 'pointer-events-none fixed left-1/2 top-24 z-[90] -translate-x-1/2 rounded-full px-5 py-3 text-sm font-medium shadow-xl transition ' +
      (type === 'error' ? 'bg-red-500 text-white' : 'bg-neo-green text-neo-black');
    t.textContent = msg;
    t.style.opacity = '1';
    clearTimeout(t._h);
    t._h = setTimeout(function () { t.style.opacity = '0'; }, 4200);
  }

  function track(name, label, serviceId) {
    try {
      var body = JSON.stringify({ name: name, label: label || '', service_id: serviceId || null, _csrf: CSRF });
      if (navigator.sendBeacon) {
        navigator.sendBeacon(BASE + '/api/event/', new Blob([body], { type: 'application/json' }));
      } else {
        api('event/', { name: name, label: label, service_id: serviceId });
      }
    } catch (e) { /* ignore */ }
  }

  window.DA = { api: api, toast: toast, track: track, base: BASE, csrf: CSRF };

  /* Mobile menu */
  var menu = document.getElementById('mobile-menu');
  var openBtn = document.querySelector('[data-menu-open]');
  function setMenu(open) {
    if (!menu) return;
    menu.classList.toggle('hidden', !open);
    document.documentElement.style.overflow = open ? 'hidden' : '';
    if (openBtn) openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  if (openBtn) openBtn.addEventListener('click', function () { setMenu(true); });
  document.querySelectorAll('[data-menu-close]').forEach(function (el) { el.addEventListener('click', function () { setMenu(false); }); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setMenu(false); closeChat(); } });

  /* Click tracking */
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-track]');
    if (el) track(el.getAttribute('data-track'), el.getAttribute('data-label') || el.textContent.trim().slice(0, 80), el.getAttribute('data-service') || null);
  });

  /* Reveal on scroll */
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -40px 0px' });
    document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });
  } else {
    document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('in'); });
  }

  /* Filters (work page, services page) */
  document.querySelectorAll('[data-filter-group]').forEach(function (group) {
    var target = document.querySelector(group.getAttribute('data-filter-group'));
    if (!target) return;
    group.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-filter]');
      if (!btn) return;
      var f = btn.getAttribute('data-filter');
      group.querySelectorAll('[data-filter]').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
      target.querySelectorAll('[data-type]').forEach(function (item) {
        item.style.display = (f === 'all' || item.getAttribute('data-type') === f) ? '' : 'none';
      });
      if (history.replaceState) history.replaceState(null, '', f === 'all' ? location.pathname : location.pathname + '?type=' + f);
    });
    var pre = new URLSearchParams(location.search).get('type');
    if (pre) { var b = group.querySelector('[data-filter="' + pre.replace(/[^a-z]/g, '') + '"]'); if (b) b.click(); }
  });

  document.querySelectorAll('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });

  /* Generic AJAX forms: <form data-ajax="contact/"> */
  document.querySelectorAll('form[data-ajax]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = form.querySelector('[type="submit"]');
      var data = {};
      new FormData(form).forEach(function (v, k) { data[k] = v; });
      if (btn) { btn.disabled = true; btn._t = btn.innerHTML; btn.innerHTML = 'Sending…'; }
      api(form.getAttribute('data-ajax'), data).then(function (res) {
        if (btn) { btn.disabled = false; btn.innerHTML = btn._t; }
        if (res.ok) {
          var ok = form.querySelector('[data-success]');
          if (ok) { ok.classList.remove('hidden'); ok.querySelector('[data-ref]') && (ok.querySelector('[data-ref]').textContent = res.ref || ''); }
          form.querySelectorAll('[data-hide-on-success]').forEach(function (el) { el.classList.add('hidden'); });
          form.reset();
          toast(res.message || 'Sent! We will get back to you soon.');
        } else {
          toast(res.error || 'Please check the form and try again.', 'error');
        }
      });
    });
  });

  /* ---------------- Enquiry modal ---------------- */
  var enq = document.getElementById('enq-modal');
  if (enq) {
    var enqForm = document.getElementById('enq-form');
    var enqSvc = document.getElementById('enq-service');
    var enqPkg = document.getElementById('enq-package');
    var enqPkgWrap = document.getElementById('enq-pkg-wrap');
    var enqErr = document.getElementById('enq-error');
    var enqDone = document.getElementById('enq-done');
    var lastFocus = null;

    var fillPackages = function (preferred) {
      var opt = enqSvc.options[enqSvc.selectedIndex];
      var list = [];
      try { list = JSON.parse(opt.getAttribute('data-packages') || '[]'); } catch (e) { list = []; }
      enqPkg.innerHTML = '';
      if (!list.length) { enqPkgWrap.classList.add('hidden'); return; }
      enqPkgWrap.classList.remove('hidden');
      var none = document.createElement('option');
      none.value = ''; none.textContent = 'Not sure yet — suggest me';
      enqPkg.appendChild(none);
      list.forEach(function (p) {
        var o = document.createElement('option');
        o.value = p; o.textContent = p;
        if (preferred && p.indexOf(preferred) === 0) o.selected = true;
        enqPkg.appendChild(o);
      });
      var name = document.getElementById('enq-svc-name');
      if (name) name.textContent = opt.getAttribute('data-title');
    };
    var openEnq = function (slug, pkg) {
      lastFocus = document.activeElement;
      if (slug) enqSvc.value = slug;
      fillPackages(pkg);
      enqForm.classList.remove('hidden');
      enqDone.classList.add('hidden');
      enqErr.classList.add('hidden');
      enq.classList.remove('hidden');
      document.documentElement.style.overflow = 'hidden';
      setTimeout(function () { document.getElementById('enq-name').focus(); }, 60);
    };
    var closeEnq = function () {
      enq.classList.add('hidden');
      document.documentElement.style.overflow = '';
      if (lastFocus && lastFocus.focus) lastFocus.focus();
    };
    enqSvc.addEventListener('change', function () { fillPackages(''); });
    enq.querySelectorAll('[data-enq-close]').forEach(function (b) { b.addEventListener('click', closeEnq); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !enq.classList.contains('hidden')) closeEnq(); });
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-enquire]');
      if (!b) return;
      e.preventDefault();
      openEnq(b.getAttribute('data-enquire'), b.getAttribute('data-package') || '');
    });
    enqForm.addEventListener('submit', function (e) {
      e.preventDefault();
      enqErr.classList.add('hidden');
      var fail = function (m) { enqErr.textContent = m; enqErr.classList.remove('hidden'); };
      if (!enqForm.checkValidity()) {
        var bad = enqForm.querySelector(':invalid');
        if (bad) bad.focus();
        return fail(bad && bad.type === 'email' ? 'Please enter a valid email address.' : 'Please fill your name, email and phone number.');
      }
      var btn = enqForm.querySelector('[type="submit"]');
      var label = btn.innerHTML;
      btn.disabled = true; btn.textContent = 'Sending…';
      var data = {};
      new FormData(enqForm).forEach(function (v, k) { data[k] = v; });
      api('lead/', data).then(function (res) {
        btn.disabled = false; btn.innerHTML = label;
        if (!res.ok) return fail(res.error || 'Please check the form and try again.');
        enqForm.classList.add('hidden');
        enqDone.classList.remove('hidden');
        document.getElementById('enq-ref').textContent = res.ref;
        var t = document.getElementById('enq-track');
        t.href = t.href.split('?')[0] + '?ref=' + encodeURIComponent(res.ref) + '&email=' + encodeURIComponent(data.email);
        var wa = document.getElementById('enq-wa');
        if (wa) wa.href = 'https://wa.me/' + wa.getAttribute('data-wa') + '?text=' + encodeURIComponent('Hi Arun, I just sent an enquiry (' + res.ref + ') about ' + enqSvc.options[enqSvc.selectedIndex].getAttribute('data-title') + '.');
        enqForm.reset();
      });
    });
    window.DA.enquire = openEnq;
  }

  /* ---------------- AI chat ---------------- */
  var chat = document.getElementById('ai-chat');
  var win = document.getElementById('ai-window');
  var msgs = document.getElementById('ai-messages');
  var chips = document.getElementById('ai-chips');
  var form = document.getElementById('ai-form');
  var input = document.getElementById('ai-input');
  var toggle = document.getElementById('ai-toggle');
  var badge = document.getElementById('ai-badge');
  var token = null;
  try { token = sessionStorage.getItem('da_chat'); } catch (e) { /* private mode */ }
  var started = false;
  var busy = false;
  var DEFAULT_CHIPS = ['💰 Pricing', '🌐 I need a website', '📱 Android app', '🤖 AI chatbot', '✨ What is AEO/GEO?', '🤝 Is price negotiable?'];

  function addMsg(role, html, isHtml) {
    var d = document.createElement('div');
    d.className = 'msg ' + (role === 'user' ? 'msg-user' : 'msg-bot');
    if (isHtml) d.innerHTML = html; else d.textContent = html;
    msgs.appendChild(d);
    msgs.scrollTop = msgs.scrollHeight;
    return d;
  }
  function setChips(list) {
    chips.innerHTML = '';
    (list || []).forEach(function (c) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'chat-chip';
      b.textContent = c;
      b.addEventListener('click', function () { send(c.replace(/^[^\w\u0900-\u097F]+/, '').trim() || c); });
      chips.appendChild(b);
    });
  }
  function typing(on) {
    var t = document.getElementById('ai-typing');
    if (on && !t) {
      t = document.createElement('div');
      t.id = 'ai-typing';
      t.className = 'msg msg-bot typing flex gap-1';
      t.innerHTML = '<span></span><span></span><span></span>';
      msgs.appendChild(t);
      msgs.scrollTop = msgs.scrollHeight;
    } else if (!on && t) { t.remove(); }
  }
  function start() {
    if (started) return;
    started = true;
    if (token) {
      api('chat/', { action: 'history', token: token }).then(function (res) {
        if (res.ok && res.messages && res.messages.length) {
          res.messages.forEach(function (m) { addMsg(m.role, m.html, true); });
          setChips(DEFAULT_CHIPS.slice(0, 3));
        } else { token = null; greet(); }
      });
    } else { greet(); }
  }
  function greet() {
    addMsg('bot', chat.getAttribute('data-greeting') || 'Hi! How can I help?');
    setChips(DEFAULT_CHIPS);
  }
  function send(text) {
    text = (text || '').trim();
    if (!text || busy) return;
    busy = true;
    addMsg('user', text);
    setChips([]);
    typing(true);
    api('chat/', { action: 'send', token: token, message: text, page: location.pathname }).then(function (res) {
      typing(false);
      busy = false;
      if (res.ok) {
        token = res.token;
        try { sessionStorage.setItem('da_chat', token); } catch (e) { /* ignore */ }
        addMsg('bot', res.html, true);
        setChips(res.chips || []);
      } else {
        addMsg('bot', res.error || 'Sorry, something went wrong. You can also use the contact form.');
      }
    });
  }
  function openChat() {
    if (!win) return;
    win.classList.remove('hidden');
    win.classList.add('flex');
    toggle.setAttribute('aria-expanded', 'true');
    if (badge) badge.remove();
    start();
    track('chat_open', location.pathname);
    setTimeout(function () { input && input.focus(); }, 50);
  }
  function closeChat() {
    if (!win) return;
    win.classList.add('hidden');
    win.classList.remove('flex');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  }
  if (toggle) toggle.addEventListener('click', function () { win.classList.contains('hidden') ? openChat() : closeChat(); });
  var closeBtn = document.getElementById('ai-close');
  if (closeBtn) closeBtn.addEventListener('click', closeChat);
  if (form) form.addEventListener('submit', function (e) { e.preventDefault(); var v = input.value; input.value = ''; send(v); });
  document.querySelectorAll('[data-open-chat]').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); openChat(); var q = b.getAttribute('data-open-chat'); if (q) send(q); }); });
})();

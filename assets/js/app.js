/* Devs Arun — public site script (no dependencies). */
(function () {
  'use strict';

  var BASE = document.body.getAttribute('data-base') || '';
  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  /* The server answers 419 + a fresh token when the session expired (e.g. the tab was open for hours).
     Store it and retry once, so a visitor never loses an enquiry to "session expired". */
  function setCsrf(t) {
    CSRF = t;
    if (window.DA) window.DA.csrf = t;
    var m = document.querySelector('meta[name="csrf-token"]');
    if (m) m.setAttribute('content', t);
  }

  function api(path, data, retried) {
    return fetch(BASE + '/api/' + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(data || {})
    }).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'Unexpected server response.' }; }).then(function (res) {
        if (r.status === 419 && res && res.csrf && !retried) {
          setCsrf(res.csrf);
          return api(path, data, true);
        }
        return res;
      });
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

  window.DA = { api: api, toast: toast, track: track, base: BASE, csrf: CSRF, setCsrf: setCsrf };

  /* Engaged-visitor signal: fires once per browser session on the first real interaction.
     Bots and link previews rarely scroll or tap, so admin can compare real visitors with raw hits. */
  (function () {
    var key = 'da_eng';
    try { if (sessionStorage.getItem(key)) return; } catch (e) { /* private mode */ }
    var evs = ['scroll', 'pointerdown', 'keydown', 'touchstart'];
    var fire = function () {
      evs.forEach(function (n) { window.removeEventListener(n, fire, true); });
      try { sessionStorage.setItem(key, '1'); } catch (e) { /* ignore */ }
      track('engaged', location.pathname);
    };
    evs.forEach(function (n) { window.addEventListener(n, fire, { capture: true, passive: true }); });
  })();

  /* ---------------- Local currency for prices ----------------
     Every price is rendered as <span data-fx="3500" data-fx-from="INR">₹3,500</span>.
     We pick the visitor's currency (saved choice → Cloudflare country → timezone → browser language → USD)
     and rewrite the text using the rates in <meta name="fx">. */
  var FX = null;
  try { FX = JSON.parse((document.querySelector('meta[name="fx"]') || {}).content || 'null'); } catch (e) { FX = null; }
  var TZ_COUNTRY = {
    'Asia/Kolkata': 'IN', 'Asia/Calcutta': 'IN', 'Europe/London': 'GB', 'Europe/Dublin': 'IE', 'Europe/Berlin': 'DE', 'Europe/Paris': 'FR',
    'Europe/Madrid': 'ES', 'Europe/Rome': 'IT', 'Europe/Amsterdam': 'NL', 'Europe/Brussels': 'BE', 'Europe/Vienna': 'AT', 'Europe/Zurich': 'CH',
    'Europe/Stockholm': 'SE', 'Europe/Oslo': 'NO', 'Europe/Copenhagen': 'DK', 'Europe/Warsaw': 'PL', 'Europe/Prague': 'CZ', 'Europe/Budapest': 'HU',
    'Europe/Bucharest': 'RO', 'Europe/Istanbul': 'TR', 'Europe/Moscow': 'RU', 'Europe/Kiev': 'UA', 'Europe/Kyiv': 'UA', 'Europe/Lisbon': 'PT',
    'Europe/Helsinki': 'FI', 'Europe/Athens': 'GR', 'Asia/Dubai': 'AE', 'Asia/Riyadh': 'SA', 'Asia/Qatar': 'QA', 'Asia/Kuwait': 'KW',
    'Asia/Bahrain': 'BH', 'Asia/Muscat': 'OM', 'Asia/Singapore': 'SG', 'Asia/Kuala_Lumpur': 'MY', 'Asia/Tokyo': 'JP', 'Asia/Shanghai': 'CN',
    'Asia/Hong_Kong': 'HK', 'Asia/Seoul': 'KR', 'Asia/Taipei': 'TW', 'Asia/Bangkok': 'TH', 'Asia/Jakarta': 'ID', 'Asia/Manila': 'PH',
    'Asia/Ho_Chi_Minh': 'VN', 'Asia/Saigon': 'VN', 'Asia/Kathmandu': 'NP', 'Asia/Katmandu': 'NP', 'Asia/Dhaka': 'BD', 'Asia/Colombo': 'LK',
    'Asia/Karachi': 'PK', 'Asia/Jerusalem': 'IL', 'Asia/Tel_Aviv': 'IL', 'Pacific/Auckland': 'NZ', 'Africa/Johannesburg': 'ZA', 'Africa/Lagos': 'NG',
    'Africa/Nairobi': 'KE', 'Africa/Cairo': 'EG', 'America/Toronto': 'CA', 'America/Vancouver': 'CA', 'America/Edmonton': 'CA', 'America/Winnipeg': 'CA',
    'America/Halifax': 'CA', 'America/Mexico_City': 'MX', 'America/Sao_Paulo': 'BR', 'America/Argentina/Buenos_Aires': 'AR', 'America/Buenos_Aires': 'AR',
    'America/Santiago': 'CL', 'America/Bogota': 'CO', 'America/Lima': 'PE'
  };
  function guessCountry() {
    var tz = '';
    try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { /* ignore */ }
    if (TZ_COUNTRY[tz]) return TZ_COUNTRY[tz];
    if (/^Australia\//.test(tz)) return 'AU';
    if (/^America\//.test(tz)) return 'US';
    var langs = navigator.languages || [navigator.language || ''];
    for (var i = 0; i < langs.length; i++) {
      var m = /-([A-Z]{2})$/i.exec(langs[i] || '');
      if (m) return m[1].toUpperCase();
    }
    return '';
  }
  function fxTarget() {
    if (!FX || !FX.rates) return 'INR';
    var saved = readCookie('da_fx');
    if (saved && FX.rates[saved]) return saved;
    if (FX.cur && FX.rates[FX.cur]) return FX.cur;
    var cc = guessCountry();
    var cur = cc && FX.map ? FX.map[cc] : '';
    return cur && FX.rates[cur] ? cur : (cc === 'IN' ? 'INR' : 'USD');
  }
  function fxFormat(v, cur) {
    var small = v < 20;
    if (!small) v = Math.round(v);
    var loc = cur === 'INR' ? 'en-IN' : (navigator.language || 'en-US');
    try {
      return new Intl.NumberFormat(loc, { style: 'currency', currency: cur, minimumFractionDigits: 0, maximumFractionDigits: small ? 2 : 0 }).format(v);
    } catch (e) { return cur + ' ' + (small ? v.toFixed(2) : String(v)); }
  }
  function applyFx(target) {
    if (!FX || !FX.rates || !FX.rates[target]) return;
    var converted = false;
    document.querySelectorAll('[data-fx]').forEach(function (el) {
      if (!el.hasAttribute('data-fx-orig')) el.setAttribute('data-fx-orig', el.textContent);
      var orig = el.getAttribute('data-fx-orig');
      var from = el.getAttribute('data-fx-from');
      var amt = parseFloat(el.getAttribute('data-fx'));
      var intl = parseFloat(el.getAttribute('data-fx-intl') || '');
      var src = orig;
      if (target !== 'INR' && intl > 0) { from = 'USD'; amt = intl; src = '$' + intl; } // owner-set international price
      if (from === target && src === orig) { el.textContent = orig; el.removeAttribute('title'); return; }
      if (!FX.rates[from] || !(amt > 0)) return;
      el.textContent = fxFormat(amt / FX.rates[from] * FX.rates[target], target);
      if (from !== target) { el.title = 'Approx. conversion of ' + src; converted = true; } else { el.removeAttribute('title'); }
    });
    document.querySelectorAll('[data-fx-same-hide]').forEach(function (el) { el.classList.toggle('hidden', el.getAttribute('data-fx-same-hide') === target); });
    document.querySelectorAll('[data-fx-home-only]').forEach(function (el) { el.classList.toggle('hidden', target !== 'INR'); });
    document.querySelectorAll('[data-fx-select]').forEach(function (sel) { sel.value = target; });
    document.querySelectorAll('[data-fx-approx]').forEach(function (el) { el.classList.toggle('hidden', !converted); });
    window.DA.fxCurrency = target;
  }
  applyFx(fxTarget());
  document.addEventListener('change', function (e) {
    var sel = e.target.closest ? e.target.closest('[data-fx-select]') : null;
    if (!sel) return;
    document.cookie = 'da_fx=' + sel.value + '; path=' + (BASE || '') + '/; max-age=31536000; samesite=lax';
    applyFx(sel.value);
    if (window.DA.setCurrency) window.DA.setCurrency(sel.value === 'INR' ? 'INR' : 'USD', true); // store / checkout pay currency
  });
  window.DA.applyFx = applyFx;

  /* Store currency (INR / USD). Prices carry data-cur-show; toggles carry data-set-cur. */
  function readCookie(n) { var m = document.cookie.match('(?:^|; )' + n + '=([^;]*)'); return m ? decodeURIComponent(m[1]) : ''; }
  function setCurrency(cur, remember) {
    if (cur !== 'INR' && cur !== 'USD') return;
    window.DA.currency = cur;
    document.querySelectorAll('[data-cur-show]').forEach(function (el) { el.classList.toggle('hidden', el.getAttribute('data-cur-show') !== cur); });
    document.querySelectorAll('[data-set-cur]').forEach(function (b) {
      var on = b.getAttribute('data-set-cur') === cur;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    if (remember) document.cookie = 'da_cur=' + cur + '; path=' + (BASE || '') + '/; max-age=31536000; samesite=lax';
    document.dispatchEvent(new CustomEvent('da:currency', { detail: cur }));
  }
  (function () {
    var saved = readCookie('da_cur');
    if (saved) { setCurrency(saved, false); return; }
    if (!document.querySelector('[data-cur-show],[data-set-cur]')) return;
    setCurrency(fxTarget() === 'INR' ? 'INR' : 'USD', false);
  })();
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-set-cur]');
    if (b) { e.preventDefault(); setCurrency(b.getAttribute('data-set-cur'), true); }
  });
  window.DA.setCurrency = setCurrency;

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
  if (chat && chat.getAttribute('data-store') === '1') DEFAULT_CHIPS.splice(1, 0, '📚 Show me the notes in your store');

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

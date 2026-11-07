/* Store: listing filters, preview viewer, sticky buy bar and Razorpay checkout for notes. */
(function () {
  'use strict';
  if (!window.DA) return;
  var lockScroll = function (on) { document.documentElement.style.overflow = on ? 'hidden' : ''; };

  /* ---------- Listing: search + category filter ---------- */
  var grid = document.getElementById('product-grid');
  if (grid) {
    var search = document.querySelector('[data-store-search]');
    var empty = document.getElementById('store-empty');
    var cat = '';
    var apply = function () {
      var q = (search && search.value || '').trim().toLowerCase();
      var shown = 0;
      grid.querySelectorAll('[data-product]').forEach(function (card) {
        var ok = (!cat || card.getAttribute('data-cat') === cat) && (!q || card.getAttribute('data-title').indexOf(q) !== -1);
        card.style.display = ok ? '' : 'none';
        if (ok) shown++;
      });
      if (empty) empty.classList.toggle('hidden', shown > 0);
    };
    if (search) search.addEventListener('input', apply);
    document.querySelectorAll('[data-cat-filter]').forEach(function (b) {
      b.addEventListener('click', function () {
        cat = b.getAttribute('data-cat-filter');
        document.querySelectorAll('[data-cat-filter]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
        apply();
      });
    });
  }

  var product = document.getElementById('product');
  if (!product) return;
  var slug = product.getAttribute('data-slug');
  var currencies = (product.getAttribute('data-currencies') || 'INR').split(',');

  /* ---------- Preview viewer ---------- */
  var pv = document.getElementById('pv-modal');
  var pvScroll = document.getElementById('pv-scroll');
  var openViewer = function (i) {
    if (!pv) return;
    pv.classList.remove('hidden');
    lockScroll(true);
    var target = document.getElementById('pv-' + i);
    setTimeout(function () { if (target) pvScroll.scrollTop = target.offsetTop - 72; }, 30);
  };
  var closeViewer = function () { if (pv && !pv.classList.contains('hidden')) { pv.classList.add('hidden'); lockScroll(false); } };
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-viewer-open]');
    if (b) { e.preventDefault(); openViewer(b.getAttribute('data-viewer-open')); return; }
    if (e.target.closest('[data-pv-close]')) closeViewer();
  });
  if (pv) pv.addEventListener('contextmenu', function (e) { if (e.target.tagName === 'IMG') e.preventDefault(); });

  /* ---------- Sticky desktop bar ---------- */
  var sticky = document.getElementById('sticky-buy');
  var anchor = document.getElementById('buy-main');
  if (sticky && anchor && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      var hidden = entries[0].isIntersecting || entries[0].boundingClientRect.top > 0;
      sticky.classList.toggle('opacity-0', hidden);
      sticky.classList.toggle('-translate-y-4', hidden);
      sticky.classList.toggle('pointer-events-none', hidden);
    }, { threshold: 0 }).observe(anchor);
  }

  /* ---------- Buy ---------- */
  var modal = document.getElementById('buy-modal');
  if (!modal) return;
  var form = document.getElementById('buy-form');
  var err = document.getElementById('buy-error');
  var payLabel = document.getElementById('buy-pay-label');
  var btn = form.querySelector('[type="submit"]');
  var btnHtml = btn.innerHTML;

  var currency = function () {
    var c = window.DA.currency;
    return currencies.indexOf(c) !== -1 ? c : currencies[0];
  };
  var priceFor = function (c) { return product.getAttribute(c === 'USD' ? 'data-price-usd' : 'data-price-inr'); };
  var refreshLabel = function () {
    if (btn.disabled) return;
    payLabel = document.getElementById('buy-pay-label');
    if (payLabel) payLabel.textContent = 'Pay ' + priceFor(currency()) + ' securely';
    btnHtml = btn.innerHTML;
  };
  document.addEventListener('da:currency', refreshLabel);

  try {
    var saved = JSON.parse(localStorage.getItem('da_buyer') || '{}');
    ['name', 'email', 'phone'].forEach(function (k) { if (saved[k] && form.elements[k]) form.elements[k].value = saved[k]; });
  } catch (e) { /* ignore */ }

  var lastFocus = null;
  var openBuy = function () {
    closeViewer();
    lastFocus = document.activeElement;
    refreshLabel();
    err.classList.add('hidden');
    modal.classList.remove('hidden');
    lockScroll(true);
    setTimeout(function () { (form.elements.name.value ? form.elements.email : form.elements.name).focus(); }, 60);
  };
  var closeBuy = function () {
    modal.classList.add('hidden');
    lockScroll(false);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  };
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-buy]')) { e.preventDefault(); openBuy(); }
    else if (e.target.closest('[data-buy-close]')) closeBuy();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (!modal.classList.contains('hidden')) closeBuy(); else closeViewer();
  });

  var fail = function (msg) {
    err.textContent = msg;
    err.classList.remove('hidden');
    btn.disabled = false;
    btn.innerHTML = btnHtml;
  };

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    err.classList.add('hidden');
    var name = form.elements.name.value.trim();
    var email = form.elements.email.value.trim();
    var phone = form.elements.phone.value.trim();
    if (name.length < 2) { form.elements.name.focus(); return fail('Please enter your name.'); }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) { form.elements.email.focus(); return fail('Please enter a valid email — your download link is sent there.'); }
    if (typeof window.Razorpay !== 'function') return fail('The payment window could not load. Please disable ad-blockers and refresh the page.');
    try { localStorage.setItem('da_buyer', JSON.stringify({ name: name, email: email, phone: phone })); } catch (x) { /* ignore */ }
    var cur = currency();
    btn.disabled = true;
    btn.textContent = 'Opening secure payment…';
    window.DA.api('order/', { type: 'product', key: slug, currency: cur, name: name, email: email, phone: phone, agree: '1' }).then(function (res) {
      if (!res.ok) {
        if (res.switch_currency && window.DA.setCurrency) { window.DA.setCurrency(res.switch_currency, true); btn.disabled = false; refreshLabel(); }
        return fail(res.error || 'Could not start the payment.');
      }
      var rzp = new window.Razorpay({
        key: res.key,
        amount: res.amount,
        currency: res.currency,
        name: res.site,
        description: res.title,
        order_id: res.razorpay_order_id,
        prefill: res.prefill,
        notes: { order_no: res.order_no },
        theme: { color: '#4ADE80' },
        handler: function (r) {
          btn.textContent = 'Confirming payment…';
          window.DA.api('order/verify/', { order_no: res.order_no, razorpay_order_id: r.razorpay_order_id, razorpay_payment_id: r.razorpay_payment_id, razorpay_signature: r.razorpay_signature })
            .then(function (v) {
              if (v.ok) { window.location.href = v.redirect; }
              else fail(v.error || 'We could not confirm the payment yet. If money was deducted, your download link will be emailed automatically — order ' + res.order_no);
            });
        },
        modal: { ondismiss: function () { btn.disabled = false; btn.innerHTML = btnHtml; } }
      });
      rzp.on('payment.failed', function (r) {
        window.DA.track('payment_failed', res.order_no);
        fail('Payment failed: ' + ((r.error && r.error.description) || 'please try again or use another method.'));
      });
      rzp.open();
    });
  });
})();

/* Razorpay checkout flow. */
(function () {
  'use strict';
  var form = document.getElementById('checkout');
  if (!form || !window.DA) return;
  var err = document.getElementById('checkout-error');
  var btn = form.querySelector('[type="submit"]');
  var label = btn.innerHTML;

  function fail(msg) { err.textContent = msg; err.classList.remove('hidden'); btn.disabled = false; btn.innerHTML = label; }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    err.classList.add('hidden');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    if (typeof window.Razorpay !== 'function') return fail('Payment library failed to load. Please disable ad-blockers and refresh.');
    btn.disabled = true;
    btn.textContent = 'Opening secure payment…';
    var fd = new FormData(form);
    DA.api('order/', {
      type: form.getAttribute('data-type'), key: form.getAttribute('data-key'),
      name: fd.get('name'), email: fd.get('email'), phone: fd.get('phone'), notes: fd.get('notes'), agree: fd.get('agree')
    }).then(function (res) {
      if (!res.ok) return fail(res.error || 'Could not start payment.');
      var rzp = new window.Razorpay({
        key: res.key,
        amount: res.amount,
        currency: 'INR',
        name: res.site,
        description: res.title,
        order_id: res.razorpay_order_id,
        prefill: res.prefill,
        notes: { order_no: res.order_no },
        theme: { color: '#4ADE80' },
        handler: function (r) {
          btn.textContent = 'Verifying payment…';
          DA.api('order/verify/', { order_no: res.order_no, razorpay_order_id: r.razorpay_order_id, razorpay_payment_id: r.razorpay_payment_id, razorpay_signature: r.razorpay_signature })
            .then(function (v) {
              if (v.ok) { window.location.href = v.redirect; }
              else fail(v.error || 'Verification failed. If money was deducted, it will be confirmed automatically — contact me with order ' + res.order_no);
            });
        },
        modal: { ondismiss: function () { btn.disabled = false; btn.innerHTML = label; } }
      });
      rzp.on('payment.failed', function (r) {
        DA.track('payment_failed', res.order_no);
        fail('Payment failed: ' + ((r.error && r.error.description) || 'please try again.'));
      });
      rzp.open();
    });
  });
})();

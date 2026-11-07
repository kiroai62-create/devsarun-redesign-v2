/* Multi-step project brief. */
(function () {
  'use strict';
  var form = document.getElementById('brief');
  if (!form || !window.DA) return;
  var steps = Array.prototype.slice.call(form.querySelectorAll('.step'));
  var cur = 0;
  var started = false;
  var back = document.getElementById('b-back');
  var next = document.getElementById('b-next');
  var submit = document.getElementById('b-submit');
  var err = document.getElementById('brief-error');

  function showError(msg) { err.textContent = msg; err.classList.toggle('hidden', !msg); }

  function render() {
    steps.forEach(function (s, i) { s.classList.toggle('hidden', i !== cur); });
    var pct = Math.round(((cur + 1) / steps.length) * 100);
    document.getElementById('step-bar').style.width = pct + '%';
    document.getElementById('step-pct').textContent = pct + '%';
    document.getElementById('step-label').textContent = 'Step ' + (cur + 1) + ' of ' + steps.length + ' · ' + steps[cur].getAttribute('data-title');
    back.classList.toggle('invisible', cur === 0);
    next.classList.toggle('hidden', cur === steps.length - 1);
    submit.classList.toggle('hidden', cur !== steps.length - 1);
    showError('');
  }

  function validate(i) {
    var s = steps[i];
    var radios = {};
    var fields = s.querySelectorAll('input, textarea, select');
    for (var k = 0; k < fields.length; k++) {
      var f = fields[k];
      if (f.type === 'radio') { if (f.required) radios[f.name] = radios[f.name] || !!s.querySelector('input[name="' + f.name + '"]:checked'); continue; }
      if (f.type === 'file' && f.files && f.files[0] && f.files[0].size > 8 * 1024 * 1024) return 'The attachment must be smaller than 8 MB.';
      if (!f.checkValidity()) {
        f.focus();
        if (f.type === 'checkbox') return 'Please accept to be contacted about this project.';
        if (f.type === 'email') return 'Please enter a valid email address.';
        if (f.type === 'url') return 'Please enter a full URL starting with https://';
        return 'Please fill: ' + ((s.querySelector('label[for="' + f.id + '"]') || {}).textContent || 'required field').replace('*', '').trim();
      }
    }
    for (var n in radios) {
      if (!radios[n]) return n === 'service' ? 'Please choose a service.' : (n === 'budget' ? 'Please choose a budget range (it is negotiable!).' : 'Please choose a timeline.');
    }
    if (i === 1) {
      var d = form.querySelector('[name="description"]').value.trim();
      if (d.length < 20) return 'Please describe the project in at least 20 characters.';
    }
    if (i === 3) {
      var p = form.querySelector('[name="phone"]').value.trim();
      if (!/^[0-9+\-\s()]{6,20}$/.test(p)) return 'Please enter a valid phone number.';
    }
    return '';
  }

  form.addEventListener('change', function () {
    if (!started) { started = true; DA.track('form_start', 'brief'); }
  });

  next.addEventListener('click', function () {
    var m = validate(cur);
    if (m) return showError(m);
    cur++;
    DA.track('form_step', 'brief_step_' + (cur + 1));
    render();
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
  back.addEventListener('click', function () { if (cur > 0) { cur--; render(); } });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var m = validate(cur);
    if (m) return showError(m);
    submit.disabled = true;
    var label = submit.innerHTML;
    submit.textContent = 'Sending…';
    var fd = new FormData(form);
    fetch(DA.base + '/api/lead/', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': DA.csrf, 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        submit.disabled = false;
        submit.innerHTML = label;
        if (!res.ok) return showError(res.error || 'Something went wrong. Please try again.');
        steps.forEach(function (s) { s.classList.add('hidden'); });
        document.getElementById('brief-nav').classList.add('hidden');
        document.getElementById('step-bar').style.width = '100%';
        document.getElementById('step-pct').textContent = '100%';
        document.getElementById('step-label').textContent = 'Done';
        document.getElementById('brief-ref').textContent = res.ref;
        document.getElementById('brief-done').classList.remove('hidden');
        var t = document.getElementById('brief-track');
        t.href = t.href.split('?')[0] + '?ref=' + encodeURIComponent(res.ref) + '&email=' + encodeURIComponent(fd.get('email'));
        var wa = document.getElementById('brief-wa');
        if (wa) wa.href = 'https://wa.me/' + wa.getAttribute('data-wa') + '?text=' + encodeURIComponent('Hi Arun, I just submitted my project brief (' + res.ref + ').');
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      })
      .catch(function () { submit.disabled = false; submit.innerHTML = label; showError('Network error. Please try again.'); });
  });

  // Jump to step 2 when a service is pre-selected via ?service=
  if (form.querySelector('input[name="service"]:checked') && /[?&]service=/.test(location.search)) { cur = 1; }
  render();
})();

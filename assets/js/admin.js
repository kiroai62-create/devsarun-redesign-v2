/* Admin panel helpers. */
(function () {
  'use strict';
  var side = document.getElementById('a-side');
  var ov = document.getElementById('a-overlay');
  var btn = document.getElementById('a-menu');
  function setSide(open) {
    if (!side) return;
    side.classList.toggle('hidden', !open);
    side.classList.toggle('flex', open);
    ov.classList.toggle('hidden', !open);
  }
  if (btn) btn.addEventListener('click', function () { setSide(true); });
  if (ov) ov.addEventListener('click', function () { setSide(false); });

  // Confirm dangerous actions
  document.addEventListener('submit', function (e) {
    var f = e.target;
    var msg = f.getAttribute('data-confirm') || (e.submitter && e.submitter.getAttribute('data-confirm'));
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  document.querySelectorAll('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form.submit(); }); });

  // Insert email template into compose form
  document.querySelectorAll('[data-template-select]').forEach(function (sel) {
    sel.addEventListener('change', function () {
      var opt = sel.options[sel.selectedIndex];
      if (!opt || !opt.value) return;
      var form = sel.closest('form');
      form.querySelector('[name="subject"]').value = opt.getAttribute('data-subject') || '';
      form.querySelector('[name="body"]').value = opt.getAttribute('data-body') || '';
    });
  });

  // Copy to clipboard
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.getAttribute('data-copy');
      (navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () {
        var t = b.textContent; b.textContent = 'Copied ✓'; setTimeout(function () { b.textContent = t; }, 1500);
      }).catch(function () { window.prompt('Copy:', v); });
    });
  });

  // Charts (Chart.js loaded on pages that need it)
  function initCharts() {
    if (typeof window.Chart !== 'function') return;
    Chart.defaults.color = 'rgba(255,255,255,.5)';
    Chart.defaults.font.family = 'Inter, sans-serif';
    Chart.defaults.borderColor = 'rgba(255,255,255,.06)';
    document.querySelectorAll('canvas[data-chart]').forEach(function (c) {
      var cfgEl = document.getElementById(c.getAttribute('data-chart'));
      if (!cfgEl) return;
      var cfg = JSON.parse(cfgEl.textContent);
      var palette = ['#4ADE80', '#60A5FA', '#F472B6', '#FBBF24', '#A78BFA', '#34D399', '#F87171', '#22D3EE'];
      cfg.data.datasets.forEach(function (ds, i) {
        if (cfg.type === 'line') {
          ds.borderColor = ds.borderColor || palette[i];
          ds.backgroundColor = ds.backgroundColor || (i === 0 ? 'rgba(74,222,128,.12)' : 'rgba(96,165,250,.08)');
          ds.fill = true; ds.tension = .35; ds.pointRadius = 0; ds.borderWidth = 2;
        } else if (cfg.type === 'doughnut') {
          ds.backgroundColor = palette; ds.borderWidth = 0;
        } else {
          ds.backgroundColor = ds.backgroundColor || palette[i]; ds.borderRadius = 8;
        }
      });
      cfg.options = Object.assign({ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: cfg.type !== 'bar' || cfg.data.datasets.length > 1, labels: { boxWidth: 10 } } } }, cfg.options || {});
      new Chart(c, cfg);
    });
  }
  window.addEventListener('load', initCharts);
})();

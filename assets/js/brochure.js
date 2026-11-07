/* Brochure: PDF download (html2pdf) + print fallback + analytics. */
(function () {
  'use strict';
  var base = document.body.getAttribute('data-base') || '';
  var slug = document.body.getAttribute('data-slug');
  var sid = document.body.getAttribute('data-sid');
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  function track(name) {
    try {
      var body = JSON.stringify({ name: name, label: slug, service_id: sid, _csrf: csrf });
      navigator.sendBeacon ? navigator.sendBeacon(base + '/api/event/', new Blob([body], { type: 'application/json' }))
        : fetch(base + '/api/event/', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body });
    } catch (e) { /* ignore */ }
  }

  document.getElementById('print-btn').addEventListener('click', function () { track('brochure_download'); window.print(); });

  var btn = document.getElementById('pdf-btn');
  btn.addEventListener('click', function () {
    if (typeof window.html2pdf !== 'function') { window.print(); return; }
    track('brochure_download');
    btn.disabled = true;
    btn.textContent = 'Building PDF…';
    document.body.classList.add('exporting');
    window.scrollTo(0, 0);
    var deck = document.getElementById('deck');
    var opt = {
      margin: 0,
      filename: 'devsarun-' + slug + '-brochure.pdf',
      image: { type: 'jpeg', quality: 0.94 },
      html2canvas: { scale: 1.6, useCORS: true, backgroundColor: '#F7F7F7', windowWidth: 1280, scrollY: 0 },
      jsPDF: { unit: 'px', format: [1280, 720], orientation: 'landscape', hotfixes: ['px_scaling'] },
      // Slides are exactly one page tall, so plain height-based slicing gives 1 slide per page.
      pagebreak: { mode: 'css' }
    };
    // Wait for fonts so the PDF uses Inter.
    (document.fonts ? document.fonts.ready : Promise.resolve()).then(function () {
      return window.html2pdf().set(opt).from(deck).save();
    }).catch(function () { window.print(); }).then(function () {
      document.body.classList.remove('exporting');
      btn.disabled = false;
      btn.textContent = 'Download PDF';
    });
  });
})();

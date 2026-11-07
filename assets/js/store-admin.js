/*
 * Admin → Store → product editor.
 * Reads the PDF in the browser with PDF.js (no server tools needed on shared hosting):
 *   - page count + text (sent to AI and stored for the chat assistant)
 *   - preview images of the first N pages (watermarked) + 2 blurred "locked" teaser pages
 * Then uploads everything with the form (XHR, with progress).
 */
const form = document.getElementById('product-form');

if (form) {
  const CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  const PDFJS = form.dataset.pdfjs;
  const WATERMARK = form.dataset.watermark || 'preview';
  const MAX_BYTES = parseInt(form.dataset.maxBytes || '0', 10);

  const pdfInput = document.getElementById('pdf-input');
  const drop = document.getElementById('pdf-drop');
  const statusEl = document.getElementById('pdf-status');
  const prog = document.getElementById('pdf-progress');
  const grid = document.getElementById('preview-grid');
  const pagesInput = document.getElementById('preview-pages');
  const rerender = document.getElementById('rerender-btn');
  const aiBtn = document.getElementById('ai-btn');
  const aiStatus = document.getElementById('ai-status');
  const priceHint = document.getElementById('ai-price-hint');
  const errorsBox = document.getElementById('form-errors');
  const saveBtn = document.getElementById('save-btn');

  const state = { previews: [], teasers: [], busy: false, buffer: null, fileName: '', pageCount: 0, text: '' };
  let pdfjsLib = null;

  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const setStatus = (html) => { statusEl.innerHTML = html; };
  const setProgress = (pct) => {
    prog.classList.toggle('hidden', pct === null);
    prog.firstElementChild.style.width = (pct || 0) + '%';
  };
  const say = (el, html, tone) => {
    el.classList.remove('hidden', 'text-red-200', 'text-neo-green', 'text-white/70');
    el.classList.add(tone === 'error' ? 'text-red-200' : tone === 'ok' ? 'text-neo-green' : 'text-white/70');
    el.innerHTML = html;
  };

  async function lib() {
    if (!pdfjsLib) {
      pdfjsLib = await import(PDFJS + 'pdf.min.js');
      pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS + 'pdf.worker.min.js';
    }
    return pdfjsLib;
  }

  /* ---------- Rendering ---------- */
  function watermark(ctx, w, h) {
    ctx.save();
    ctx.globalAlpha = 0.07;
    ctx.fillStyle = '#000';
    ctx.font = `700 ${Math.round(w / 18)}px Inter, Arial, sans-serif`;
    ctx.translate(w / 2, h / 2);
    ctx.rotate(-Math.PI / 6);
    ctx.textAlign = 'center';
    for (let y = -h; y <= h; y += Math.round(h / 4)) ctx.fillText(WATERMARK.toUpperCase(), 0, y);
    ctx.restore();
  }

  async function renderPage(doc, num, width, locked) {
    const page = await doc.getPage(num);
    const vp1 = page.getViewport({ scale: 1 });
    const scale = Math.min(width / vp1.width, 4);
    const vp = page.getViewport({ scale });
    const c = document.createElement('canvas');
    c.width = Math.floor(vp.width);
    c.height = Math.floor(vp.height);
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, c.width, c.height);
    await page.render({ canvasContext: ctx, viewport: vp }).promise;
    page.cleanup();
    let out = c;
    if (locked) {
      // Unreadable on purpose: shrink to a few pixels and scale back up (works in every browser).
      const tiny = document.createElement('canvas');
      tiny.width = 36;
      tiny.height = Math.max(1, Math.round(36 * c.height / c.width));
      tiny.getContext('2d').drawImage(c, 0, 0, tiny.width, tiny.height);
      out = document.createElement('canvas');
      out.width = c.width;
      out.height = c.height;
      const o = out.getContext('2d');
      o.imageSmoothingEnabled = true;
      o.imageSmoothingQuality = 'high';
      o.drawImage(tiny, 0, 0, out.width, out.height);
    } else {
      watermark(ctx, c.width, c.height);
    }
    return new Promise((res) => out.toBlob((b) => res(b), 'image/jpeg', locked ? 0.6 : 0.82));
  }

  async function extractText(doc, onPage) {
    const max = Math.min(doc.numPages, 80);
    const pages = [];
    for (let i = 1; i <= max; i++) {
      const page = await doc.getPage(i);
      const tc = await page.getTextContent();
      pages.push(tc.items.map((it) => (it.str || '') + (it.hasEOL ? '\n' : ' ')).join('').replace(/[ \t]+/g, ' ').trim());
      page.cleanup();
      onPage(i, max);
      if (pages.join('').length > 200000) break;
    }
    return pages.join('\f');
  }

  async function processPdf(buffer, { withText }) {
    const pdf = await lib();
    state.busy = true;
    const task = pdf.getDocument({
      data: new Uint8Array(buffer.slice(0)),
      standardFontDataUrl: PDFJS + 'standard_fonts/',
      wasmUrl: PDFJS + 'wasm/',
      isEvalSupported: false,
      enableXfa: false,
    });
    let doc;
    try {
      doc = await task.promise;
    } catch (e) {
      state.busy = false;
      throw e;
    }
    try {
      state.pageCount = doc.numPages;
      document.getElementById('page-count').value = String(doc.numPages);
      if (withText) {
        setStatus(`Reading text… <span class="text-white/40">${esc(state.fileName)}</span>`);
        state.text = await extractText(doc, (i, n) => setProgress(Math.round((i / n) * 50)));
        document.getElementById('pdf-text').value = state.text;
      }
      const n = Math.max(1, Math.min(parseInt(pagesInput.value || '4', 10) || 4, 12, doc.numPages));
      pagesInput.value = String(n);
      state.previews = [];
      state.teasers = [];
      grid.innerHTML = '';
      for (let i = 1; i <= n; i++) {
        setStatus(`Rendering preview page ${i} of ${n}…`);
        const b = await renderPage(doc, i, 1200, false);
        state.previews.push(b);
        addThumb(b, false);
        setProgress(50 + Math.round((i / n) * 45));
      }
      for (let i = n + 1; i <= Math.min(n + 2, doc.numPages); i++) {
        const b = await renderPage(doc, i, 600, true);
        state.teasers.push(b);
        addThumb(b, true);
      }
      setProgress(null);
      const chars = state.text.replace(/\s/g, '').length;
      setStatus(`<strong class="text-white">${esc(state.fileName || 'Saved PDF')}</strong> · ${doc.numPages} pages · ${n} free preview page${n > 1 ? 's' : ''} ready`
        + (withText ? (chars > 200 ? ` · ${chars.toLocaleString()} characters of text read` : ' · <span class="text-amber-200">no selectable text (scanned PDF?) — AI will rely on the file name and your instructions</span>') : '')
        + ' · <span class="text-neo-green">click Save to upload</span>');
    } finally {
      state.busy = false;
      try { await task.destroy(); } catch (e) { /* ignore */ }
    }
  }

  function addThumb(blob, locked) {
    const d = document.createElement('div');
    d.className = 'relative overflow-hidden rounded-lg border border-white/10 bg-white';
    d.innerHTML = `<img alt="" class="aspect-[210/297] w-full object-cover object-top" src="${URL.createObjectURL(blob)}">` + (locked ? '<span class="absolute inset-x-0 bottom-0 bg-black/70 py-0.5 text-center text-[10px] text-white">locked</span>' : '');
    grid.appendChild(d);
  }

  async function handleFile(file) {
    if (!file) return;
    if (!/\.pdf$/i.test(file.name) && file.type !== 'application/pdf') { setStatus('<span class="text-red-200">Please choose a PDF file.</span>'); return; }
    if (MAX_BYTES && file.size > MAX_BYTES) {
      setStatus(`<span class="text-red-200">This PDF is ${(file.size / 1048576).toFixed(1)} MB but the server accepts up to ${(MAX_BYTES / 1048576).toFixed(0)} MB. Increase upload_max_filesize and post_max_size in hPanel → PHP Configuration.</span>`);
      return;
    }
    state.fileName = file.name;
    try {
      state.buffer = await file.arrayBuffer();
      await processPdf(state.buffer, { withText: true });
      rerender.classList.remove('hidden');
      const title = form.querySelector('[name="title"]');
      if (form.dataset.aiReady === '1' && !title.value.trim()) runAI(false);
    } catch (e) {
      console.error(e);
      setProgress(null);
      setStatus(`<span class="text-red-200">Could not read this PDF in the browser (${esc(e.message || e)}). You can still save it — previews and AI need a readable PDF.</span>`);
    }
  }

  pdfInput.addEventListener('change', () => handleFile(pdfInput.files[0]));
  ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('border-neo-green/60'); }));
  ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.remove('border-neo-green/60'); }));
  drop.addEventListener('drop', (e) => {
    const f = e.dataTransfer && e.dataTransfer.files[0];
    if (!f) return;
    const dt = new DataTransfer();
    dt.items.add(f);
    pdfInput.files = dt.files;
    handleFile(f);
  });

  rerender.addEventListener('click', async () => {
    if (state.busy) return;
    try {
      if (!state.buffer) {
        setStatus('Downloading the saved PDF…');
        const r = await fetch(form.dataset.fileUrl, { credentials: 'same-origin' });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        state.buffer = await r.arrayBuffer();
        state.fileName = '';
      }
      await processPdf(state.buffer, { withText: !state.text });
    } catch (e) {
      setProgress(null);
      setStatus(`<span class="text-red-200">Could not render previews: ${esc(e.message || e)}</span>`);
    }
  });

  /* ---------- AI ---------- */
  const lines = (v) => (Array.isArray(v) ? v.join('\n') : (v || ''));
  function fill(name, value) {
    const el = form.querySelector(`[name="${name}"]`);
    if (el && value !== undefined && value !== null && String(value).trim() !== '') el.value = value;
  }

  async function runAI(ask) {
    if (aiBtn.disabled) return;
    const hasContent = ['description', 'highlights', 'contents'].some((n) => form.querySelector(`[name="${n}"]`).value.trim());
    if (ask && hasContent && !window.confirm('Replace the current title, description and other page text with a fresh AI draft?')) return;
    if (!state.text && !(form.dataset.id !== '0' && form.dataset.hasFile === '1') && !state.fileName) {
      say(aiStatus, 'Choose a PDF first — AI reads it to write the page.', 'error');
      return;
    }
    aiBtn.disabled = true;
    const label = aiBtn.textContent;
    aiBtn.textContent = 'Writing…';
    say(aiStatus, '✨ Reading your notes and writing the product page… (10–40 seconds)');
    try {
      const r = await fetch(form.dataset.aiUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({
          text: state.text.slice(0, 100000),
          product_id: form.dataset.id,
          file_name: state.fileName,
          page_count: state.pageCount || '',
          title_hint: form.querySelector('[name="title"]').value,
          instructions: document.getElementById('ai-instructions').value,
        }),
      });
      const res = await r.json().catch(() => ({ ok: false, error: 'Unexpected server response (HTTP ' + r.status + ').' }));
      if (!res.ok) throw new Error(res.error || 'AI request failed');
      const d = res.data;
      fill('title', d.title);
      fill('subtitle', d.subtitle);
      fill('category', d.category);
      fill('tags', d.tags);
      fill('description', d.description);
      fill('highlights', lines(d.highlights));
      fill('contents', lines(d.contents));
      fill('audience', lines(d.audience));
      fill('faqs', (d.faqs || []).map((f) => f.q + ' | ' + f.a).join('\n'));
      fill('seo_description', d.seo_description);
      document.getElementById('ai-used').value = '1';
      const inr = form.querySelector('[name="price_inr"]');
      const usd = form.querySelector('[name="price_usd"]');
      const hint = [];
      if (d.suggested_price_inr) { hint.push('₹' + d.suggested_price_inr); if (!inr.value) inr.value = d.suggested_price_inr; }
      if (d.suggested_price_usd) { hint.push('$' + d.suggested_price_usd); if (!usd.value) usd.value = d.suggested_price_usd; }
      if (hint.length) say(priceHint, 'AI price suggestion: ' + esc(hint.join(' / ')) + ' — empty price fields were pre-filled; change them freely.', 'ok');
      say(aiStatus, `✅ Draft written by <strong>${esc(d.engine)}</strong>${res.scanned ? ' (no text found in the PDF, so it is based on the file name — please review carefully)' : ''}. Review &amp; edit anything below, then <strong>Save</strong>.`, 'ok');
    } catch (e) {
      say(aiStatus, '❌ ' + esc(e.message || e), 'error');
    } finally {
      aiBtn.disabled = false;
      aiBtn.textContent = label;
    }
  }
  aiBtn.addEventListener('click', () => runAI(true));

  /* ---------- Save (XHR upload with progress) ---------- */
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (state.busy) { window.alert('Still preparing the PDF previews — please wait a moment.'); return; }
    errorsBox.classList.add('hidden');
    const fd = new FormData(form);
    state.previews.forEach((b, i) => fd.append('previews[]', b, `p${i + 1}.jpg`));
    state.teasers.forEach((b, i) => fd.append('teasers[]', b, `t${i + 1}.jpg`));
    const bar = document.getElementById('upload-progress');
    const xhr = new XMLHttpRequest();
    xhr.open('POST', form.getAttribute('action') || window.location.href);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.upload.onprogress = (ev) => {
      if (!ev.lengthComputable) return;
      bar.classList.remove('hidden');
      bar.firstElementChild.style.width = Math.round((ev.loaded / ev.total) * 100) + '%';
      saveBtn.textContent = 'Uploading… ' + Math.round((ev.loaded / ev.total) * 100) + '%';
    };
    xhr.onload = () => {
      let res = null;
      try { res = JSON.parse(xhr.responseText); } catch (err) { res = null; }
      if (res && res.ok && res.redirect) { window.location.href = res.redirect; return; }
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save product';
      bar.classList.add('hidden');
      const msgs = (res && res.errors) || ['Save failed (HTTP ' + xhr.status + '). If the PDF is large, increase upload_max_filesize / post_max_size on the server.'];
      errorsBox.innerHTML = msgs.map(esc).join('<br>');
      errorsBox.classList.remove('hidden');
      errorsBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    xhr.onerror = () => {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save product';
      errorsBox.textContent = 'Network error while uploading. Please try again.';
      errorsBox.classList.remove('hidden');
    };
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving…';
    xhr.send(fd);
  });
}

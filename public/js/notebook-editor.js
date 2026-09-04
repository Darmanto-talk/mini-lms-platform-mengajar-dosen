/* Editor notebook pelajaran — sel Markdown / Teks / Gambar / Video / Kode Dart /
   Callout / Latihan / Interaktif / Blok Kuis / Tugas / Proyek */
(function () {
  var editor = document.getElementById('nbEditor');
  if (!editor) return;

  var jsonInput = document.getElementById('cellsJson');
  var emptyMsg = document.getElementById('nbEmpty');
  var uploadUrl = editor.getAttribute('data-upload-url');
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  var LABEL = {
    markdown: 'Markdown', text: 'Teks', image: 'Gambar', video: 'Video',
    code: 'Kode Dart', callout: 'Callout', practice: 'Latihan', interactive: 'Interaktif',
    file: 'Dokumen', quiz: 'Blok Kuis', assignment: 'Blok Tugas', project: 'Blok Proyek'
  };
  var fileUploadUrl = (editor.getAttribute('data-file-url') || '');

  function el(tag, attrs, children) {
    var n = document.createElement(tag);
    attrs = attrs || {};
    Object.keys(attrs).forEach(function (k) {
      if (k === 'class') n.className = attrs[k];
      else if (k === 'text') n.textContent = attrs[k];
      else n.setAttribute(k, attrs[k]);
    });
    (children || []).forEach(function (c) { if (c) n.appendChild(c); });
    return n;
  }

  function field(labelText, control) {
    var l = el('label', { class: 'field' });
    l.appendChild(el('span', { text: labelText }));
    l.appendChild(control);
    return l;
  }

  function makeCell(cell) {
    var type = cell.type;
    // "project" = blok tugas dengan flag proyek — perlakukan sebagai assignment
    var isProject = false;
    if (type === 'project') { isProject = true; type = 'assignment'; }
    else if (type === 'assignment') { isProject = !!cell.project; }

    var tagLabel = isProject ? LABEL.project : (LABEL[type] || type);
    var wrap = el('div', { class: 'nb-e-cell', 'data-type': type });
    if (type === 'assignment') wrap.setAttribute('data-project', isProject ? '1' : '0');

    var head = el('div', { class: 'nb-e-head' });
    head.appendChild(el('span', { class: 'nb-e-tag', text: tagLabel }));
    var tools = el('div', { class: 'nb-e-tools' });
    var up = el('button', { type: 'button', class: 'nb-e-btn', title: 'Naik', text: '↑' });
    var dn = el('button', { type: 'button', class: 'nb-e-btn', title: 'Turun', text: '↓' });
    var rm = el('button', { type: 'button', class: 'nb-e-btn nb-e-rm', title: 'Hapus sel', text: '✕' });
    up.addEventListener('click', function () { if (wrap.previousElementSibling) editor.insertBefore(wrap, wrap.previousElementSibling); });
    dn.addEventListener('click', function () { if (wrap.nextElementSibling) editor.insertBefore(wrap.nextElementSibling, wrap); });
    rm.addEventListener('click', function () { wrap.remove(); refreshEmpty(); });
    tools.appendChild(up); tools.appendChild(dn); tools.appendChild(rm);
    head.appendChild(tools);
    wrap.appendChild(head);

    var body = el('div', { class: 'nb-e-body' });

    if (type === 'markdown' || type === 'text') {
      var ta = el('textarea', { class: 'code-area nb-f-source', rows: type === 'markdown' ? '8' : '4' });
      ta.value = cell.source || '';
      body.appendChild(ta);

    } else if (type === 'code') {
      var tc = el('textarea', { class: 'code-area nb-f-source', rows: '8', placeholder: 'void main() {\n  print(\'Halo Dart\');\n}' });
      tc.value = cell.source || '';
      body.appendChild(field('Kode Dart (tampil sebagai contoh)', tc));
      var gi = el('input', { type: 'text', class: 'nb-f-gist', placeholder: 'ID gist DartPad (opsional) — mis. e75b493d...' });
      gi.value = cell.gist || '';
      body.appendChild(field('Gist DartPad', gi));
      var runL = el('label', { class: 'row', style: 'gap:8px' });
      var run = el('input', { type: 'checkbox', class: 'nb-f-run', style: 'width:auto' });
      if (cell.run !== false) run.checked = true;
      runL.appendChild(run);
      runL.appendChild(el('span', { text: 'Sisipkan DartPad live yang bisa dijalankan mahasiswa' }));
      body.appendChild(runL);

    } else if (type === 'image') {
      var si = el('input', { type: 'text', class: 'nb-f-src', placeholder: 'storage/uploads/gambar.png atau https://...' });
      si.value = cell.src || '';
      body.appendChild(field('Sumber gambar', si));
      var ci = el('input', { type: 'text', class: 'nb-f-caption', placeholder: 'Keterangan (opsional)' });
      ci.value = cell.caption || '';
      body.appendChild(field('Keterangan', ci));
      var upRow = el('div', { class: 'row', style: 'gap:8px; align-items:center' });
      var file = el('input', { type: 'file', accept: 'image/*', style: 'max-width:220px' });
      var upBtn = el('button', { type: 'button', class: 'btn btn-sm', text: 'Unggah & pakai' });
      var upMsg = el('span', { class: 'small muted' });
      upBtn.addEventListener('click', function () {
        if (!file.files[0]) { upMsg.textContent = 'Pilih berkas dulu.'; return; }
        var fd = new FormData(); fd.append('image', file.files[0]);
        upMsg.textContent = 'Mengunggah…';
        fetch(uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (d && d.url) { si.value = d.url; upMsg.textContent = 'Terunggah.'; }
            else { upMsg.textContent = 'Gagal mengunggah.'; }
          })
          .catch(function () { upMsg.textContent = 'Gagal mengunggah.'; });
      });
      upRow.appendChild(file); upRow.appendChild(upBtn); upRow.appendChild(upMsg);
      body.appendChild(upRow);
      var prev = el('img', { class: 'nb-e-imgprev', alt: '' });
      function syncPrev() { if (si.value) { prev.src = si.value.match(/^https?:/) ? si.value : ('/' + si.value.replace(/^\/+/, '')); prev.hidden = false; } else { prev.hidden = true; } }
      si.addEventListener('input', syncPrev); syncPrev();
      body.appendChild(prev);

    } else if (type === 'video') {
      var vi = el('input', { type: 'url', class: 'nb-f-url', placeholder: 'https://youtu.be/... atau https://vimeo.com/...' });
      vi.value = cell.url || '';
      body.appendChild(field('URL video (YouTube / Vimeo)', vi));

    } else if (type === 'callout') {
      var cv = el('select', { class: 'nb-f-variant' });
      [['info', 'Info (biru)'], ['tip', 'Tips (hijau)'], ['warning', 'Perhatian (kuning)'], ['danger', 'Penting (merah)']].forEach(function (o) {
        var op = el('option', { value: o[0] }); op.textContent = o[1];
        if ((cell.variant || 'info') === o[0]) op.selected = true;
        cv.appendChild(op);
      });
      body.appendChild(field('Jenis sorotan', cv));
      var cc = el('textarea', { class: 'nb-f-source', rows: '3', placeholder: 'Isi catatan / sorotan (mendukung Markdown)' });
      cc.value = cell.source || '';
      body.appendChild(field('Isi', cc));

    } else if (type === 'practice') {
      var pp = el('textarea', { class: 'nb-f-prompt', rows: '3', placeholder: 'Deskripsi latihan — apa yang harus dikerjakan mahasiswa (Markdown)' });
      pp.value = cell.prompt || '';
      body.appendChild(field('Instruksi latihan', pp));
      var ps = el('textarea', { class: 'code-area nb-f-starter', rows: '5', placeholder: 'void main() {\n  // TODO: lengkapi\n}' });
      ps.value = cell.starter || '';
      body.appendChild(field('Kode awal (opsional)', ps));
      var pg = el('input', { type: 'text', class: 'nb-f-gist', placeholder: 'ID gist DartPad (opsional)' });
      pg.value = cell.gist || '';
      body.appendChild(field('Gist DartPad', pg));
      var psol = el('textarea', { class: 'code-area nb-f-solution', rows: '5', placeholder: 'Solusi contoh — disembunyikan di balik "Lihat contoh solusi"' });
      psol.value = cell.solution || '';
      body.appendChild(field('Contoh solusi (opsional)', psol));

    } else if (type === 'interactive') {
      var iu = el('input', { type: 'url', class: 'nb-f-url', placeholder: 'https://dartpad.dev/... atau URL alat interaktif lain' });
      iu.value = cell.url || '';
      body.appendChild(field('URL embed', iu));
      var ih = el('input', { type: 'number', class: 'nb-f-height', min: '160', max: '900', style: 'width:130px' });
      ih.value = cell.height || 460;
      body.appendChild(field('Tinggi (px)', ih));
      body.appendChild(el('p', { class: 'hint', text: 'Ditampilkan dalam iframe ter-sandbox. Pastikan situs mengizinkan embed.' }));

    } else if (type === 'file') {
      var fPath = el('input', { type: 'hidden', class: 'nb-f-path' });   fPath.value = cell.path || '';
      var fExt  = el('input', { type: 'hidden', class: 'nb-f-ext' });    fExt.value = cell.ext || '';
      var fSize = el('input', { type: 'hidden', class: 'nb-f-size' });   fSize.value = cell.size || 0;
      body.appendChild(fPath); body.appendChild(fExt); body.appendChild(fSize);

      var fName = el('input', { type: 'text', class: 'nb-f-name', placeholder: 'mis. Slide Pertemuan 1' });
      fName.value = cell.name || '';
      body.appendChild(field('Nama tampilan dokumen', fName));

      var fRow = el('div', { class: 'row', style: 'gap:8px; align-items:center' });
      var fInput = el('input', { type: 'file', accept: '.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.odt,.ods,.odp,.txt,.csv,.rtf,.zip,.rar' });
      var fBtn = el('button', { type: 'button', class: 'btn btn-sm', text: 'Unggah dokumen' });
      var fMsg = el('span', { class: 'small muted', text: cell.path ? ('Tersimpan: ' + (cell.name || cell.path.split('/').pop())) : '' });
      fBtn.addEventListener('click', function () {
        if (!fInput.files[0]) { fMsg.textContent = 'Pilih berkas dulu.'; return; }
        var fd = new FormData(); fd.append('file', fInput.files[0]);
        fMsg.textContent = 'Mengunggah…'; fBtn.disabled = true;
        fetch(fileUploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd })
          .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
          .then(function (res) {
            fBtn.disabled = false;
            if (res.ok && res.d.ok) {
              fPath.value = res.d.path; fExt.value = res.d.ext; fSize.value = res.d.size;
              if (!fName.value) fName.value = res.d.name;
              fMsg.textContent = 'Terunggah: ' + res.d.name + ' (' + res.d.ext.toUpperCase() + ')';
            } else {
              fMsg.textContent = (res.d && (res.d.message || (res.d.errors && res.d.errors.file && res.d.errors.file[0]))) || 'Gagal mengunggah.';
            }
          })
          .catch(function () { fBtn.disabled = false; fMsg.textContent = 'Gagal mengunggah.'; });
      });
      fRow.appendChild(fInput); fRow.appendChild(fBtn); fRow.appendChild(fMsg);
      body.appendChild(field('Berkas (PDF, DOCX, PPTX, XLSX, ZIP … maks 25 MB)', fRow));

    } else if (type === 'quiz') {
      body.appendChild(el('p', { class: 'hint', text: 'Blok ini menautkan ke kuis pelajaran. Setelah menyimpan pelajaran, atur waktu, nilai lulus, dan daftar soal di bagian "Pengaturan kuis" di bawah halaman.' }));

    } else if (type === 'assignment') {
      var pi = el('textarea', { class: 'code-area nb-f-prompt', rows: '4', placeholder: 'Instruksi tugas / proyek (mendukung Markdown)' });
      pi.value = cell.prompt || '';
      body.appendChild(field('Instruksi', pi));
      var ri = el('textarea', { class: 'nb-f-rubric', rows: '3', placeholder: 'mis. Kelengkapan jawaban (40), ketepatan algoritma/kode (40), kejelasan penjelasan (20)' });
      ri.value = cell.rubric || '';
      body.appendChild(field('Rubrik penilaian AI', ri));
      var mi = el('input', { type: 'number', class: 'nb-f-max', min: '1', style: 'width:130px' });
      mi.value = cell.max_score || 100;
      body.appendChild(field('Nilai maksimum', mi));
      var pj = el('label', { class: 'row', style: 'gap:8px' });
      var pjc = el('input', { type: 'checkbox', class: 'nb-f-project', style: 'width:auto' });
      if (isProject) pjc.checked = true;
      pj.appendChild(pjc);
      pj.appendChild(el('span', { text: 'Perlakukan sebagai Proyek (bukan tugas biasa)' }));
      body.appendChild(pj);
    }

    wrap.appendChild(body);
    return wrap;
  }

  function refreshEmpty() {
    if (emptyMsg) emptyMsg.hidden = editor.children.length > 0;
  }

  function addCell(type) {
    var seed = { type: type };
    if (type === 'code') seed.run = true;
    if (type === 'callout') seed.variant = 'info';
    if (type === 'interactive') seed.height = 460;
    if (type === 'assignment') { seed.max_score = 100; }
    if (type === 'project') { seed = { type: 'assignment', project: true, max_score: 100 }; }
    // batasi satu blok kuis & satu blok tugas/proyek
    if (type === 'quiz' && editor.querySelector('.nb-e-cell[data-type="quiz"]')) { alert('Hanya boleh ada satu Blok Kuis per pelajaran.'); return; }
    if ((type === 'assignment' || type === 'project') && editor.querySelector('.nb-e-cell[data-type="assignment"]')) { alert('Hanya boleh ada satu Blok Tugas/Proyek per pelajaran.'); return; }
    var node = makeCell(seed);
    editor.appendChild(node);
    refreshEmpty();
    node.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }

  function serialize() {
    var out = [];
    editor.querySelectorAll('.nb-e-cell').forEach(function (c) {
      var t = c.getAttribute('data-type');
      var get = function (sel) { var n = c.querySelector(sel); return n ? n.value : ''; };
      var checked = function (sel) { var n = c.querySelector(sel); return !!(n && n.checked); };
      if (t === 'markdown' || t === 'text') {
        var s = get('.nb-f-source').trim(); if (s) out.push({ type: t, source: s });
      } else if (t === 'code') {
        out.push({ type: 'code', source: get('.nb-f-source'), gist: get('.nb-f-gist').replace(/[^A-Za-z0-9]/g, ''), run: checked('.nb-f-run') });
      } else if (t === 'image') {
        var src = get('.nb-f-src').trim(); if (src) out.push({ type: 'image', src: src, caption: get('.nb-f-caption').trim() });
      } else if (t === 'video') {
        var u = get('.nb-f-url').trim(); if (u) out.push({ type: 'video', url: u });
      } else if (t === 'callout') {
        var cs = get('.nb-f-source').trim();
        if (cs) out.push({ type: 'callout', variant: get('.nb-f-variant') || 'info', source: cs });
      } else if (t === 'practice') {
        var pr = get('.nb-f-prompt').trim(), st = get('.nb-f-starter');
        if (pr || st.trim()) out.push({
          type: 'practice', prompt: pr, starter: st,
          solution: get('.nb-f-solution'), gist: get('.nb-f-gist').replace(/[^A-Za-z0-9]/g, '')
        });
      } else if (t === 'interactive') {
        var iu = get('.nb-f-url').trim();
        if (iu) out.push({ type: 'interactive', url: iu, height: parseInt(get('.nb-f-height'), 10) || 460 });
      } else if (t === 'file') {
        var fp = get('.nb-f-path').trim();
        if (fp) out.push({
          type: 'file', path: fp, ext: get('.nb-f-ext').trim(),
          size: parseInt(get('.nb-f-size'), 10) || 0, name: get('.nb-f-name').trim()
        });
      } else if (t === 'quiz') {
        out.push({ type: 'quiz' });
      } else if (t === 'assignment') {
        out.push({
          type: 'assignment', prompt: get('.nb-f-prompt').trim(), rubric: get('.nb-f-rubric').trim(),
          max_score: parseInt(get('.nb-f-max'), 10) || 100, project: checked('.nb-f-project')
        });
      }
    });
    return out;
  }

  // init
  (window.NB_INITIAL || []).forEach(function (cell) { editor.appendChild(makeCell(cell)); });
  refreshEmpty();

  document.querySelectorAll('.nb-add [data-add]').forEach(function (b) {
    b.addEventListener('click', function () { addCell(b.getAttribute('data-add')); });
  });

  var form = editor.closest('form');
  if (form) {
    form.addEventListener('submit', function (ev) {
      var cells;
      try {
        cells = serialize();
      } catch (err) {
        ev.preventDefault();
        alert('Terjadi kesalahan saat menyiapkan data notebook. Perubahan TIDAK disimpan agar materi lama tidak hilang.\n\n' + err);
        return;
      }
      // pengaman: jangan pernah menyimpan daftar kosong kalau di editor masih ada sel
      if (cells.length === 0 && editor.querySelectorAll('.nb-e-cell').length > 0) {
        if (! confirm('Semua sel terdeteksi kosong. Lanjut menyimpan pelajaran TANPA materi?')) {
          ev.preventDefault();
          return;
        }
      }
      jsonInput.value = JSON.stringify(cells);
    });
  }
})();

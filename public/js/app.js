// Konfirmasi hapus
document.addEventListener('click', function (ev) {
  var el = ev.target.closest('[data-confirm]');
  if (el && !confirm(el.getAttribute('data-confirm'))) {
    ev.preventDefault();
  }
});

// Salin ke clipboard (mis. sandi sementara hasil reset)
document.addEventListener('click', function (ev) {
  var b = ev.target.closest('[data-copy]');
  if (!b) return;
  var el = document.getElementById(b.getAttribute('data-copy'));
  if (!el) return;
  var text = el.textContent.trim();
  var done = function () {
    var orig = b.textContent;
    b.textContent = 'Tersalin ✓';
    setTimeout(function () { b.textContent = orig; }, 1800);
  };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(done).catch(function () { fallbackCopy(text, done); });
  } else {
    fallbackCopy(text, done);
  }
});
function fallbackCopy(text, done) {
  var ta = document.createElement('textarea');
  ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
  document.body.appendChild(ta); ta.focus(); ta.select();
  try { document.execCommand('copy'); done(); } catch (e) {}
  document.body.removeChild(ta);
}

// Hapus permanen (mata kuliah / akun mahasiswa): tombol aktif hanya bila
// teks konfirmasi diketik persis sama.
document.querySelectorAll('form[data-confirm-name]').forEach(function (f) {
  var input = f.querySelector('[data-match]');
  var btn = f.querySelector('button[type=submit]');
  if (!input || !btn) return;
  var check = function () {
    btn.disabled = input.value.trim().toLowerCase() !== input.getAttribute('data-match').toLowerCase();
  };
  input.addEventListener('input', check); check();
});

// Modal pratinjau PDF
(function () {
  var modal = document.getElementById('pdfModal');
  if (!modal) return;
  var frame = document.getElementById('pdfModalFrame');
  var title = document.getElementById('pdfModalTitle');
  var dl = document.getElementById('pdfModalDl');
  function open(url, name) {
    frame.src = url + '#view=FitH';
    title.textContent = name || 'Dokumen';
    dl.href = url;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
  }
  function close() {
    modal.hidden = true;
    frame.removeAttribute('src');
    document.body.style.overflow = '';
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-pdf-modal]');
    if (b) { e.preventDefault(); open(b.getAttribute('data-pdf-modal'), b.getAttribute('data-pdf-name')); return; }
    if (e.target.closest('[data-pdf-close]')) close();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) close(); });
})();

// Menu navigasi (mode HP)
(function () {
  var toggle = document.querySelector('[data-nav-toggle]');
  var links = document.getElementById('navLinks');
  if (!toggle || !links) return;
  var close = function () { links.classList.remove('is-open'); toggle.setAttribute('aria-expanded', 'false'); };
  toggle.addEventListener('click', function (e) {
    e.stopPropagation();
    var open = links.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  document.addEventListener('click', function (e) {
    if (links.classList.contains('is-open') && !links.contains(e.target)) close();
  });
  window.addEventListener('resize', close);
})();

// Opsi kuis: klik baris untuk memilih radio
document.querySelectorAll('.quiz-opt').forEach(function (opt) {
  var radio = opt.querySelector('input[type=radio]');
  if (!radio) return;
  function sync() {
    opt.parentNode.querySelectorAll('.quiz-opt').forEach(function (o) { o.classList.remove('chosen'); });
    if (radio.checked) opt.classList.add('chosen');
  }
  opt.addEventListener('click', function () { radio.checked = true; sync(); });
  radio.addEventListener('change', sync);
  sync();
});

// Timer kuis
(function () {
  var t = document.querySelector('[data-timer]');
  if (!t) return;
  var left = parseInt(t.getAttribute('data-timer'), 10) || 0;
  function tick() {
    if (left <= 0) {
      var f = document.getElementById('quizForm');
      if (f) f.submit();
      return;
    }
    var m = Math.floor(left / 60), s = left % 60;
    t.textContent = m + ':' + (s < 10 ? '0' + s : s);
    left--;
    setTimeout(tick, 1000);
  }
  tick();
})();

// Unggah gambar di editor pelajaran
(function () {
  var btn = document.getElementById('imgUploadBtn');
  var input = document.getElementById('imgUpload');
  var area = document.getElementById('contentArea');
  var msg = document.getElementById('imgUploadMsg');
  if (!btn || !input || !area) return;
  var token = (document.querySelector('meta[name=csrf-token]') || {}).content || '';

  btn.addEventListener('click', function () {
    if (!input.files || !input.files[0]) { msg.textContent = 'Pilih file gambar dulu.'; return; }
    var fd = new FormData();
    fd.append('image', input.files[0]);
    btn.disabled = true; msg.textContent = 'Mengunggah...';
    fetch(btn.getAttribute('data-upload-url'), {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
      body: fd
    }).then(function (r) { return r.json(); }).then(function (d) {
      btn.disabled = false;
      if (!d.ok) { msg.textContent = d.error || 'Gagal.'; return; }
      var pos = area.selectionStart || area.value.length;
      area.value = area.value.slice(0, pos) + '\n\n' + d.markdown + '\n\n' + area.value.slice(pos);
      msg.textContent = 'Gambar disisipkan. Ganti "keterangan gambar" bila perlu.';
      input.value = '';
    }).catch(function () { btn.disabled = false; msg.textContent = 'Gagal menghubungi server.'; });
  });
})();

// ---- Reader: lipat / buka sidebar daftar materi ----
(function () {
  var reader = document.getElementById('reader');
  if (!reader) return;

  // di layar kecil selalu mulai tertutup; di layar lebar ikuti pilihan tersimpan
  var small = window.matchMedia('(max-width: 900px)').matches;
  if (small || localStorage.getItem('reader.collapsed') === '1') {
    reader.classList.add('is-collapsed');
  }

  function toggle() {
    reader.classList.toggle('is-collapsed');
    localStorage.setItem('reader.collapsed', reader.classList.contains('is-collapsed') ? '1' : '0');
  }

  ['sideToggle', 'sideToggleBar'].forEach(function (id) {
    var b = document.getElementById(id);
    if (b) b.addEventListener('click', toggle);
  });
})();

// Flash toast: hilang otomatis (error 7 detik, lainnya 3.5 detik)
(function () {
  document.querySelectorAll('#flashWrap .flash').forEach(function (el) {
    var delay = el.classList.contains('error') ? 7000 : 3500;
    setTimeout(function () {
      el.classList.add('is-hiding');
      setTimeout(function () { el.remove(); }, 320);
    }, delay);
    el.addEventListener('click', function () { el.remove(); });
  });
})();

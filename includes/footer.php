    </div><!-- /.content -->
  </main>
</div><!-- /.app-layout -->

<!-- Inisialisasi Lucide Icons -->
<!-- Tombol menu layar sempit (BUG-08); dipisah agar tetap berfungsi walau CDN ikon gagal dimuat -->
<script>
(function () {
  var btn = document.getElementById('menuToggle'), sb = document.getElementById('sidebar'), bd = document.getElementById('sidebarBackdrop');
  if (!btn || !sb) return;
  function setOpen(o) { sb.classList.toggle('open', o); if (bd) bd.classList.toggle('show', o); btn.setAttribute('aria-expanded', o ? 'true' : 'false'); }
  btn.addEventListener('click', function () { setOpen(!sb.classList.contains('open')); });
  if (bd) bd.addEventListener('click', function () { setOpen(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
})();
</script>
<script>if (window.lucide) lucide.createIcons();</script>
</body>
</html>

#!/bin/bash
# Jalankan di folder proyek kedai_kopi_kita (yang sudah berisi .git dari Modul 7-8).
# Membuat commit 16-22 Modul 9. Jika "git status" menunjukkan perubahan, file akan dibagi per commit.
set -e
[ -d .git ] || { echo "Folder ini belum repo Git. Jalankan 'git init' dan commit Modul 7-8 terlebih dulu."; exit 1; }
c() { git add -- "${@:2}" 2>/dev/null || true; git diff --cached --quiet && echo "(lewati: tidak ada perubahan untuk '$1')" || git commit -q -m "$1"; }
c "test: add automated black-box test suite (58 test cases)" tests
c "fix: validate field length and range, hide database errors (BUG-01, BUG-04)" includes/helpers.php menu_process.php kategori_process.php bahan_baku_process.php resep_process.php
c "fix: validate stok masuk date and ingredient (BUG-02)" stok_masuk_process.php
c "fix: add CSRF protection to all POST forms (BUG-03)" includes/auth.php includes/header.php login.php menu.php kategori.php bahan_baku.php resep.php stok_masuk.php pos.php
c "fix: require whole-number portions; inclusive report period (BUG-05, BUG-06)" pos_process.php laporan.php
c "fix: hide empty menu groups and add mobile menu toggle (BUG-07, BUG-08)" includes/footer.php assets/css/style.css
c "docs: add testing report modul 9" "LAPORAN PRAKTIKUM 9.docx"
git log --oneline

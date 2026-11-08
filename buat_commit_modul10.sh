#!/bin/bash
# Jalankan di folder proyek kedai_kopi_kita (repo Git). Membuat commit 23-26 Modul 10.
set -e
[ -d .git ] || { echo "Folder ini belum repo Git."; exit 1; }
c() { git add -- "${@:2}" 2>/dev/null || true; git diff --cached --quiet && echo "(lewati: $1)" || git commit -q -m "$1"; }
c "feat: serve fonts and icons locally so the app runs offline" assets includes/header.php login.php
c "fix: show friendly 503 page when database is unavailable" config/database.php
c "test: add readiness tests TC-59 to TC-61 (61 test cases)" tests
c "docs: add final project report (modul 10)" "LAPORAN PRAKTIKUM 10.docx"
git log --oneline

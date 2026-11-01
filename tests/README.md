# Pengujian otomatis Modul 9

58 test case black box (login, master data, POS, stok masuk, dashboard, laporan, hak akses, end-to-end).
Setiap test case memberi input melalui HTTP, lalu membandingkan keluaran dan isi database dengan expected result.

**Peringatan:** skrip ini membuat ulang database `kedai_kopi_kita` (DROP + impor `sql/`). Jalankan hanya pada database uji.

```bash
pip install requests playwright && playwright install chromium   # playwright hanya untuk TC-58 (layar ponsel)
php -S 127.0.0.1:8080            # di folder aplikasi (terminal lain)
export DB_PASS="password_mysql_anda"   # kosongkan jika root tanpa password
python3 tests/tests.py hasil_pengujian.json
```
Keluaran: satu baris PASS/FAIL per test case, ringkasan `TOTAL ... PASS ... FAIL ...`, dan file JSON berisi input, expected, actual, status.
Perlu klien `mysql` di PATH dan aplikasi di `http://127.0.0.1:8080` (ubah dengan `BASE_URL`).

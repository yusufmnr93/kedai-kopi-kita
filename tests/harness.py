"""Harness pengujian Modul 9 — menjalankan test case sungguhan terhadap aplikasi (black box via HTTP)
dan memverifikasi hasilnya ke database. Expected result ditulis dari requirement (Modul 2-4), bukan dari kode."""
import requests, subprocess, re, json, sys, html, time, datetime, os
BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
B = os.environ.get("BASE_URL", "http://127.0.0.1:8080")
PW = os.environ.get("DB_PASS", "")   # password MySQL/MariaDB (user root)
def sql(q, db="kedai_kopi_kita"):
    r = subprocess.run(["mysql", "-uroot"] + ([f"-p{PW}"] if PW else []) + ["-N", "-B", db, "-e", q], capture_output=True, text=True)
    if r.returncode: raise RuntimeError(r.stderr)
    return [l.split("\t") for l in r.stdout.strip().split("\n")] if r.stdout.strip() else []
def one(q):
    r = sql(q); return r[0][0] if r else None
def reset(demo=True):
    """Buat ulang database kedai_kopi_kita dari sql/ agar setiap pengujian mulai dari data awal yang sama."""
    base = ["mysql", "-uroot"] + ([f"-p{PW}"] if PW else [])
    subprocess.run(base + ["-e", "drop database if exists kedai_kopi_kita"], check=True)
    subprocess.run(base, stdin=open(os.path.join(BASE, "sql", "kedai_kopi_kita.sql")), check=True)
    if demo:
        subprocess.run(base + ["kedai_kopi_kita"], stdin=open(os.path.join(BASE, "sql", "demo_data_modul8.sql")), check=True)
def text(h):
    h = re.sub(r"<script.*?</script>", " ", h, flags=re.S); h = re.sub(r"<style.*?</style>", " ", h, flags=re.S)
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", h))).strip()
class U:
    """Satu sesi pengguna (browser)."""
    def __init__(s, name=None, pw=None):
        s.s = requests.Session(); s.name = name
        if name: s.login(name, pw or name + "123")
    def tok(s):
        r = s.s.get(B + "/dashboard.php", allow_redirects=True)
        m = re.search(r'name="csrf-token" content="([^"]+)"', r.text)
        return m.group(1) if m else None
    def login(s, u, p):
        r = s.s.get(B + "/login.php"); m = re.search(r'name="csrf_token" value="([^"]+)"', r.text)
        d = {"username": u, "password": p}
        if m: d["csrf_token"] = m.group(1)
        return s.s.post(B + "/login.php", data=d, allow_redirects=True)
    def get(s, p, **k): return s.s.get(B + "/" + p, allow_redirects=k.pop("allow_redirects", True), **k)
    def post(s, p, d, tok=True, **k):
        d = dict(d)
        if tok:
            t = s.tok()
            if t: d["csrf_token"] = t
        return s.s.post(B + "/" + p, data=d, allow_redirects=k.pop("allow_redirects", True), **k)
    def flash(s, r):
        t = text(r.text); m = re.search(r"(berhasil[^.]*\.|wajib[^.]*\.|tidak[^.]*\.|harus[^.]*\.|sudah[^.]*\.|kurang[^.]*\.|belum[^.]*\.|SQLSTATE[^\n]{0,120})", t)
        return t
def rp(n): return "Rp " + f"{int(round(float(n))):,}".replace(",", ".")
RES = []
def case(id, ts, ref, kind, judul, input_, langkah, expected, fn):
    try:
        actual, ok = fn()
    except Exception as ex:
        actual, ok = f"EXCEPTION pada harness: {type(ex).__name__}: {ex}", False
    RES.append(dict(id=id, ts=ts, ref=ref, kind=kind, judul=judul, input=input_, langkah=langkah, expected=expected, actual=actual, status="PASS" if ok else "FAIL"))
    print(("PASS " if ok else "FAIL ") + id, "|", judul, "|", actual[:170])
def save(path):
    json.dump(RES, open(path, "w"), ensure_ascii=False, indent=1)

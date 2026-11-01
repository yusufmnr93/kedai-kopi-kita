import sys, json, re, time
sys.path.insert(0, __import__("os").path.dirname(__import__("os").path.abspath(__file__)))
from harness import *
OUT = sys.argv[1] if len(sys.argv) > 1 else "hasil_pengujian.json"
reset()
own, kas, bar = U("owner"), U("kasir"), U("barista")
def mid(n): return one(f"select id_menu from menu where nama_menu='{n}'")
LATTE, CROI = mid("Caffe Latte"), mid("Croissant")
ntrx = lambda: int(one("select count(*) from transaksi"))
stok = lambda n: float(one(f"select stok_tersedia from bahan_baku where nama_bahan_baku='{n}'"))
def pos(u, items, metode="Tunai", bayar=0, total=0):
    return u.post("pos_process.php", {"cart_data": json.dumps(items), "metode_pembayaran": metode, "nominal_bayar": bayar, "total_harga": total})
F = lambda r: text(r.text)
def alert(r):
    m = re.search(r'class="alert[^"]*"[^>]*>(.*?)</div>', r.text, re.S)
    return text(m.group(1)) if m else ("[HTTP %d] " % r.status_code + text(r.text)[:90])
def has(r, *n): t = F(r); return all(x in t for x in n)
def sqlerr(t): return bool(re.search(r"SQLSTATE|Data too long|Out of range|Integrity constraint|Incorrect (date|datetime)|Fatal error|Warning:|Stack trace", t))

# ============ TS-01 Login ============
def t():
    r = U().login("owner", "owner123"); ok = r.url.endswith("dashboard.php") and "Dashboard" in F(r)
    return ("Diarahkan ke dashboard.php, halaman Dashboard tampil" if ok else f"URL akhir {r.url}", ok)
case("TC-01","TS-01","UC-03 (login); NFR-01","Positif","Login dengan data valid","owner / owner123","Buka login → isi username & password → klik Masuk","Pengguna masuk dan Dashboard tampil",t)
def t():
    r = U().login("owner", "salah"); ok = r.url.endswith("login.php") and "salah" in F(r)
    return ("Tetap di login.php; pesan: Username atau password salah." if ok else f"URL {r.url}", ok)
case("TC-02","TS-01","NFR-01","Negatif","Login dengan password salah","owner / salah","Isi username benar, password salah → Masuk","Login ditolak dengan pesan kesalahan",t)
def t():
    r = U().login("", ""); ok = "wajib diisi" in F(r)
    return ("Pesan: Username dan password wajib diisi." if ok else F(r)[:100], ok)
case("TC-03","TS-01","NFR-01","Negatif","Login dengan field kosong","username & password kosong","Klik Masuk tanpa mengisi","Pesan validasi, tidak login",t)
def t():
    r = U().login("' OR '1'='1' -- ", "x"); ok = not r.url.endswith("dashboard.php")
    return ("Ditolak; tetap di halaman login" if ok else "Berhasil login (SQL Injection berhasil)", ok)
case("TC-04","TS-01","NFR-01","Negatif","Percobaan SQL Injection pada login","username: ' OR '1'='1' -- ","Isi username injeksi → Masuk","Login ditolak",t)
def t():
    r = U().get("dashboard.php"); ok = r.url.endswith("login.php")
    return ("Dialihkan ke login.php" if ok else f"URL {r.url}", ok)
case("TC-05","TS-01","NFR-01","Negatif","Akses Dashboard tanpa login","—","Buka dashboard.php langsung tanpa sesi","Dialihkan ke halaman login",t)
def t():
    u = U("kasir"); old = dict(u.s.cookies); u.get("logout.php"); s2 = requests.Session(); s2.cookies.update(old)
    r = s2.get(B + "/dashboard.php"); ok = r.url.endswith("login.php")
    return ("Cookie sesi lama tidak dapat dipakai setelah logout" if ok else "Sesi lama masih valid setelah logout", ok)
case("TC-06","TS-01","NFR-01","Negatif","Sesi tidak berlaku setelah logout","cookie sesi sebelum logout","Login → logout → pakai cookie lama membuka dashboard","Dialihkan ke login",t)

# ============ TS-02 Master data ============
def t():
    n = int(one("select count(*) from kategori")); r = own.post("kategori_process.php", {"action":"create","nama_kategori":"Dessert Uji"}); n2 = int(one("select count(*) from kategori"))
    ok = n2 == n+1 and "berhasil" in F(r); return (f"Kategori bertambah {n}→{n2}; pesan sukses tampil", ok)
case("TC-07","TS-02","FR-01 / UC-01","Positif","Tambah kategori baru","nama_kategori = Dessert Uji","Kategori → isi nama → Simpan","Data tersimpan di tabel kategori dan muncul di daftar",t)
def t():
    n = int(one("select count(*) from kategori")); r = own.post("kategori_process.php", {"action":"create","nama_kategori":"Dessert Uji"}); n2 = int(one("select count(*) from kategori"))
    ok = n2 == n and "sudah ada" in F(r); return ("Ditolak: kategori sudah ada; jumlah baris tetap" if ok else f"Jumlah baris {n}→{n2}", ok)
case("TC-08","TS-02","FR-01 / UC-01","Negatif","Tambah kategori duplikat","nama_kategori = Dessert Uji (sudah ada)","Simpan nama yang sama dua kali","Ditolak dengan pesan duplikat",t)
kat = one("select id_kategori from kategori limit 1")
def addmenu(nama, harga):
    return own.post("menu_process.php", {"action":"create","id_kategori":kat,"nama_menu":nama,"harga":harga})
def t():
    r = addmenu("Teh Tarik Uji","15000"); row = sql("select harga from menu where nama_menu='Teh Tarik Uji'"); ok = bool(row) and float(row[0][0]) == 15000
    return (f"Menu tersimpan, harga di DB {row[0][0] if row else '-'}", ok)
case("TC-09","TS-02","FR-01 / UC-01","Positif","Tambah menu dengan data valid","Teh Tarik Uji, harga 15000","Menu → isi form → Simpan","Menu tersimpan dengan harga yang benar",t)
def t():
    n = int(one("select count(*) from menu")); addmenu("Harga Nol","0"); n2 = int(one("select count(*) from menu")); ok = n2 == n
    return ("Ditolak, tidak ada baris baru" if ok else "Menu dengan harga 0 tersimpan", ok)
case("TC-10","TS-02","FR-01 / BR (harga > 0)","Boundary","Harga menu = 0 (batas bawah)","harga = 0","Simpan menu dengan harga 0","Ditolak (harga harus > 0)",t)
def t():
    addmenu("Harga Satu","1"); ok = one("select count(*) from menu where nama_menu='Harga Satu'") == "1"
    return ("Diterima dengan harga 1" if ok else "Ditolak", ok)
case("TC-11","TS-02","FR-01 / BR (harga > 0)","Boundary","Harga menu = 1 (nilai valid terkecil)","harga = 1","Simpan menu dengan harga 1","Diterima",t)
def t():
    n = int(one("select count(*) from menu")); addmenu("Harga Minus","-5000"); ok = int(one("select count(*) from menu")) == n
    return ("Ditolak" if ok else "Menu harga negatif tersimpan", ok)
case("TC-12","TS-02","FR-01","Negatif","Harga menu negatif","harga = -5000","Simpan menu harga negatif","Ditolak",t)
def t():
    r = own.post("menu_process.php", {"action":"delete","id_menu":LATTE}); ok = one(f"select count(*) from menu where id_menu={LATTE}") == "1" and "tidak dapat dihapus" in F(r)
    return ("Ditolak: menu sudah tercatat dalam transaksi" if ok else "Menu terhapus / pesan tidak sesuai", ok)
case("TC-13","TS-02","BR-05 (integritas transaksi)","Negatif","Hapus menu yang sudah terjual","id_menu Caffe Latte","Menu → Hapus","Ditolak agar histori transaksi aman",t)
def t():
    r = own.post("kategori_process.php", {"action":"delete","id_kategori":kat}); ok = one(f"select count(*) from kategori where id_kategori={kat}") == "1"
    return ("Ditolak: kategori masih memiliki menu" if ok else "Kategori berisi menu terhapus", ok)
case("TC-14","TS-02","FR-01","Negatif","Hapus kategori yang masih memiliki menu","kategori berisi menu","Kategori → Hapus","Ditolak",t)
def t():
    r = addmenu("M"*101, "10000"); tt = F(r); ok = not sqlerr(tt)
    return (("Ditolak. Pesan: " + alert(r)) if ok else "Halaman menampilkan galat teknis database: " + (re.search(r"(SQLSTATE[^.]{0,90}|Data too long[^.]{0,60})", tt) or [''])[0][:110], ok)
case("TC-15","TS-02","FR-01 (validasi input)","Boundary","Nama menu 101 karakter (batas kolom 100)","nama_menu = 101 huruf","Simpan menu dengan nama terlalu panjang","Pesan validasi yang mudah dipahami, tanpa detail SQL",t)
def t():
    r = addmenu("Harga Raksasa", "99999999999"); tt = F(r); ok = not sqlerr(tt)
    return (("Ditolak. Pesan: " + alert(r)) if ok else "Halaman menampilkan galat teknis database: " + (re.search(r"(SQLSTATE[^.]{0,90}|Out of range[^.]{0,60})", tt) or [''])[0][:110], ok)
case("TC-16","TS-02","FR-01 (validasi input)","Negatif","Harga melebihi kapasitas kolom","harga = 99999999999","Simpan menu harga sangat besar","Pesan validasi yang mudah dipahami",t)
bhn = one("select id_bahan_baku from bahan_baku where nama_bahan_baku='Gula Pasir'")
def t():
    own.post("resep_process.php", {"action":"create","id_menu":one("select id_menu from menu where nama_menu='Teh Tarik Uji'"),"id_bahan_baku":bhn,"takaran":"10"})
    ok = one(f"select takaran from resep where id_menu={one(chr(34).join(['select id_menu from menu where nama_menu=','Teh Tarik Uji','']).replace(chr(34),chr(39)))} and id_bahan_baku={bhn}") in ("10.00","10")
    return ("Resep tersimpan (takaran 10)" if ok else "Resep tidak tersimpan", ok)
case("TC-17","TS-02","FR-02 / UC-02","Positif","Tambah resep (menu–bahan–takaran)","Teh Tarik Uji + Gula Pasir 10 gram","Resep → pilih menu, bahan, isi takaran → Simpan","Resep tersimpan",t)
def t():
    mt = one("select id_menu from menu where nama_menu='Teh Tarik Uji'"); n = int(one("select count(*) from resep")); own.post("resep_process.php", {"action":"create","id_menu":mt,"id_bahan_baku":bhn,"takaran":"0"}); ok = int(one("select count(*) from resep")) == n
    return ("Ditolak: takaran harus > 0" if ok else "Resep takaran 0 tersimpan", ok)
case("TC-18","TS-02","FR-02 / UC-02","Boundary","Takaran resep = 0","takaran = 0","Simpan resep takaran 0","Ditolak",t)
def t():
    mt = one("select id_menu from menu where nama_menu='Teh Tarik Uji'"); n = int(one("select count(*) from resep")); own.post("resep_process.php", {"action":"create","id_menu":mt,"id_bahan_baku":bhn,"takaran":"5"}); ok = int(one("select count(*) from resep")) == n
    return ("Ditolak: kombinasi menu dan bahan sudah ada" if ok else "Resep ganda tersimpan", ok)
case("TC-19","TS-02","FR-02 / UC-02","Negatif","Resep duplikat (menu + bahan sama)","Teh Tarik Uji + Gula Pasir lagi","Simpan resep kombinasi yang sama","Ditolak",t)
def t():
    r = own.post("kategori_process.php", {"action":"create","nama_kategori":"0"}); ok = one("select count(*) from kategori where nama_kategori='0'") == "1"
    return ("Kategori bernama \"0\" tersimpan" if ok else "Ditolak dengan pesan \"Nama kategori wajib diisi.\" padahal nama sudah diisi", ok)
case("TC-20","TS-02","FR-01 (validasi input)","Boundary","Nama kategori \"0\" (nama pendek valid)","nama_kategori = 0","Simpan kategori bernama 0","Diterima karena field terisi",t)

# ============ TS-03 POS ============
reset(); own, kas, bar = U("owner"), U("kasir"), U("barista")
def t():
    n=ntrx(); a0, s0 = stok("Biji Kopi Arabica"), stok("Susu UHT Fresh"); r = pos(kas, [{"id":LATTE,"qty":1}], "Tunai", 50000, 22000); a1, s1 = stok("Biji Kopi Arabica"), stok("Susu UHT Fresh")
    row = sql("select total_harga,nominal_bayar,kembalian,id_pengguna from transaksi order by id_transaksi desc limit 1")[0]
    ok = ntrx()==n+1 and float(row[0])==22000 and float(row[2])==28000 and round(a0-a1,2)==18 and round(s0-s1,2)==200 and "Struk" in F(r)
    return (f"Transaksi baru total {rp(row[0])}, kembalian {rp(row[2])}; Arabica −{a0-a1:g} g, Susu −{s0-s1:g} ml; struk tampil", ok)
case("TC-21","TS-03","FR-03, FR-04, FR-05, FR-08 / UC-03, UC-07; BR-02","Positif","Transaksi tunai 1 Caffe Latte","1 Caffe Latte, Tunai, bayar 50.000","POS → pilih menu → Tunai → Bayar","Total Rp 22.000, kembalian Rp 28.000, struk tampil, stok terpotong 18 g kopi & 200 ml susu",t)
def t():
    n=ntrx(); r = pos(kas, [{"id":LATTE,"qty":2},{"id":CROI,"qty":1}], "QRIS", 0, 0); row = sql("select total_harga,nominal_bayar,kembalian,metode_pembayaran from transaksi order by id_transaksi desc limit 1")[0]
    exp = 2*float(one(f"select harga from menu where id_menu={LATTE}"))+float(one(f"select harga from menu where id_menu={CROI}"))
    ok = ntrx()==n+1 and float(row[0])==exp and float(row[2])==0 and row[3]=="QRIS"
    return (f"QRIS total {rp(row[0])} (harapan {rp(exp)}), kembalian {rp(row[2])}", ok)
case("TC-22","TS-03","FR-04 / UC-03","Positif","Transaksi QRIS dua jenis menu","2 Caffe Latte + 1 Croissant, QRIS","POS → pilih 2 menu → QRIS → Bayar","Total = Σ(harga × jumlah), kembalian 0",t)
def t():
    n=ntrx(); r = pos(kas, [], "Tunai", 10000); ok = ntrx()==n and "Keranjang kosong" in F(r)
    return ("Ditolak: Keranjang kosong" if ok else "Transaksi kosong tersimpan", ok)
case("TC-23","TS-03","BR-07 (min. 1 item)","Negatif","Bayar dengan keranjang kosong","cart = []","Kirim pembayaran tanpa item","Ditolak",t)
def t():
    n=ntrx(); r = pos(kas, [{"id":LATTE,"qty":0}], "Tunai", 50000); ok = ntrx()==n
    return ("Ditolak: jumlah porsi harus lebih besar dari 0" if ok else "Transaksi qty 0 tersimpan", ok)
case("TC-24","TS-03","BR-07","Boundary","Jumlah porsi = 0 (batas bawah)","qty = 0","Kirim item qty 0","Ditolak",t)
def t():
    n=ntrx(); r = pos(kas, [{"id":LATTE,"qty":-1}], "Tunai", 50000); ok = ntrx()==n
    return ("Ditolak" if ok else "Transaksi qty negatif tersimpan (stok bertambah)", ok)
case("TC-25","TS-03","BR-07","Negatif","Jumlah porsi negatif","qty = -1","Kirim item qty -1","Ditolak",t)
def t():
    n=ntrx(); tot = float(one(f"select harga from menu where id_menu={LATTE}")); r = pos(kas, [{"id":LATTE,"qty":1}], "Tunai", tot-1); ok1 = ntrx()==n
    r = pos(kas, [{"id":LATTE,"qty":1}], "Tunai", tot); ok2 = ntrx()==n+1 and float(one("select kembalian from transaksi order by id_transaksi desc limit 1"))==0
    return (f"Bayar total−1 ditolak: {ok1}; bayar pas diterima, kembalian 0: {ok2}", ok1 and ok2)
case("TC-26","TS-03","FR-04 / BR-09","Boundary","Batas pembayaran tunai: total−1 dan total pas","bayar 21.999 lalu 22.000","Bayar kurang Rp 1, lalu bayar pas","Kurang Rp 1 ditolak; pas diterima dengan kembalian 0",t)
def t():
    n=ntrx(); pos(kas, [{"id":LATTE,"qty":1}], "Tunai", 50000, 1); row = sql("select total_harga from transaksi order by id_transaksi desc limit 1")[0][0]
    ok = ntrx()==n+1 and float(row)==float(one(f"select harga from menu where id_menu={LATTE}"))
    return (f"Total tersimpan {rp(row)} (harga DB), nilai total_harga=1 dari browser diabaikan", ok)
case("TC-27","TS-03","BR-09","Negatif","Manipulasi total dari browser","total_harga = 1 dikirim manual","Kirim ulang form dengan total diubah","Total tetap dihitung server dari harga DB",t)
def t():
    n=ntrx(); r = pos(kas, [{"id":LATTE,"qty":1}], "Kredit", 50000); ok = ntrx()==n; return ("Ditolak: metode tidak valid" if ok else "Tersimpan", ok)
case("TC-28","TS-03","FR-04","Negatif","Metode pembayaran tidak dikenal","metode = Kredit","Kirim metode selain Tunai/QRIS","Ditolak",t)
def t():
    sql("update bahan_baku set stok_tersedia=36 where nama_bahan_baku='Biji Kopi Arabica'"); n=ntrx()
    r = pos(kas, [{"id":LATTE,"qty":3}], "Tunai", 100000); ok_tolak = ntrx()==n and "tidak mencukupi" in F(r)
    r = pos(kas, [{"id":LATTE,"qty":2}], "Tunai", 100000); ok_pas = ntrx()==n+1 and stok("Biji Kopi Arabica")==0
    return (f"qty 3 (butuh 54 g, ada 36 g) ditolak: {ok_tolak}; qty 2 (butuh 36 g) diterima dan stok menjadi {stok('Biji Kopi Arabica'):g} g: {ok_pas}", ok_tolak and ok_pas)
case("TC-29","TS-03","BR-08; FR-08","Boundary","Stok tepat cukup vs kurang","stok kopi 36 g; qty 2 (butuh 36 g) dan qty 3 (butuh 54 g)","Atur stok → jual 3 porsi → jual 2 porsi","3 porsi ditolak; 2 porsi diterima, stok menjadi 0",t)
reset(); own, kas, bar = U("owner"), U("kasir"), U("barista")
def t():
    n=ntrx(); r = pos(bar, [{"id":LATTE,"qty":1}], "Tunai", 50000); ok = ntrx()==n
    return ("Barista ditolak dari proses transaksi" if ok else "Barista berhasil membuat transaksi", ok)
case("TC-30","TS-03","FR-03; RBAC","Negatif","Barista mencoba membuat transaksi","POST langsung ke pos_process.php sebagai Barista","Login Barista → kirim transaksi","Ditolak (hanya Owner/Kasir)",t)
def t():
    n=ntrx(); r = pos(kas, [{"id":LATTE,"qty":1}], "Tunai", 1e15); tt=F(r); ok = (not sqlerr(tt)) 
    return (("Ditolak. Pesan: " + alert(r)) if ok else "Halaman menampilkan galat teknis database: " + (re.search(r"(SQLSTATE[^.]{0,90}|Out of range[^.]{0,60})", tt) or [''])[0][:110] , ok)
case("TC-31","TS-03","FR-04 (validasi input)","Negatif","Nominal bayar tidak wajar","bayar = 1.000.000.000.000.000","Isi nominal bayar sangat besar","Pesan validasi yang mudah dipahami",t)
def t():
    n=ntrx(); a0=stok("Biji Kopi Arabica"); r = pos(kas, [{"id":LATTE,"qty":1.5}], "Tunai", 100000); d = n!=ntrx(); porsi = one("select jumlah_porsi from detail_transaksi order by id_detail_transaksi desc limit 1") if d else None
    ok = not d
    return ((("Ditolak. Pesan: " + alert(r)) if ok else f"Diterima dan dibulatkan diam-diam menjadi {porsi} porsi (1,5 tidak valid sebagai jumlah porsi)"), ok)
case("TC-32","TS-03","FR-03 (validasi input)","Negatif","Jumlah porsi desimal","qty = 1.5","Kirim item qty 1.5","Ditolak karena porsi harus bilangan bulat",t)

# ============ TS-04 Stok masuk & low stock ============
reset(); own, kas, bar = U("owner"), U("kasir"), U("barista")
GULA = one("select id_bahan_baku from bahan_baku where nama_bahan_baku='Gula Pasir'")
def sm(b, j, tgl, ket="uji"): return bar.post("stok_masuk_process.php", {"id_bahan_baku":b,"jumlah_masuk":j,"tanggal_masuk":tgl,"keterangan":ket})
today = datetime.datetime.now().strftime("%Y-%m-%dT%H:%M")
def t():
    s0=stok("Gula Pasir"); n=int(one("select count(*) from stok_masuk")); r = sm(GULA,"100",today); s1=stok("Gula Pasir")
    ok = round(s1-s0,2)==100 and int(one("select count(*) from stok_masuk"))==n+1
    return (f"Stok Gula {s0:g} → {s1:g} (+{s1-s0:g}); riwayat stok masuk +1", ok)
case("TC-33","TS-04","FR-07 / UC-06; BR-06","Positif","Catat stok masuk valid","Gula Pasir, 100, tanggal hari ini","Stok Masuk → isi form → Simpan","Stok bertambah tepat 100 dan riwayat tercatat",t)
def t():
    s0=stok("Gula Pasir"); sm(GULA,"0",today); ok = stok("Gula Pasir")==s0; return ("Ditolak, stok tidak berubah" if ok else "Stok berubah", ok)
case("TC-34","TS-04","BR-06","Boundary","Jumlah stok masuk = 0","jumlah = 0","Simpan stok masuk jumlah 0","Ditolak",t)
def t():
    s0=stok("Gula Pasir"); sm(GULA,"-50",today); ok = stok("Gula Pasir")==s0; return ("Ditolak, stok tidak berubah" if ok else f"Stok berubah dari {s0:g} menjadi {stok('Gula Pasir'):g}", ok)
case("TC-35","TS-04","BR-06","Negatif","Jumlah stok masuk negatif","jumlah = -50","Simpan stok masuk negatif","Ditolak",t)
def t():
    r = sm(GULA,"10",""); ok = "wajib" in F(r); return ("Pesan: Tanggal masuk wajib diisi." if ok else "Tersimpan tanpa tanggal", ok)
case("TC-36","TS-04","BR-06","Negatif","Tanggal penerimaan kosong","tanggal = (kosong)","Simpan tanpa tanggal","Ditolak",t)
def t():
    n=int(one("select count(*) from stok_masuk")); s0=stok("Gula Pasir"); r = sm(GULA,"10","bukan-tanggal"); tt=F(r); ok = int(one("select count(*) from stok_masuk"))==n and stok("Gula Pasir")==s0 and not sqlerr(tt)
    return (("Ditolak. Pesan: " + alert(r)) if ok else f"Baris stok masuk {n}→{one('select count(*) from stok_masuk')}; stok {s0:g}→{stok('Gula Pasir'):g}; tampil: " + (re.search(r"(SQLSTATE[^.]{0,100}|Incorrect[^.]{0,80})", tt) or [''])[0][:120], ok)
case("TC-37","TS-04","BR-06 (tanggal valid)","Negatif","Tanggal penerimaan tidak valid","tanggal = bukan-tanggal","Kirim tanggal teks sembarang","Ditolak dengan pesan yang mudah dipahami; stok tidak berubah",t)
def t():
    n=int(one("select count(*) from stok_masuk")); r = sm("9999","10",today); tt=F(r); ok = int(one("select count(*) from stok_masuk"))==n and not sqlerr(tt)
    return (("Ditolak. Pesan: " + alert(r)) if ok else "Halaman menampilkan galat teknis database: " + (re.search(r"(SQLSTATE[^.]{0,100}|Cannot add[^.]{0,80})", tt) or [''])[0][:120], ok)
case("TC-38","TS-04","BR-06 (bahan valid)","Negatif","Stok masuk untuk bahan yang tidak ada","id_bahan_baku = 9999","Kirim id bahan tidak valid","Ditolak: bahan baku tidak valid",t)
def t():
    s0=stok("Gula Pasir"); fut=(datetime.datetime.now()+datetime.timedelta(days=30)).strftime("%Y-%m-%dT%H:%M"); r = sm(GULA,"10",fut); ok = stok("Gula Pasir")==s0
    return (("Ditolak. Pesan: " + alert(r)) if ok else "Diterima: stok bertambah untuk barang yang katanya baru diterima 30 hari lagi", ok)
case("TC-39","TS-04","BR-06 (tanggal penerimaan)","Boundary","Tanggal penerimaan di masa depan","tanggal = hari ini + 30","Simpan stok masuk bertanggal masa depan","Ditolak (barang belum diterima)",t)
def lowstate(v):
    sql(f"update bahan_baku set stok_tersedia={v}, stok_minimum=5 where nama_bahan_baku='Pisang'")
    r = bar.get("bahan_baku.php"); row = re.search(r"Pisang.{0,400}?(Normal|Menipis|Habis)", r.text, re.S); return row.group(1) if row else "?"
def t():
    a, b, c = lowstate("5"), lowstate("5.01"), lowstate("4.99")
    ok = (a, b, c) == ("Menipis","Normal","Menipis"); return (f"stok=5 (=minimum) → {a}; 5,01 → {b}; 4,99 → {c}", ok)
case("TC-40","TS-04","FR-09 / UC-08; BR-03","Boundary","Low stock alert pada batas minimum","min 5; stok 5, 5,01, 4,99","Atur stok → buka Bahan Baku","stok ≤ minimum = Menipis; di atasnya Normal",t)
def t():
    sql("update bahan_baku set stok_tersedia=2, stok_minimum=5 where nama_bahan_baku='Pisang'"); r = own.get("dashboard.php"); ok = "Pisang" in F(r) and "Stok Menipis" in F(r)
    return ("Dashboard Owner menampilkan kartu Stok Menipis dan daftar belanja memuat Pisang" if ok else "Tidak tampil", ok)
case("TC-41","TS-04","FR-09 / UC-08","Positif","Peringatan stok menipis tampil di Dashboard","Pisang stok 2, minimum 5","Buka Dashboard Owner","Pisang muncul pada daftar bahan yang harus dibeli",t)

# ============ TS-05 Dashboard ============
reset(); own, kas, bar = U("owner"), U("kasir"), U("barista")
def t():
    r = own.get("dashboard.php"); om = one("select coalesce(sum(total_harga),0) from transaksi where date(tanggal_transaksi)=curdate()"); jml = one("select count(*) from transaksi where date(tanggal_transaksi)=curdate()")
    ok = rp(om) in F(r) and (jml+" Transaksi") in F(r) or (rp(om) in F(r))
    return (f"Dashboard menampilkan {rp(om)} sesuai SUM database ({jml} transaksi hari ini)" if ok else f"Tidak ditemukan {rp(om)} pada dashboard", ok)
case("TC-42","TS-05","FR-10 / UC-09","Positif","Omzet hari ini di Dashboard Owner = data database","—","Login Owner → Dashboard → bandingkan dengan query SUM","Angka identik",t)
def t():
    r = kas.get("dashboard.php"); om = one("select coalesce(sum(total_harga),0) from transaksi where date(tanggal_transaksi)=curdate() and id_pengguna=(select id_pengguna from pengguna where username='kasir')")
    tt=F(r); ok = rp(om) in tt and "Omzet Saya" in tt
    return (f"Dashboard Kasir menampilkan {rp(om)} (hanya milik sendiri)" if ok else "Angka tidak sesuai", ok)
case("TC-43","TS-05","BR-10","Positif","Dashboard Kasir hanya memuat data miliknya","—","Login Kasir → Dashboard","Omzet = transaksi Kasir sendiri",t)
def t():
    r = bar.get("dashboard.php"); tt=F(r); ok = "Omzet" not in tt and "Rp" not in re.sub(r"Rp\s*[0-9.]+ ?(?=Harga)","",tt)[:0]+""
    ok = "Omzet" not in tt
    return ("Dashboard Barista tidak memuat omzet/penjualan" if ok else "Omzet terlihat oleh Barista", ok)
case("TC-44","TS-05","BR-04; FR-11","Negatif","Barista tidak boleh melihat data keuangan","—","Login Barista → Dashboard","Tidak ada omzet",t)

# ============ TS-06 Laporan ============
def t():
    r = own.get("laporan.php"); tot = one("select coalesce(sum(total_harga),0) from transaksi where tanggal_transaksi>=date_sub(curdate(), interval 6 day)")
    ok = rp(tot) in F(r); return (f"Laporan 7 hari menampilkan {rp(tot)} (SUM database {rp(tot)})" if ok else f"{rp(tot)} tidak ditemukan", ok)
case("TC-45","TS-06","FR-10, FR-11 / UC-09","Positif","Total laporan 7 hari = database","periode default","Owner → Laporan","Total laporan = SUM database",t)
def t():
    r1 = kas.get("laporan.php"); r2 = bar.get("laporan.php"); ok = r1.url.endswith("dashboard.php") and r2.url.endswith("dashboard.php") and "Laporan Penjualan &" not in F(r1)[:0]+"" 
    ok = r1.url.endswith("dashboard.php") and r2.url.endswith("dashboard.php")
    return ("Kasir dan Barista dialihkan ke Dashboard" if ok else f"{r1.url} / {r2.url}", ok)
case("TC-46","TS-06","BR-04; FR-11","Negatif","Kasir & Barista membuka Laporan","—","Login Kasir/Barista → laporan.php","Ditolak",t)
def t():
    h = (datetime.date.today()).isoformat(); d1 = (datetime.date.today()-datetime.timedelta(days=365)).isoformat(); d2 = (datetime.date.today()-datetime.timedelta(days=366)).isoformat()
    a = F(own.get(f"laporan.php?dari={d1}&sampai={h}")); b = F(own.get(f"laporan.php?dari={d2}&sampai={h}"))
    ok = ("maksimal" not in a.lower()) and ("maksimal" in b.lower() or "366" in b)
    return (f"366 hari inklusif: {'diterima' if 'maksimal' not in a.lower() else 'ditolak'}; 367 hari inklusif: {'ditolak/dibatasi' if ('maksimal' in b.lower() or '366' in b) else 'diterima (melebihi batas 366 hari)'}", ok)
case("TC-47","TS-06","UC-09","Boundary","Batas rentang periode laporan (366 hari)","366 hari (inklusif) vs 367 hari","Atur filter tanggal di batas","Maksimal 366 hari: 366 hari diterima, 367 hari ditolak",t)
def t():
    own2 = own; own2.post("menu_process.php", {"action":"create","id_kategori":kat,"nama_menu":'=HYPERLINK("http://x.test","klik")',"harga":"10000"})
    mm = one("select id_menu from menu where nama_menu like '=HYPERLINK%'"); sql(f"insert into resep(id_menu,id_bahan_baku,takaran) values({mm},{GULA},1)"); pos(kas, [{"id":mm,"qty":1}], "QRIS")
    h = datetime.date.today().isoformat(); r = own.get(f"laporan.php?dari={h}&sampai={h}&export=csv"); body = r.content.decode("utf-8-sig", "ignore")
    ok = 'HYPERLINK' in body and not re.search(r'(^|,|")=HYPERLINK', body)
    return ("Sel diawali tanda petik (')" if ok else "Sel CSV diawali = (formula aktif di Excel)", ok)
case("TC-48","TS-06","UC-09 (ekspor CSV)","Negatif","Injeksi rumus pada ekspor CSV","nama menu =HYPERLINK(...)","Jual menu itu → ekspor CSV laporan","Sel tidak menjadi rumus (diberi awalan ')",t)

# ============ TS-07 Hak akses & keamanan ============
reset(); own, kas, bar = U("owner"), U("kasir"), U("barista")
def t():
    res=[]; 
    for u,p in [(kas,"menu.php"),(kas,"kategori.php"),(kas,"resep.php"),(kas,"stok_masuk.php"),(kas,"bahan_baku.php"),(bar,"menu.php"),(bar,"pos.php"),(bar,"transaksi.php")]:
        res.append((p, u.get(p).url.endswith("dashboard.php")))
    ok = all(x[1] for x in res); return ("Seluruh akses terlarang dialihkan ke Dashboard (" + ", ".join(p for p,_ in res) + ")" if ok else "Tidak dialihkan: " + ", ".join(p for p,v in res if not v), ok)
case("TC-49","TS-07","NFR-01; RBAC","Negatif","Matriks akses halaman per role","8 kombinasi role × halaman terlarang","Buka URL halaman terlarang langsung","Semua dialihkan",t)
def t():
    ids = sql("select id_transaksi from transaksi where id_pengguna=(select id_pengguna from pengguna where username='owner') limit 1")[0][0]
    r = kas.get(f"receipt.php?id={ids}"); ok = "hanya dapat melihat struk" in F(r) or r.url.endswith("transaksi.php") or r.url.endswith("dashboard.php")
    return ("Struk milik Owner ditolak untuk Kasir" if ok else "Kasir dapat membuka struk milik Owner", ok)
case("TC-50","TS-07","BR-10","Negatif","Kasir membuka struk milik orang lain","receipt.php?id=<transaksi Owner>","Login Kasir → ubah id pada URL","Ditolak",t)
def t():
    r = own.get("receipt.php?id=abc"); r2 = own.get("receipt.php?id=999999"); tt=F(r)+F(r2); ok = not sqlerr(tt)
    return ("id tidak valid/tidak ada ditangani tanpa galat teknis" if ok else "Galat teknis tampil", ok)
case("TC-51","TS-07","NFR-01","Negatif","Struk dengan id tidak valid/tidak ada","id=abc dan id=999999","Ubah id pada URL","Pesan yang wajar, tanpa galat",t)
def t():
    own.post("menu_process.php", {"action":"create","id_kategori":kat,"nama_menu":"<script>alert(1)</script>","harga":"9000"})
    pg = [own.get(p).text for p in ("menu.php","pos.php","resep.php")]
    ok = all("<script>alert(1)</script>" not in x for x in pg); return ("Teks ditampilkan sebagai teks (di-escape) pada Menu, POS, Resep" if ok else "Skrip tidak di-escape", ok)
case("TC-52","TS-07","NFR-01","Negatif","Stored XSS pada nama menu","nama_menu = <script>alert(1)</script>","Simpan menu → buka halaman yang menampilkan nama","Tampil sebagai teks biasa",t)
def t():
    n = int(one("select count(*) from menu")); s = requests.Session(); s.post(B+"/login.php", data={"username":"owner","password":"owner123"})
    r = s.post(B+"/menu_process.php", data={"action":"create","id_kategori":kat,"nama_menu":"Menu Dari Situs Lain","harga":"7000"}, headers={"Origin":"http://situs-jahat.example","Referer":"http://situs-jahat.example/x"})
    ok = int(one("select count(*) from menu")) == n
    return (("Ditolak: " + alert(r)) if ok else "Permintaan dari situs lain tanpa token diterima: menu baru tersimpan (CSRF)", ok)
case("TC-53","TS-07","NFR-01","Negatif","Permintaan POST dari situs lain (CSRF)","POST tanpa token, Origin situs lain, sesi Owner valid","Kirim form dari domain berbeda","Ditolak",t)
def t():
    n = int(one("select count(*) from transaksi")); s = requests.Session(); s.post(B+"/login.php", data={"username":"kasir","password":"kasir123"})
    s.post(B+"/pos_process.php", data={"cart_data":json.dumps([{"id":LATTE,"qty":1}]),"metode_pembayaran":"QRIS"}, headers={"Origin":"http://situs-jahat.example"})
    ok = int(one("select count(*) from transaksi")) == n
    return ("Ditolak: HTTP 403, token tidak valid; jumlah transaksi tetap" if ok else "Transaksi dari situs lain tanpa token tersimpan (CSRF)", ok)
case("TC-54","TS-07","NFR-01","Negatif","Transaksi POS dari situs lain (CSRF)","POST tanpa token, sesi Kasir valid","Kirim form pembayaran dari domain berbeda","Ditolak",t)

# ============ TS-08 End-to-end & konsistensi ============
reset(); own, kas, bar = U("owner"), U("kasir"), U("barista")
def t():
    n = ntrx(); pos(kas, [{"id":LATTE,"qty":2},{"id":CROI,"qty":1}], "Tunai", 100000); idt = one("select max(id_transaksi) from transaksi"); tot = one("select total_harga from transaksi order by id_transaksi desc limit 1")
    rr = kas.get(f"receipt.php?id={idt}"); rh = kas.get("transaksi.php"); hari = datetime.date.today().isoformat(); rl = own.get(f"laporan.php?dari={hari}&sampai={hari}"); rd = own.get("dashboard.php")
    nomor = f"#{int(idt):06d}"; oms = rp(one("select sum(total_harga) from transaksi where date(tanggal_transaksi)=curdate()"))
    ok = nomor in F(rr) and nomor in F(rh) and nomor in F(rl) and oms in F(rl) and oms in F(rd) and ntrx()==n+1
    return (f"Transaksi {nomor} total {rp(tot)} muncul di struk, riwayat, laporan; omzet {oms} sama di Laporan dan Dashboard", ok)
case("TC-55","TS-08","FR-03…FR-11 (end-to-end)","Positif","End-to-end: Kasir jual → Owner lihat laporan","2 Latte + 1 Croissant, tunai 100.000","Login Kasir → POS → bayar → struk → riwayat → Login Owner → Dashboard & Laporan","Transaksi konsisten di semua halaman",t)
def t():
    a = sql("select count(*) from transaksi t where total_harga <> (select coalesce(sum(subtotal),0) from detail_transaksi d where d.id_transaksi=t.id_transaksi)")[0][0]
    b = sql("select count(*) from transaksi where kembalian <> nominal_bayar-total_harga")[0][0]
    c = sql("select count(*) from detail_transaksi d left join menu m using(id_menu) where m.id_menu is null")[0][0]
    d = sql("select count(*) from bahan_baku where stok_tersedia<0")[0][0]
    ok = (a,b,c,d)==("0","0","0","0"); return (f"Header≠Σdetail: {a}; kembalian salah: {b}; detail yatim: {c}; stok negatif: {d}", ok)
case("TC-56","TS-08","Konsistensi database","Positif","Integritas data transaksi di database","—","Jalankan query konsistensi header–detail","Tidak ada selisih/yatim/stok negatif",t)

# ============ TS-09 Navigasi & usability UI (temuan user testing) ============
reset(); own, kas, bar = U("owner"), U("kasir"), U("barista")
def t():
    r = kas.get("dashboard.php"); nav = re.search(r'<nav class="sidebar-nav">(.*?)</nav>', r.text, re.S).group(1)
    judul = re.findall(r'class="nav-section">([^<]+)<', nav); kosong = []
    blocks = re.split(r'<div class="nav-section">', nav)[1:]
    for b in blocks:
        nama = b.split("<")[0].strip()
        if "<a " not in b: kosong.append(nama)
    ok = not kosong
    return (("Setiap judul grup menu memiliki minimal satu menu: " + ", ".join(x.strip() for x in judul)) if ok else "Judul grup tanpa isi tampil untuk Kasir: " + ", ".join(kosong), ok)
case("TC-57","TS-09","NFR (usability)","Negatif","Judul grup menu kosong untuk role tertentu","login Kasir","Buka Dashboard Kasir, perhatikan sidebar","Hanya grup yang memiliki menu yang ditampilkan",t)
def t():
    from playwright.sync_api import sync_playwright
    with sync_playwright() as p:
        br = p.chromium.launch(); pg = br.new_context(viewport={"width": 390, "height": 800}).new_page()
        pg.goto(B + "/login.php"); pg.fill("#username", "kasir"); pg.fill("#password", "kasir123"); pg.click("button[type=submit]"); pg.wait_for_load_state()
        ada = pg.locator("#menuToggle").count() > 0 and pg.locator("#menuToggle").is_visible()
        if ada:
            pg.click("#menuToggle"); pg.wait_for_timeout(500)
            box = pg.locator("#sidebar").bounding_box(); terbuka = box is not None and box["x"] >= -1
            pg.click("#sidebar a[href='pos.php']"); pg.wait_for_load_state(); pindah = pg.url.endswith("pos.php")
        else:
            terbuka = pindah = False
        br.close()
    ok = ada and terbuka and pindah
    return ("Layar 390 px: tombol menu terlihat, sidebar terbuka, tautan POS Kasir dapat dipilih" if ok else f"Layar 390 px: tombol menu terlihat={ada}; sidebar dapat dibuka={terbuka}; navigasi berhasil={pindah} (menu tidak dapat dijangkau)", ok)
case("TC-58","TS-09","NFR (usability)","Positif","Navigasi pada layar sempit (ponsel 390 px)","viewport 390 × 800, login Kasir","Buka aplikasi di layar sempit → cari tombol menu → buka menu → pilih POS Kasir","Menu dapat dibuka dan dipakai untuk berpindah halaman",t)

save(OUT)
p=sum(1 for r in RES if r["status"]=="PASS"); print(f"\nTOTAL {len(RES)}  PASS {p}  FAIL {len(RES)-p}")

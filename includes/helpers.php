<?php
/**
 * includes/helpers.php
 * Fungsi bantu umum (format tampilan, validasi tanggal, pagination, CSV).
 * Ditambahkan pada Modul 8 agar format angka & escape konsisten di semua halaman.
 */

/** Escape output HTML (mencegah XSS) */
function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** 22000 -> "Rp 22.000" */
function rupiah($n): string {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

/** Kuantitas bahan baku: 12000.00 -> "12.000", 1.50 -> "1,5" */
function fmtQty($n): string {
    $s = number_format((float)$n, 2, ',', '.');
    return rtrim(rtrim($s, '0'), ',');
}

/** Validasi tanggal format Y-m-d (dan benar-benar ada di kalender) */
function validDate(?string $s): bool {
    if ($s === null || $s === '') return false;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d !== false && $d->format('Y-m-d') === $s;
}

/** Hitung data pagination */
function paginate(int $total, int $perPage, int $page): array {
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    return [
        'page'    => $page,
        'pages'   => $pages,
        'offset'  => ($page - 1) * $perPage,
        'perPage' => $perPage,
        'total'   => $total,
    ];
}

/** Query string saat ini (GET) dengan beberapa parameter diganti */
function queryString(array $override = []): string {
    $q = array_merge($_GET, $override);
    $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
    return http_build_query($q);
}

/** Cegah CSV/formula injection saat export ke Excel */
function csvSafe($v): string {
    $s = (string)$v;
    if ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) {
        $s = "'" . $s;
    }
    return $s;
}

/** Tanggal Indonesia singkat: 2026-10-25 -> 25/10/2026 */
function tglIndo(string $ymd): string {
    return date('d/m/Y', strtotime($ymd));
}

/* ===== Modul 9: validasi batas, pesan galat aman, dan proteksi CSRF ===== */

// Batas nilai kolom DECIMAL(10,2) pada database
const MAX_DESIMAL = 99999999.99;

/** Pesan galat yang aman ditampilkan: detail SQL tidak dibocorkan ke pengguna (BUG-01). */
function userError(Throwable $e): string {
    if ($e instanceof PDOException) {
        error_log('[DB] ' . $e->getMessage());
        return 'Data tidak dapat disimpan. Periksa kembali isian Anda (panjang teks atau besar angka mungkin melebihi batas).';
    }
    return $e->getMessage();
}

/** Lempar Exception jika teks melebihi panjang maksimum kolom. */
function cekPanjang(string $v, int $max, string $label): void {
    if (mb_strlen($v) > $max) throw new Exception($label . ' maksimal ' . $max . ' karakter.');
}

/** Lempar Exception jika angka melebihi batas kolom DECIMAL. */
function cekMaks(float $v, string $label, float $max = MAX_DESIMAL): void {
    if ($v > $max) throw new Exception($label . ' terlalu besar (maksimal ' . number_format(floor($max), 0, ',', '.') . ').');
}

/** Token CSRF per sesi (BUG-03). */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}
/** Tolak setiap permintaan POST yang tidak membawa token sesi yang sah. */
function csrfEnforce(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
    $t = $_POST['csrf_token'] ?? '';
    if (!is_string($t) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $t)) {
        http_response_code(403);
        echo '<!DOCTYPE html><meta charset="utf-8"><title>Permintaan ditolak</title>'
           . '<body style="font-family:sans-serif;max-width:480px;margin:15vh auto;text-align:center">'
           . '<h2>Permintaan ditolak</h2><p>Token keamanan formulir tidak valid atau sudah kedaluwarsa. '
           . 'Muat ulang halaman, lalu coba lagi.</p><p><a href="dashboard.php">Kembali ke aplikasi</a></p></body>';
        exit;
    }
}


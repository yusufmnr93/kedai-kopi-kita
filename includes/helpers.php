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

<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/lib/db.php';

$berisiko = [];
foreach (rows('SELECT username, nama, password_hash FROM pengguna WHERE aktif = 1 ORDER BY nama') as $r) {
    if (password_verify(DEFAULT_PASSWORD, $r['password_hash'])) {
        $berisiko[] = $r['nama'] . ' (' . $r['username'] . ')';
    }
}
echo count($berisiko), "\n";
foreach ($berisiko as $b) {
    echo $b, "\n";
}

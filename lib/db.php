<?php

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('Gagal terhubung ke basis data: ' . $e->getMessage());
        $rinci = APP_DEBUG
            ? '<pre style="background:#fff;padding:12px;border-radius:6px;overflow:auto;color:#c62828">'
              . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>'
            : '';
        exit(
            '<div style="font-family:Segoe UI,sans-serif;max-width:640px;margin:80px auto;padding:28px;'
            . 'border:1px solid #c62828;border-radius:10px;background:#fdecec">'
            . '<h2 style="margin:0 0 10px;color:#c62828">Gagal terhubung ke basis data</h2>'
            . '<p style="color:#474f63;line-height:1.6">Layanan basis data sedang tidak dapat dihubungi. '
            . 'Silakan coba beberapa saat lagi. Bila berulang, hubungi pengelola sistem.</p>'
            . $rinci . '</div>'
        );
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function val(string $sql, array $params = [], $default = null)
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? $default : $v;
}

function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql  = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES ('
          . implode(',', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int) db()->lastInsertId();
}

function update(string $table, array $data, string $where, array $whereParams = []): int
{
    $set = [];
    foreach (array_keys($data) as $c) {
        $set[] = '`' . $c . '`=?';
    }
    $sql = 'UPDATE `' . $table . '` SET ' . implode(',', $set) . ' WHERE ' . $where;
    return q($sql, array_merge(array_values($data), $whereParams))->rowCount();
}

function in_placeholder(array $ids): string
{
    return $ids ? implode(',', array_fill(0, count($ids), '?')) : 'NULL';
}

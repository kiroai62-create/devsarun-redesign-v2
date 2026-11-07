<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = config('db');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int)($c['port'] ?? 3306), $c['name']);
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $pdo->exec("SET time_zone = '" . date('P') . "', sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
    return $pdo;
}

function db_query(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    foreach (array_values($params) as $i => $p) {
        $type = is_int($p) ? PDO::PARAM_INT : (is_null($p) ? PDO::PARAM_NULL : (is_bool($p) ? PDO::PARAM_BOOL : PDO::PARAM_STR));
        $st->bindValue($i + 1, $p, $type);
    }
    $st->execute();
    return $st;
}

function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

function db_row(string $sql, array $params = []): ?array
{
    $r = db_query($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function db_val(string $sql, array $params = []): mixed
{
    $v = db_query($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function db_exec(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

function db_insert(string $table, array $data): int
{
    $cols = array_keys($data);
    foreach ($cols as $c) {
        if (!preg_match('/^[a-z_]+$/', $c)) {
            throw new InvalidArgumentException('Bad column');
        }
    }
    $sql = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s)', $table, implode('`,`', $cols), implode(',', array_fill(0, count($cols), '?')));
    db_query($sql, array_values($data));
    return (int)db()->lastInsertId();
}

function db_update(string $table, array $data, string $where, array $whereParams = []): int
{
    $sets = [];
    foreach (array_keys($data) as $c) {
        if (!preg_match('/^[a-z_]+$/', $c)) {
            throw new InvalidArgumentException('Bad column');
        }
        $sets[] = "`$c` = ?";
    }
    return db_exec(sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where), [...array_values($data), ...$whereParams]);
}

<?php

const PIALERT_REMEMBER_COOKIE = 'PiAlert_SaveLogin';
const PIALERT_REMEMBER_LIFETIME = 604800;

function pialert_auth_tokens_initialize(SQLite3 $database): bool {
    return $database->exec(
        'CREATE TABLE IF NOT EXISTS "AuthTokens" (' .
        '"TokenHash" TEXT PRIMARY KEY, ' .
        '"ExpiresAt" INTEGER NOT NULL, ' .
        '"CreatedAt" INTEGER NOT NULL' .
        ')'
    );
}

function pialert_remember_cookie_value(): string {
    $value = $_COOKIE[PIALERT_REMEMBER_COOKIE] ?? '';
    return is_string($value) ? trim($value) : '';
}

function pialert_remember_token_hash(string $token): string {
    return hash('sha256', $token);
}

function pialert_cleanup_remember_tokens(SQLite3 $database, ?int $now = null): void {
    $now = $now ?? time();
    db_execute_prepared(
        $database,
        'DELETE FROM "AuthTokens" WHERE "ExpiresAt" < :now',
        array(':now' => array($now, SQLITE3_INTEGER))
    );
}

function pialert_store_remember_token(SQLite3 $database, string $token, int $expires): bool {
    if (!pialert_auth_tokens_initialize($database)) {
        return false;
    }
    $result = db_execute_prepared(
        $database,
        'INSERT INTO "AuthTokens" ("TokenHash", "ExpiresAt", "CreatedAt") VALUES (:hash, :expires, :created)',
        array(
            ':hash' => pialert_remember_token_hash($token),
            ':expires' => array($expires, SQLITE3_INTEGER),
            ':created' => array(time(), SQLITE3_INTEGER),
        )
    );
    return $result !== false;
}

function pialert_issue_remember_token(SQLite3 $database, ?int $now = null): ?string {
    if (!pialert_auth_tokens_initialize($database)) {
        return null;
    }
    $now = $now ?? time();
    $expires = $now + PIALERT_REMEMBER_LIFETIME;
    pialert_cleanup_remember_tokens($database, $now);

    $token = bin2hex(random_bytes(32));
    if (!pialert_store_remember_token($database, $token, $expires)) {
        return null;
    }
    if (!pialert_set_auth_cookie(PIALERT_REMEMBER_COOKIE, $token, $expires)) {
        db_execute_prepared(
            $database,
            'DELETE FROM "AuthTokens" WHERE "TokenHash" = :hash',
            array(':hash' => pialert_remember_token_hash($token))
        );
        return null;
    }
    return $token;
}

function pialert_delete_remember_token_hash(SQLite3 $database, string $tokenHash): void {
    db_execute_prepared(
        $database,
        'DELETE FROM "AuthTokens" WHERE "TokenHash" = :hash',
        array(':hash' => $tokenHash)
    );
}

function pialert_consume_remember_token(SQLite3 $database, ?int $now = null): bool {
    $token = pialert_remember_cookie_value();
    $now = $now ?? time();

    // Legacy password-hash cookies are also 64 hex characters, but cannot
    // authenticate because only hashes of random tokens exist server-side.
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        if ($token !== '') {
            pialert_delete_auth_cookie(PIALERT_REMEMBER_COOKIE);
        }
        return false;
    }
    if (!pialert_auth_tokens_initialize($database)) {
        return false;
    }

    $tokenHash = pialert_remember_token_hash($token);
    $result = db_execute_prepared(
        $database,
        'SELECT "TokenHash", "ExpiresAt" FROM "AuthTokens" WHERE "TokenHash" = :hash LIMIT 1',
        array(':hash' => $tokenHash)
    );
    $row = $result === false ? false : $result->fetchArray(SQLITE3_ASSOC);
    if (!$row || !hash_equals((string) $row['TokenHash'], $tokenHash) || (int) $row['ExpiresAt'] < $now) {
        pialert_delete_remember_token_hash($database, $tokenHash);
        pialert_delete_auth_cookie(PIALERT_REMEMBER_COOKIE);
        return false;
    }

    // Successful use rotates the bearer token. A copied old cookie therefore
    // stops working after the legitimate browser uses it once.
    pialert_delete_remember_token_hash($database, $tokenHash);
    return pialert_issue_remember_token($database, $now) !== null;
}

function pialert_revoke_current_remember_token(SQLite3 $database): void {
    $token = pialert_remember_cookie_value();
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        pialert_delete_remember_token_hash($database, pialert_remember_token_hash($token));
    }
    pialert_delete_auth_cookie(PIALERT_REMEMBER_COOKIE);
}

function pialert_revoke_all_remember_tokens(SQLite3 $database): void {
    if (pialert_auth_tokens_initialize($database)) {
        $database->exec('DELETE FROM "AuthTokens"');
    }
    pialert_delete_auth_cookie(PIALERT_REMEMBER_COOKIE);
}

?>

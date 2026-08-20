<?php
/**
 * config/database.php
 * -----------------------------------------------------------------------
 * PDO_SQLSRV Database Connection (Singleton)
 * -----------------------------------------------------------------------
 * Provides a single shared PDO instance for the whole request lifecycle.
 * Uses prepared statements everywhere -> protects against SQL Injection.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Database
{
    private static ?PDO $instance = null;

    /**
     * Returns a singleton PDO connection using the sqlsrv driver.
     * Throws a PDOException on failure - callers should catch this
     * or let the global error handler deal with it.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'sqlsrv:Server=%s,%s;Database=%s;Encrypt=%s;TrustServerCertificate=%s',
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_ENCRYPT,
                    DB_TRUST_SERVER_CERTIFICATE
                );

                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    /*PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements*/
                    PDO::SQLSRV_ATTR_ENCODING    => PDO::SQLSRV_ENCODING_UTF8,
                ];

                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Never leak connection strings / credentials to the client
                error_log('Database connection failed: ' . $e->getMessage());

                $detail = APP_ENV === 'development'
                    ? $e->getMessage()
                    : 'A system error occurred. Please try again later.';

                // Every page in this app calls the DB through an AJAX endpoint
                // (login, add product, etc. all POST/GET to a *Controller.php
                // and expect JSON back). Dying with plain text here broke that -
                // jQuery couldn't parse it, so the browser only ever showed the
                // generic "Something went wrong" fallback instead of the real
                // reason. Respond in the shape the caller actually expects.
                $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

                if ($isAjax) {
                    http_response_code(500);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Could not connect to the database. ' . $detail,
                    ]);
                    exit;
                }

                die('Database connection failed: ' . $detail);
            }
        }

        return self::$instance;
    }

    /**
     * Begin a transaction helper (used by modules that write to
     * multiple tables at once, e.g. Sales + Sale Details).
     */
    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }

    public static function rollBack(): bool
    {
        return self::getConnection()->rollBack();
    }

    // Prevent cloning / unserialization of the singleton
    private function __clone() {}
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize a singleton.');
    }
}

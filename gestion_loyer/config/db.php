<?php
// ============================================================
//   config/db.php — Connexion PDO MySQL (Docker Edition)
// ============================================================

define('DB_HOST', 'mysql_loyer'); // ✔️ تم التعديل ليتطابق مع اسم الحاوية عندك
define('DB_NAME', 'gestion_loyer');
define('DB_USER', 'root');
define('DB_PASS', 'root_password'); // تأكد أن هذه الباسورد تطابق المكتوبة في الـ Compose
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;padding:2rem;color:#c0392b;background:#fdf0ee;border:1px solid #e74c3c;border-radius:8px;margin:2rem auto;max-width:600px;">
                <h2>❌ Erreur de connexion à la base de données</h2>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <p><strong>Vérifiez :</strong><br>
                1. Le conteneur Docker MySQL (db) est démarré<br>
                2. La base <code>gestion_loyer</code> existe (importez le fichier SQL via phpMyAdmin)<br>
                3. Les identifiants dans config/db.php match avec docker-compose.yml</p>
            </div>');
        }
    }
    return $pdo;
}

// Helpers globaux
function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function formatTND(float $amount): string {
    return number_format($amount, 3, ',', ' ');
}

function flash(string $key, string $msg = null): ?string {
    if (!isset($_SESSION)) session_start();
    if ($msg !== null) {
        $_SESSION['flash'][$key] = $msg;
        return null;
    }
    $val = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $val;
}
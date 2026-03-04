<?php
// ============================================================
//  db.php  –  Connexion PDO à MySQL
// ============================================================

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'suivi_factures_dp');
define('DB_USER', 'root');          // ← changer selon votre config
define('DB_PASS', '');              // ← changer selon votre config
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En production : loguer l'erreur, ne pas afficher les détails
            die(json_encode(['error' => 'Connexion base de données impossible.']));
        }
    }
    return $pdo;
}

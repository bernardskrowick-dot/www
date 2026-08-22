<?php
 /* $pdo = new PDO(
    "mysql:host=localhost;dbname=ii3jbzwv_gestion_echeances;charset=utf8mb4",
    "root",
    "Mk7@dkot#7/71"
);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); */
?>
<?php
/* Version: v1.0.0 - 2026-08-11 */
/**
 * =====================================================
 * CONNEXION DOUBLE : LOCALE & DISTANTE
 * =====================================================
 */

// --- 1. CONNEXION LOCALE (Serveur Ubuntu 22.04) ---
$host_local = 'localhost';
$db_local   = 'ii3jbzwv_gestion_echeances';
$user_local = 'root';
$pass_local = 'Mk7@dkot#7/71';

try {
    $pdo_local = new PDO("mysql:host=$host_local;dbname=$db_local;charset=utf8mb4", $user_local, $pass_local, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true, // 👈 Remettre à true résout immédiatement le souci des UNION avec le même paramètre
    ]);
} catch (PDOException $e) {
    die("Erreur de connexion locale : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// --- 2. CONNEXION DISTANTE (Hébergeur) ---
$host_distant = '81.88.53.128';
$port_distant = '3306';
$db_distant   = 'ii3jbzwv_gestion_echeances';
$user_distant = 'ii3jbzwv_EricB';
$pass_distant = 'Mk7@dkot#7/71';

$pdo_distant = null;
try {
    // On passe explicitement le port séparément pour éviter les erreurs de résolution
    $dsn_distant = "mysql:host={$host_distant};port={$port_distant};dbname={$db_distant};charset=utf8mb4";

    $pdo_distant = new PDO($dsn_distant, $user_distant, $pass_distant, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
    ]);
} catch (PDOException $e) {
    // Log discret pour ne pas bloquer l'application locale
    error_log("Erreur de connexion distante (synchro) : " . $e->getMessage());
}

// Rétrocompatibilité avec votre code existant ($pdo pointe vers le local)
$pdo = $pdo_local;
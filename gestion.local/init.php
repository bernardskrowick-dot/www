<?php
/* Version: v1.5.1 (Rev #14) - 2026-08-01 */

/**
 * =====================================================
 * INIT.PHP – LE CERVEAU DU PROJET
 * =====================================================
 * RÔLE : Centraliser la sécurité, la session et les
 * inclusions indispensables avant tout affichage.
 * -----------------------------------------------------
 */

/* --- 1. SÉCURISATION DES COOKIES (HTTPS) --- */
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

/* --- 2. GESTION DE LA SESSION --- */
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 0,          // Expire à la fermeture du navigateur
        'cookie_secure'   => $isHttps,   // Uniquement sur HTTPS si disponible
        'cookie_httponly' => true,       // Protection XSS
        'cookie_samesite' => 'Strict'    // Protection CSRF
    ]);
}

/* --- 3. INCLUSIONS DES CONSTANTES ET FONCTIONS --- */
define('DIR_INCLUDES', __DIR__ . '/includes/');

if (file_exists(DIR_INCLUDES . 'config.php')) {
    require_once DIR_INCLUDES . 'config.php';
    require_once DIR_INCLUDES . 'connexion.php';
    require_once DIR_INCLUDES . 'fonctions.php';

    // Dictionnaire de titres (pour la Topbar)
    if (file_exists(DIR_INCLUDES . 'titres_pages.php')) {
        require_once DIR_INCLUDES . 'titres_pages.php';
    }
} else {
    die('❌ Fichier de configuration introuvable dans /includes/config.php');
}

/* --- 4. PROTECTION CONTRE LE CACHE NAVIGATEUR --- */
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

/* --- 5. CONTRÔLE D'ACCÈS (AUTHENTIFICATION) --- */
$user_id = $_SESSION['user_id'] ?? null;

// On récupère la page demandée depuis $_GET['p'] ou l'URL directe
$requestedPage = $_GET['p'] ?? basename($_SERVER['PHP_SELF']);


// Pages publiques accessibles sans connexion (ou en attente de 2FA)
$publicPages = ['login.php', 'login', 'verify-2fa.php'];

if (!$user_id && !in_array($requestedPage, $publicPages, true)) {
    // Si BASE_URL n'est pas encore définie dans config.php, fallback sur login.php
    $loginUrl = defined('BASE_URL') ? BASE_URL . 'login.php' : 'login.php';
    header('Location: ' . $loginUrl);
    exit;
}
/* --- 6. VÉRIFICATION SCAN SILENCIEUX (ADMIN) --- */
if ($user_id && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    // On vérifie si le délai de 12 heures est dépassé dans app_versions via updated_at
    try {
        $stmtCheck = $pdo->query("SELECT updated_at FROM app_versions ORDER BY id DESC LIMIT 1");
        $appVersion = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $doScan = false;
        if (!$appVersion || empty($appVersion['updated_at'])) {
            // Si aucune version n'existe, on lance le scan
            $doScan = true;
        } else {
            // On calcule l'écart en secondes (12 heures = 43200 secondes)
            $lastUpdated = strtotime($appVersion['updated_at']);
            if ((time() - $lastUpdated) >= 43200) {
                $doScan = true;
            }
        }

        if ($doScan) {
            $pathStructure = __DIR__ . '/admin/structure_projet.php';
            if (!file_exists($pathStructure)) {
                $pathStructure = __DIR__ . '/structure_projet.php';
            }

            if (file_exists($pathStructure)) {
                $onlyLogic = true;
                require_once $pathStructure;
                if (function_exists('scanSilencieux')) {
                    scanSilencieux($pdo, $_SESSION['username'] ?? 'Admin');
                }
            }
        }
    } catch (Exception $e) {
        // En cas d'erreur sur la table, on log discrètement sans bloquer le site
        error_log('Erreur scan silencieux init.php: ' . $e->getMessage());
    }
}
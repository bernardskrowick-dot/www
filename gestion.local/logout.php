<?php
/* Version: v1.3.0 (Rev #4) - 2026-07-30 */

// 🔒 Chargement de la configuration pour récupérer BASE_URL et démarrer la session de façon sécurisée
require_once __DIR__ . '/includes/config.php';

// Déterminer si HTTPS est actif
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

// Démarrage ou récupération de la session active pour pouvoir la détruire
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 0,
        'cookie_secure' => $isHttps,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

// ==========================
// DESTRUCTION SÉCURISÉE SESSION
// ==========================

// 🔹 Vider les variables de session
$_SESSION = [];

// 🔹 Supprimer le cookie de session si présent
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,  // expiration dans le passé
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 🔹 Détruire la session côté serveur
session_destroy();

// Optionnel : régénérer l'ID de session
// session_regenerate_id(true);

// ==========================
// REDIRECTION
// ==========================
header('Location: ' . BASE_URL . 'login.php');
exit;
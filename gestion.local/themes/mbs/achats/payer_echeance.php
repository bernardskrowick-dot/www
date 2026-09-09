<?php
/* Version: v1.19.2 (Rev #26) - 2026-08-16 */

/**
 * payer_echeance.php - Version intégrée au Routeur
 * RÈGLES : CSRF, PDO, Commentaires, Constantes.
 */

// 🔒 Sécurité : Le header est déjà inclus par router.php, on vérifie la session
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}
/* ----------------------------- Sécurité ----------------------------- */

// Pas besoin de session_start ou d'inclusions, init.php s'en est chargé via router.php

// 🔒 Autoriser uniquement les requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: router.php?p=index.php');
    exit;
}

// 🛡️ Vérification CSRF (La fonction est déjà chargée par init.php)
if (function_exists('verify_csrf_token')) {
    verify_csrf_token();
}

// 🔢 Validation de l'ID
$echeanceId = isset($_POST['id']) && ctype_digit($_POST['id']) ? (int) $_POST['id'] : null;

if (!$echeanceId) {
    $_SESSION['flash_error'] = 'Échéance non spécifiée.';
    header('Location: router.php?p=index.php');
    exit;
}

/* ----------------------------- Logique PDO ----------------------------- */

$stmt = $pdo->prepare('
    SELECT e.id, e.achat_id, e.statut 
    FROM echeances e
    INNER JOIN achats a ON e.achat_id = a.id
    WHERE e.id = :id AND a.user_id = :user_id
    LIMIT 1
');

$stmt->execute([
    'id' => $echeanceId,
    'user_id' => $_SESSION['user_id']
]);
$echeance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$echeance) {
    $_SESSION['flash_error'] = 'Échéance introuvable.';
    header('Location: router.php?p=index.php');
    exit;
}

/* ----------------------------- Action ----------------------------- */

if ($echeance['statut'] !== 'payee') {
    // MODIFICATION : Mise à jour du statut, de la date de paiement, et des drapeaux de synchronisation (is_synced = 0 et updated_at) pour que le cron remonte l'information
    $stmt = $pdo->prepare("
        UPDATE echeances 
        SET statut = 'payee', 
            date_paiement = CURRENT_DATE, 
            is_synced = 0, 
            updated_at = NOW() 
        WHERE id = :id
    ");
    $stmt->execute(['id' => $echeanceId]);
    $_SESSION['flash_success'] = '✅ Échéance payée avec succès.';
}

/* ----------------------------- Redirection ----------------------------- */

// On repart via le routeur
header('Location: router.php?p=achats/echeancier.php&id=' . (int) $echeance['achat_id']);
exit;

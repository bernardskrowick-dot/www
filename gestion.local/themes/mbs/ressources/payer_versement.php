<?php
/* Version: v1.19.2 (Rev #26) - 2026-08-16 */

/**
 * payer_versement.php - Marque un versement comme perçu
 * Règles : CSRF, PDO, sécurité session, redirection via router
 */

// 🔒 Sécurité : Vérification de la session
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// 🔢 Autoriser uniquement les requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: router.php?p=historique_ressources.php');
    exit;
}

// 🛡️ Vérification CSRF
if (function_exists('verify_csrf_token')) {
    verify_csrf_token();
}

// 🔢 Validation de l'ID du versement
$versementId = isset($_POST['id']) && ctype_digit($_POST['id']) ? (int) $_POST['id'] : null;

if (!$versementId) {
    $_SESSION['flash_error'] = 'Versement non spécifié.';
    header('Location: router.php?p=historique_ressources.php');
    exit;
}

// -----------------------------
// Récupération du versement et sécurité utilisateur
// -----------------------------
$stmt = $pdo->prepare('
    SELECT v.id, v.ressource_id, v.statut 
    FROM versements v
    JOIN ressources r ON v.ressource_id = r.id
    WHERE v.id = :id AND r.user_id = :user_id
    LIMIT 1
');

$stmt->execute([
    'id' => $versementId,
    'user_id' => $_SESSION['user_id']
]);

$versement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$versement) {
    $_SESSION['flash_error'] = 'Versement introuvable.';
    header('Location: router.php?p=historique_ressources.php');
    exit;
}

// -----------------------------
// Action : marquer comme perçu
// -----------------------------
if ($versement['statut'] !== 'percu') {
    // MODIFICATION : Ajout de is_synced = 0 et updated_at = NOW() pour garantir la synchronisation bidirectionnelle
    $stmt = $pdo->prepare("
        UPDATE versements 
        SET statut = 'percu',
            montant_reel = montant_prevu,
            date_perception = :aujourdhui,
            is_synced = 0,
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        'id' => $versementId,
        'aujourdhui' => date('Y-m-d')
    ]);
    $_SESSION['flash_success'] = '✅ Versement enregistré comme perçu.';
}

// -----------------------------
// Redirection vers l'historique des ressources
// -----------------------------
header('Location: router.php?p=historique_ressources.php');
exit;

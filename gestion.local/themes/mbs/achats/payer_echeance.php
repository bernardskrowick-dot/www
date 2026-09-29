<?php
/* Version: v1.19.3 - 2026-09-12 */

/**
 * payer_echeance.php - Version intégrée au Routeur
 * RÈGLES : CSRF, PDO, Commentaires, Constantes.
 * AJOUT : Contrôle anti-paiement anticipé
 */

// 🔒 Sécurité : Le header est déjà inclus par router.php, on vérifie la session
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}
/* ----------------------------- Sécurité ----------------------------- */

// 🔒 Autoriser uniquement les requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: router.php?p=index.php');
    exit;
}

// 🛡️ Vérification CSRF
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
    SELECT e.id, e.achat_id, e.statut, e.date_echeance
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

/* ----------------------------- Contrôle anti-paiement anticipé ----------------------------- */

if ($echeance['statut'] !== 'payee') {

    // On empêche de marquer comme payée si la date d'échéance est dans le futur
    if (!empty($echeance['date_echeance']) && $echeance['date_echeance'] > date('Y-m-d')) {
        $_SESSION['flash_error'] = 'Impossible de marquer comme payée : cette échéance est prévue le '
            . date('d/m/Y', strtotime($echeance['date_echeance'])) . '.';

        header('Location: router.php?p=achats/echeancier.php&id=' . (int) $echeance['achat_id']);
        exit;
    }

    // Mise à jour normale
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

header('Location: router.php?p=achats/echeancier.php&id=' . (int) $echeance['achat_id']);
exit;

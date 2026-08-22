<?php
/* Version: v1.3.0 (Rev #3) - 2026-07-30 */

/**
 * =====================================================
 * changer_statut_utilisateur.php
 * Gestion Admin - Activation / Suspension utilisateur
 * =====================================================
 *
 * RÈGLES :
 * - Protection Admin
 * - CSRF
 * - PDO
 * - Redirection router
 * - Modification uniquement du statut
 */

// =====================================================
// 1. INITIALISATION
// =====================================================

if (!defined('DIR_ROOT')) {
    header('Location: ../router.php?p=admin/changer_statut_utilisateur');
    exit;
}

// =====================================================
// 2. SÉCURITÉ ADMIN
// =====================================================

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// =====================================================
// 3. RÉCUPÉRATION ID UTILISATEUR
// =====================================================

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$user_id) {
    $_SESSION['flash_error'] = 'Utilisateur invalide.';

    header(
        'Location: ' . BASE_URL
        . 'router.php?p=gestion_utilisateurs.php'
    );

    exit;
}

// =====================================================
// 4. PROTECTION ADMIN CONTRE LUI-MÊME
// =====================================================

if ((int) $user_id === (int) $_SESSION['user_id']) {
    $_SESSION['flash_error'] =
        'Impossible de modifier votre propre statut.';

    header(
        'Location: ' . BASE_URL
        . 'router.php?p=gestion_utilisateurs.php'
    );

    exit;
}

// =====================================================
// 5. RÉCUPÉRATION UTILISATEUR
// =====================================================

$stmt = $pdo->prepare(
    '
    SELECT username, statut
    FROM users
    WHERE id = ?
    LIMIT 1
    '
);

$stmt->execute([$user_id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['flash_error'] =
        'Utilisateur introuvable.';

    header(
        'Location: ' . BASE_URL
        . 'router.php?p=gestion_utilisateurs.php'
    );

    exit;
}

// =====================================================
// 6. INVERSION DU STATUT
// =====================================================

$nouveauStatut =
    ($user['statut'] === 'actif')
    ? 'suspendu'
    : 'actif';

// =====================================================
// 7. MISE À JOUR BDD
// =====================================================

$stmtUpdate = $pdo->prepare(
    '
    UPDATE users
    SET statut = ?
    WHERE id = ?
    '
);

$stmtUpdate->execute([
    $nouveauStatut,
    $user_id
]);

// =====================================================
// 8. MESSAGE ADMIN
// =====================================================

if ($nouveauStatut === 'suspendu') {
    $_SESSION['flash_success'] =
        "⏸️ L'utilisateur "
        . htmlspecialchars($user['username'])
        . ' a été suspendu.';
} else {
    $_SESSION['flash_success'] =
        "▶️ L'utilisateur "
        . htmlspecialchars($user['username'])
        . ' a été réactivé.';
}

// =====================================================
// 9. RETOUR GESTION UTILISATEURS
// =====================================================

header(
    'Location: ' . BASE_URL
    . 'router.php?p=gestion_utilisateurs.php'
);

exit;

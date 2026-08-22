<?php
/* Version: v1.21.0 (Rev #10) - 2026-08-08 */

/**
 * supprimer_utilisateur.php - Gestion Admin
 * RÈGLES : Protection Admin, CSRF, PDO, Redirection auto, Thème Dark.
 */

// 1️⃣ INITIALISATION
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/header.php';

/* ----------------------------- 🔒 SÉCURITÉ ADMIN ----------------------------- */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

/* ----------------------------- 🔢 VALIDATION ID ----------------------------- */

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$user_id) {
    header('Location: ' . BASE_URL . 'router.php?p=gestion_utilisateurs.php&error=ID_invalide');
    exit;
}

if ((int) $user_id === (int) $_SESSION['user_id']) {
    $_SESSION['flash_error'] = 'Impossible de supprimer votre propre compte.';
    header('Location: ' . BASE_URL . 'router.php?p=gestion_utilisateurs.php');
    exit;
}

/*
 * =====================================================
 * SECTION : PROTECTION SUPER ADMIN
 * RÔLE :
 * - Le compte Super Admin principal est intouchable
 * - Un Admin classique ne peut pas supprimer un Admin
 * - Seul le Super Admin peut supprimer un Admin secondaire
 * =====================================================
 */

/*
 * Récupération des droits du compte connecté
 */
$sessionSuperAdmin = $_SESSION['is_super_admin'] ?? 0;

/*
 * Récupération des informations du compte ciblé
 */
$stmtProtection = $pdo->prepare('
    SELECT 
        id,
        username,
        role,
        is_super_admin
    FROM users
    WHERE id = ?
');

$stmtProtection->execute([$user_id]);

$userProtection = $stmtProtection->fetch(PDO::FETCH_ASSOC);

if (!$userProtection) {
    $_SESSION['flash_error'] = 'Utilisateur introuvable.';
    header('Location: ' . BASE_URL . 'router.php?p=gestion_utilisateurs.php');
    exit;
}

/*
 * Protection du Super Admin principal
 */
if ($userProtection['is_super_admin'] == 1) {
    $_SESSION['flash_error'] =
        '🔒 Impossible de supprimer le Super Admin principal.';

    header('Location: ' . BASE_URL . 'router.php?p=gestion_utilisateurs.php');

    exit;
}

/*
 * Protection des administrateurs secondaires
 *
 * Un admin classique ne peut pas supprimer un admin
 */
if (
    $userProtection['role'] === 'admin' &&
    $sessionSuperAdmin != 1
) {
    $_SESSION['flash_error'] =
        '🔒 Seul le Super Admin peut supprimer un administrateur.';

    header('Location: ' . BASE_URL . 'router.php?p=gestion_utilisateurs.php');

    exit;
}

/* ----------------------------- 🔎 RÉCUPÉRATION INFOS ----------------------------- */

$stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
$stmt->execute([$user_id]);

$userASupprimer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userASupprimer) {
    header('Location: ' . BASE_URL . 'router.php?p=gestion_utilisateurs.php&error=introuvable');
    exit;
}

$error = '';
$success = '';

/* ----------------------------- 📊 RÉSUMÉ DES DONNÉES AVANT SUPPRESSION ----------------------------- */

$stmtResume = $pdo->prepare('
    SELECT
        (SELECT COUNT(*) FROM achats WHERE user_id = :user_id) AS achats,
        (SELECT COUNT(*) 
            FROM echeances e
            INNER JOIN achats a ON e.achat_id = a.id
            WHERE a.user_id = :user_id
        ) AS echeances,
        (SELECT COUNT(*) FROM ressources WHERE user_id = :user_id) AS ressources,
        (SELECT COUNT(*)
            FROM versements v
            INNER JOIN ressources r ON v.ressource_id = r.id
            WHERE r.user_id = :user_id
        ) AS versements,
        (SELECT COUNT(*) FROM categories WHERE user_id = :user_id) AS categories,
        (SELECT COUNT(*) FROM categories_ressources WHERE user_id = :user_id) AS categories_ressources
');

$stmtResume->execute([
    'user_id' => $user_id
]);

$resumeSuppression = $stmtResume->fetch(PDO::FETCH_ASSOC);

/* ----------------------------- 📥 TRAITEMENT POST ----------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    try {
        // Utilisation d'une transaction pour garantir l'intégrité
        $pdo->beginTransaction();

        $stmtDel = $pdo->prepare('DELETE FROM users WHERE id = ? LIMIT 1');
        $res = $stmtDel->execute([$user_id]);

        if ($res && $stmtDel->rowCount() > 0) {
            // 📝 Ajout dans la table de log pour la synchronisation
            $stmtLog = $pdo->prepare('INSERT INTO sync_deletions (table_name, record_id, user_id, nom) VALUES (?, ?, ?, ?)');
            $stmtLog->execute(['users', $user_id, $user_id, $userASupprimer['username']]);

            $pdo->commit();

            // 📢 RÈGLE : Stockage en session pour l'arrivée sur la page de gestion
            $_SESSION['flash_success'] = "✅ L'utilisateur " . htmlspecialchars($userASupprimer['username']) . ' a été supprimé.';

            // 😊 Ton message local pour le refresh (sans HTML parasite)
            $success = "L'utilisateur " . htmlspecialchars($userASupprimer['username']) . ' a été supprimé. Redirection...';

            header('refresh:2;url=' . BASE_URL . 'router.php?p=gestion_utilisateurs.php');
        } else {
            $pdo->rollBack();
            $error = 'Une erreur est survenue lors de la suppression.';
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Erreur technique : ' . $e->getMessage();
    }
}
?>

<div class="container py-5">
    <div>
        <?php
        // 1. On prépare les données de surcharge
        $titreSurcharge = "Supprimer l'utilisateur : " . htmlspecialchars($userASupprimer['username']);

        // 2. On appelle la TopBar (elle affichera automatiquement ces valeurs)
        if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
            include DIR_LOGIC . 'top-bar-title-page.php';
        }
        ?>
    </div>

    <div class="card bg-glass border-danger mx-auto shadow-lg">
        <div class="card-body p-4 p-md-5 text-center">

            <div class="mb-4">
                <h2 class="text-white h3 fw-bold">
                    <span class="emoji">⚠️</span> Confirmation
                </h2>
                <hr class="border-danger opacity-25">
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success bg-success bg-opacity-10 border-success admin-text-success border-0 mb-0">
                    <span class="emoji">✅</span> <?= htmlspecialchars($success) ?>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger bg-danger bg-opacity-10 border-danger text-danger border-0">
                    <span class="emoji">❌</span> <?= htmlspecialchars($error) ?>
                </div>
            <?php else: ?>
                <h2>Êtes-vous sûr de vouloir supprimer définitivement :</h2>

                <h1 class="text-danger fw-bold mb-4">
                    ❌ <?= htmlspecialchars($userASupprimer['username']) ?>
                </h1>


                <div class="alert alert-danger bg-danger bg-opacity-10 border-danger">

                    <h5 class="fw-bold mb-3">
                        ⚠️ Données qui seront supprimées :
                    </h5>


                    <div class="row text-start">

                        <div class="col-md-6">
                            🛒 Achats :
                            <strong>
                                <?= $resumeSuppression['achats'] ?>
                            </strong>
                        </div>


                        <div class="col-md-6">
                            📅 Échéances :
                            <strong>
                                <?= $resumeSuppression['echeances'] ?>
                            </strong>
                        </div>


                        <div class="col-md-6 mt-2">
                            💰 Ressources :
                            <strong>
                                <?= $resumeSuppression['ressources'] ?>
                            </strong>
                        </div>


                        <div class="col-md-6 mt-2">
                            💳 Versements :
                            <strong>
                                <?= $resumeSuppression['versements'] ?>
                            </strong>
                        </div>


                        <div class="col-md-6 mt-2">
                            📂 Catégories dépenses :
                            <strong>
                                <?= $resumeSuppression['categories'] ?>
                            </strong>
                        </div>


                        <div class="col-md-6 mt-2">
                            📂 Catégories revenus :
                            <strong>
                                <?= $resumeSuppression['categories_ressources'] ?>
                            </strong>
                        </div>

                    </div>

                </div>

                <form method="POST">
                    <?php csrf_input(); ?>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger py-2 fw-bold text-uppercase">
                            🗑️ Supprimer définitivement
                        </button>
                        <a href="<?= BASE_URL ?>router.php?p=gestion_utilisateurs.php" class="btn btn-outline-light py-2">
                            Annuler
                        </a>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>
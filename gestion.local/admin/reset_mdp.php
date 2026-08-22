<?php
/* Version: v1.21.0 (Rev #12) - 2026-08-08 */
require_once __DIR__ . '/../includes/header.php';  // démarre session, inclut connexion.php & fonctions.php

// 🔒 Accès réservé aux admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
    exit;
}

// 🔹 Récupérer l'ID utilisateur
$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header('Location: ' . BASE_URL . 'admin/gestion_utilisateurs.php?error=Utilisateur invalide');
    exit;
    exit;
}

$error = '';
$success = '';

// 🔹 Récupération infos utilisateur
$stmt = $pdo->prepare('SELECT username FROM users WHERE id = :id');
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: ' . BASE_URL . 'admin/gestion_utilisateurs.php?error=Utilisateur non trouvé');
    exit;
}

/*
 * =====================================================
 * SECTION : PROTECTION Effacer MOT DE PASSE
 * RÔLE :
 * - Le Super Admin reste protégé
 * - Un Admin classique ne peut pas modifier
 *   le mot de passe d'un autre Admin
 * - Le Super Admin conserve tous ses droits
 * =====================================================
 */

/*
 * Récupération niveau administrateur connecté
 */
$sessionSuperAdmin = $_SESSION['is_super_admin'] ?? 0;

/*
 * Récupération des droits du compte ciblé
 */
$stmtProtection = $pdo->prepare('
    SELECT 
        role,
        is_super_admin
    FROM users
    WHERE id = ?
');

$stmtProtection->execute([$user_id]);

$userProtection = $stmtProtection->fetch(PDO::FETCH_ASSOC);

/*
 * Protection du Super Admin principal
 */
if ($userProtection['is_super_admin'] == 1) {
    $_SESSION['flash_error'] =
        '🔒 Impossible de modifier le mot de passe du Super Admin principal.';

    header('Location: ' . BASE_URL . 'router.php?p=gestion_utilisateurs.php');
    exit;
}

/*
 * Protection des administrateurs secondaires
 */
if (
    $userProtection['role'] === 'admin' &&
    $sessionSuperAdmin != 1
) {
    $_SESSION['flash_error'] =
        '🔒 Seul le Super Admin peut modifier le mot de passe d’un administrateur.';

    header('Location: ' . BASE_URL . 'router.php?p=gestion_utilisateurs.php');
    exit;
}

// ==========================
// TRAITEMENT FORMULAIRE
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // 🔐 Validation mot de passe fort
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';

    if ($password !== $password_confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (!preg_match($pattern, $password)) {
        $error = 'Le mot de passe doit contenir au minimum 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.';
    } else {
        // 🔑 Hachage sécurisé
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => $password_hash, 'id' => $user_id]);

        $_SESSION['flash_success'] = "Mot de passe pour {$user['username']} mis à jour avec succès.";
        header('Location: ' . BASE_URL . 'admin/gestion_utilisateurs.php?error=Utilisateur non trouvé');
        exit;
    }
}
?>
<div class="container pb-5">

    <div>
        <?php
        // 1. On prépare les données de surcharge
        $titreSurcharge = 'Réinitialiser mot de passe : ' . htmlspecialchars($user['username']);
     

        // 2. On appelle la TopBar (elle affichera automatiquement ces valeurs)
        if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
            include DIR_LOGIC . 'top-bar-title-page.php';
        }
        ?>

    </div>

    <hr class="hr-glass">


    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php csrf_input(); ?>

        <div class="mb-3">
            <label class="form-label">Nouveau mot de passe *</label>
            <input type="password" name="password" class="form-control" required
                pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$"
                title="Minimum 8 caractères avec majuscule, minuscule, chiffre et caractère spécial">
            <div class="form-text">
                Minimum 8 caractères avec majuscule, minuscule, chiffre et caractère spécial.
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Confirmer le mot de passe *</label>
            <input type="password" name="password_confirm" class="form-control" required
                pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$"
                title="Doit correspondre au mot de passe saisi ci-dessus">
        </div>

        <div class="d-flex flex-column flex-md-row gap-3 mt-4 mb-3">
            <button type="submit" class="btn-create-dash shadow-sm w-100 w-md-auto">
                <span class="emoji me-2">💾</span> Mettre à jour
            </button>

            <button type="Effacer" class="btn btn-secondary shadow-sm w-100 w-md-auto">
                <span class="emoji me-2">🧹</span> Effacer
            </button>

            <a href="<?= BASE_URL ?>router.php?p=gestion_utilisateurs.php"
                class="btn btn-secondary shadow-sm w-100 w-md-auto ms-md-auto d-flex align-items-center justify-content-center">
                <span class="emoji me-2">👥</span> Retour gestion utilisateur
            </a>
        </div>
    </form>
</div>
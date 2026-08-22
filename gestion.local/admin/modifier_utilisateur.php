<?php
/* Version: v1.21.0 (Rev #11) - 2026-08-08 */

/**
 * modifier_utilisateur.php - Gestion Admin
 * RÈGLES : password_hash, PDO, CSRF, redirection, commentaires.
 */
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/header.php';

/* ----------------------------- Sécurité ----------------------------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// 🔎 Récupération et validation de l'ID
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$user_id) {
    header('Location: ' . BASE_URL . 'router.php?p=gestion_utilisateurs.php');
    exit;
}

// Récupération des données actuelles

/*
 * =====================================================
 * SECTION : RÉCUPÉRATION UTILISATEUR À MODIFIER
 * RÔLE :
 * - Chargement des informations du compte sélectionné
 * - Récupération du statut pour gestion Actif/Suspendu
 * =====================================================
 */

$stmt = $pdo->prepare('
    SELECT 
        id,
        username,
        email,
        role,
        theme,
        statut
    FROM users 
    WHERE id = ?
');

$stmt->execute([
    $user_id
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: ' . BASE_URL . 'router.php?p=gestion_utilisateurs.php');
    exit;
}

/* ----------------------------- Initialisation ----------------------------- */
$error = '';
$success = '';
$themes_disponibles = array_filter(glob(DIR_THEMES . '*'), 'is_dir');

/* ----------------------------- Traitement POST ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();  // 🛡️ Règle CSRF

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');

    /*
     * =====================================================
     * SECTION : RÉCUPÉRATION DES DONNÉES FORMULAIRE
     * RÔLE :
     * - Nettoyage des valeurs reçues
     * - Sécurisation des choix administrateur
     * =====================================================
     */

    $role = ($_POST['role'] ?? 'user') === 'admin'
        ? 'admin'
        : 'user';

    $theme = $_POST['theme'] ?? 'mbs';

    /*
     * Gestion du statut utilisateur
     * Valeurs autorisées :
     * - actif
     * - suspendu
     */

    $statut = ($_POST['statut'] ?? 'actif') === 'suspendu'
        ? 'suspendu'
        : 'actif';

    // 🔎 Vérification de l'unicité (exclure l'utilisateur actuel)
    $stmtCheck = $pdo->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?');
    $stmtCheck->execute([$username, $email, $user_id]);

    if ($stmtCheck->fetch()) {
        $error = 'Pseudo ou email déjà utilisé par un autre compte.';
    } elseif ($user_id == $_SESSION['user_id'] && $role !== 'admin') {
        $error = 'Impossible de retirer vos propres droits administrateur.';
    } else {
        // 📝 Mise à jour complète du profil utilisateur

        /*
         * =====================================================
         * SECTION : MISE À JOUR DU PROFIL UTILISATEUR
         * RÔLE :
         * - Modification des informations du compte
         * - Conservation du mot de passe existant
         * - Forçage de updated_at pour la synchronisation
         * =====================================================
         */

        $stmtUp = $pdo->prepare('
            UPDATE users 
            SET 
                username = ?,
                email = ?,
                role = ?,
                theme = ?,
                statut = ?,
                updated_at = NOW()
            WHERE id = ?
        ');

        $stmtUp->execute([
            $username,
            $email,
            $role,
            $theme,
            $statut,
            $user_id
        ]);
        
        $_SESSION['flash_success'] =
            '✅ Profil de ' . htmlspecialchars($username) . ' mis à jour avec succès.';

        $success = 'Utilisateur mis à jour ! Redirection en cours...';

        header(
            'refresh:2;url='
            . BASE_URL
            . 'router.php?p=gestion_utilisateurs.php'
        );
    }
}
?>

<div class="container pb-5">
    <div>
        <?php

        // 1. On prépare les données de surcharge
        $titreSurcharge = "Modifier l'utilisateur : " . htmlspecialchars($user['username']);
        // Utilisation de la constante globale définie dans init.php
        if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
            include DIR_LOGIC . 'top-bar-title-page.php';
        }
        ?>
    </div>

    <hr class="hr-glass">

    <?php if ($error): ?>
        <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success bg-success bg-opacity-10 admin-text-success border-0">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-glass p-4 shadow-lg">
        <?php csrf_input(); ?>

        <div class="mb-3">
            <label class="text-white-50 small">Pseudo</label>
            <input type="text" name="username" class="form-control bg-dark border-secondary text-white"
                value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="text-white-50 small">Email</label>
            <input type="email" name="email" class="form-control bg-dark border-secondary text-white"
                value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>

        <div class="row">
            <div class="col-6 mb-3">
                <label class="text-white-50 small">Rôle</label>
                <select name="role" class="form-select bg-dark border-secondary text-white">
                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Utilisateur</option>
                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="col-6 mb-3">
                <label class="text-white-50 small">Thème</label>
                <select name="theme" class="form-select bg-dark border-secondary text-white">
                    <?php foreach ($themes_disponibles as $tPath):
                        $tName = basename($tPath); ?>
                        <option value="<?= $tName ?>" <?= $user['theme'] === $tName ? 'selected' : '' ?>>
                            <?= ucfirst($tName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- =====================================================
     SECTION : STATUT UTILISATEUR
     RÔLE :
     - Permet à l'administrateur de suspendre/réactiver
       un compte depuis la fiche utilisateur
     ===================================================== -->

            <div class="mb-3">

                <label class="text-white-50 small">
                    Statut
                </label>

                <select name="statut" class="form-select bg-dark border-secondary text-white">

                    <option value="actif" <?= $user['statut'] === 'actif' ? 'selected' : '' ?>>
                        🟢 Actif
                    </option>

                    <option value="suspendu" <?= $user['statut'] === 'suspendu' ? 'selected' : '' ?>>
                        🔴 Suspendu
                    </option>

                </select>

            </div>
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
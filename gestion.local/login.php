<?php

/**
 * login.php - Page de connexion
 * THEME : MBS_DARK
 * STRUCTURE : Chargement via l'écosystème Init/Router
 * RÈGLES : CSRF, Hachage, Constantes, Commentaires
 */

// Utilisation de la constante de dossier pour initialiser l'app (Session, PDO, Constantes)
require_once __DIR__ . '/init.php';

// On définit le titre de la page pour le header
$titrePage = 'Connexion';

$error = '';

// ===================================
// TRAITEMENT DU FORMULAIRE (POST)
// ===================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF stricte avant traitement
    if (!check_csrf($_POST['csrf_token'] ?? '')) {
        die('Erreur de sécurité : Jeton CSRF invalide.');
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        // Vérification utilisateur + mot de passe + statut
        $user = verify_login($pdo, $username, $password);

        // ===================================
        // GESTION RESULTAT CONNEXION
        // ===================================

        if ($user && $user['success'] === true) {
            // 🔒 Protection contre fixation de session
            session_regenerate_id(true);

            $userData = $user['user'];

            // 🔒 VÉRIFICATION DU 2FA
            if (isset($userData['is_2fa_enabled']) && (int)$userData['is_2fa_enabled'] === 1) {

                // On s’assure qu’aucune ancienne session utilisateur n’existe
                unset(
                    $_SESSION['user_id'],
                    $_SESSION['username'],
                    $_SESSION['role'],
                    $_SESSION['user_theme'],
                    $_SESSION['is_super_admin']
                );

                // Stockage temporaire pour la page 2FA
                $_SESSION['pending_2fa_user_id']  = $userData['id'];
                $_SESSION['pending_2fa_username'] = $userData['username'];

                // Force l’écriture de la session AVANT la redirection
                session_write_close();

                header('Location: ' . BASE_URL . 'verify-2fa.php');
                exit;
            }

            // 🔒 Protection contre fixation de session (Connexion normale sans 2FA)
            session_regenerate_id(true);

            // 🔹 Création session utilisateur
            $_SESSION['user_id'] = $userData['id'];
            $_SESSION['username'] = $userData['username'];
            $_SESSION['role'] = $userData['role'];
            $_SESSION['user_theme'] = $userData['theme'] ?? 'mbs_dark';

            // Paramètres financiers en session
            $_SESSION['solde_initial'] = $userData['solde_initial'] ?? 0.0;
            $_SESSION['premiere_periode'] = $userData['premiere_periode'] ?? null;
            $_SESSION['jour_debut_periode'] = $userData['jour_debut_periode'] ?? 1;

            // Position du menu sticky en session (défaut : 'right')
            $_SESSION['user_sticky_pos'] = $userData['sticky_pos'] ?? 'right';

            // Niveau Super Admin (1 = principal, 0 = classique)
            $_SESSION['is_super_admin'] = $userData['is_super_admin'] ?? 0;

            /* ******************************************** */
            /* Mise à jour dernière activité utilisateur */
            /* ******************************************** */
            $stmtActivity = $pdo->prepare('
                UPDATE users
                SET last_activity = NOW()
                WHERE id = :user_id
            ');
            $stmtActivity->execute([
                'user_id' => $userData['id']
            ]);


            // 🔹 Redirection conditionnelle (Première connexion / Configuration incomplète)
            if (empty($userData['premiere_periode']) || is_null($userData['solde_initial'])) {
                header('Location: ' . BASE_URL . 'router.php?p=profil_utilisateur.php&first_login=1');
                exit;
            }

            header('Location: ' . BASE_URL . 'index.php');
            exit;
        } else {
            // 🔒 Gestion des refus de connexion
            error_log(
                date('[Y-m-d H:i:s] ') .
                    'LOGIN_FAILED ip=' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') .
                    PHP_EOL,
                3,
                '/var/log/gestion/auth_error.log'
            );
            
            if (($user['reason'] ?? '') === 'suspendu') {
                $error = 'Votre compte est suspendu. Contactez un administrateur.';
            } else {
                $error = "Nom d'utilisateur ou mot de passe incorrect";
            }
        }
    } else {
        $error = 'Veuillez remplir tous les champs';
    }
}

// Inclusion du header
require_once __DIR__ . '/includes/header.php';
?>

<div class="container d-flex align-items-center justify-content-center" style="min-height: 80vh;">
    <div class="card bg-glass p-5 shadow-lg border-0" style="width: 100%; max-width: 450px;">
<div class="avatar-icon rounded-circle">
        <img src="<?= BASE_URL ?>images/MBS Gestions Logo 88px.png"
             alt="MBS Gestions"
             class="header-logo">
    </div>
        <div class="text-center mb-4">
            <h1 class="main-title text-white">🔑 Connexion</h1>
            <p class="text-muted">Accédez à votre espace MBS</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4">
                <span class="emoji">⚠️</span> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php csrf_input(); ?>

            <div class="mb-3">
                <label class="form-label text-white-50">Nom d'utilisateur</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-white">👤</span>
                    <input type="text" name="username" class="form-control bg-dark text-white border-secondary"
                        placeholder="Votre pseudo" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label text-white-50">Mot de passe</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-white">🔒</span>
                    <input type="password" name="password" class="form-control bg-dark text-white border-secondary"
                        placeholder="••••••••" required>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                    Se connecter <span class="ms-2">🚀</span>
                </button>
            </div>
        </form>

        <div class="text-center mt-4">
            <small class="btn-primary-2 badge-neon text-muted text-white-50">Système de gestion sécurisé &copy;
                2026</small>
        </div>
    </div>
</div>

<?php
// Fermeture propre avec le footer
require_once __DIR__ . '/footer.php';
?>
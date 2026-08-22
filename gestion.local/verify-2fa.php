<?php

/**
 * verify-2fa.php - Vérification du code 2FA lors de la connexion
 * THEME : MBS_DARK
 * STRUCTURE : Chargement via l'écosystème Init
 * RÈGLES : CSRF, PDO Prepared Statements, Sessions sécurisées, Commentaires
 */

// Chargement des dépendances Composer (pour OTPHP, BaconQrCode, etc.)
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/init.php';


// Sécurité : Si l'utilisateur n'a pas de connexion en attente de 2FA, on le renvoie au login
if (!isset($_SESSION['pending_2fa_user_id'])) {
  header('Location: ' . BASE_URL . 'login.php');
  exit;
}

$titrePage = 'Vérification 2FA';
$error = '';

use OTPHP\TOTP;

// ===================================
// TRAITEMENT DU FORMULAIRE (POST)
// ===================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Vérification CSRF stricte
  if (!check_csrf($_POST['csrf_token'] ?? '')) {
    die('Erreur de sécurité : Jeton CSRF invalide.');
  }

  $userCode = trim($_POST['code_2fa'] ?? '');

  if (empty($userCode) || strlen($userCode) !== 6 || !ctype_digit($userCode)) {
    $error = "Veuillez entrer un code à 6 chiffres valide.";
  } else {
    try {
      $userId = (int) $_SESSION['pending_2fa_user_id'];

      // Récupération du secret et de l'IV (backticks obligatoires car les colonnes commencent par un chiffre)
      $stmt = $pdo->prepare("
                SELECT id, username, role, theme, solde_initial, premiere_periode, 
                       jour_debut_periode, sticky_pos, is_super_admin, 
                       `2fa_secret`, `2fa_iv` 
                FROM users 
                WHERE id = :id
            ");
      $stmt->execute(['id' => $userId]);
      $userData = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($userData && !empty($userData['2fa_secret']) && !empty($userData['2fa_iv'])) {

        // Déchiffrement du secret
        $decryptedSecret = openssl_decrypt(
          $userData['2fa_secret'],
          'aes-256-cbc',
          ENCRYPTION_KEY,
          0,
          base64_decode($userData['2fa_iv'])
        );

        if ($decryptedSecret === false) {
          $error = "Erreur de déchiffrement du secret 2FA.";
        } else {
          // Vérification du code avec l'objet TOTP
          $totp = TOTP::create($decryptedSecret);

          // Fenêtre de ±1 période (30 secondes) pour tolérer un léger décalage d'horloge
          if ($totp->verify($userCode, null, 1)) {

            // Code correct : on nettoie la session temporaire 2FA
            unset($_SESSION['pending_2fa_user_id']);
            unset($_SESSION['pending_2fa_username']);

            // Protection contre la fixation de session
            session_regenerate_id(true);

            // Création officielle de la session utilisateur
            $_SESSION['user_id']           = $userData['id'];
            $_SESSION['username']          = $userData['username'];
            $_SESSION['role']              = $userData['role'];
            $_SESSION['user_theme']        = $userData['theme'] ?? 'mbs_dark';
            $_SESSION['solde_initial']     = $userData['solde_initial'] ?? 0.0;
            $_SESSION['premiere_periode']  = $userData['premiere_periode'] ?? null;
            $_SESSION['jour_debut_periode'] = $userData['jour_debut_periode'] ?? 1;
            $_SESSION['user_sticky_pos']   = $userData['sticky_pos'] ?? 'right';
            $_SESSION['is_super_admin']    = $userData['is_super_admin'] ?? 0;

            // Mise à jour dernière activité
            $stmtActivity = $pdo->prepare('UPDATE users SET last_activity = NOW() WHERE id = :user_id');
            $stmtActivity->execute(['user_id' => $userData['id']]);

            // Redirection conditionnelle (première connexion)
            if (empty($userData['premiere_periode']) || is_null($userData['solde_initial'])) {
              header('Location: ' . BASE_URL . 'router.php?p=profil_utilisateur.php&first_login=1');
              exit;
            }

            header('Location: ' . BASE_URL . 'index.php');
            exit;
          } else {
            $error = "Code de validation incorrect ou expiré.";
          }
        }
      } else {
        $error = "Erreur de configuration 2FA pour cet utilisateur.";
      }
    } catch (Exception $e) {
      // Log technique (à regarder dans les logs PHP)
      error_log('2FA Error: ' . $e->getMessage());
      $error = "Une erreur technique est survenue.";
    }
  }
}

// Inclusion du header
require_once __DIR__ . '/includes/header.php';
?>

<div class="container d-flex align-items-center justify-content-center" style="min-height: 80vh;">
  <div class="card bg-glass p-5 shadow-lg border-0 text-center" style="width: 100%; max-width: 450px;">
    <div class="avatar-icon rounded-circle mx-auto mb-3">
      <img src="<?= BASE_URL ?>images/MBS Gestions Logo 88px.png" alt="MBS Gestions" class="header-logo">
    </div>

    <div class="mb-4">
      <h1 class="main-title text-white">🔐 Double Authentification</h1>
      <p class="text-muted">Entrez le code à 6 chiffres de votre application</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger border-0 shadow-sm mb-4">
        <span class="emoji">⚠️</span> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" class="d-flex flex-column align-items-center">
      <?php csrf_input(); ?>

      <div class="mb-4 w-100">
        <input type="text"
          name="code_2fa"
          maxlength="6"
          pattern="[0-9]{6}"
          required
          autofocus
          autocomplete="one-time-code"
          inputmode="numeric"
          placeholder="123456"
          class="form-control bg-dark text-white border-secondary text-center fs-3 tracking-widest py-2">
      </div>

      <div class="d-grid gap-2 w-100">
        <button type="submit" class="btn btn-primary btn-lg shadow-sm">
          Valider <span class="ms-2">🚀</span>
        </button>
      </div>
    </form>

    <div class="text-center mt-4">
      <a href="<?= BASE_URL ?>login.php" class="text-muted text-decoration-none small">← Retour à la connexion</a>
    </div>
  </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
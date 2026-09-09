<?php

/**
 * Page d'activation de la double authentification (2FA)
 * Fichier : includes/enable-2fa.php
 */

require_once __DIR__ . '/header.php';
// Chargement de l'autoloader de Composer (chemin absolu sécurisé)
require_once __DIR__ . '/../vendor/autoload.php';

// Sécurité : Vérification des accès (par exemple si l'admin doit être connecté)
$user_role = $_SESSION['role'] ?? '';
$user_id = (int) ($_SESSION['user_id'] ?? 0);

if ($user_id <= 0 || $user_role !== 'admin') { // Ajuste la condition selon ton rôle admin exact
  header('Location: login.php');
  exit;
}

use OTPHP\TOTP;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Writer;

$message = '';
$error = '';

// 1. Traitement de la validation du code à 6 chiffres
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {

  $userCode = trim($_POST['code_2fa'] ?? '');

  if (empty($userCode) || strlen($userCode) !== 6) {
    $error = "Veuillez entrer un code à 6 chiffres valide.";
  } else {
    try {
      // Récupération du secret chiffré et de l'IV en base de données avec PDO
      $stmt = $pdo->prepare("SELECT 2fa_secret, 2fa_iv FROM users WHERE id = :id");
      $stmt->execute(['id' => $user_id]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($user && !empty($user['2fa_secret']) && !empty($user['2fa_iv'])) {

        // Déchiffrement du secret (avec décodage base64 de l'IV)
        $decryptedSecret = openssl_decrypt(
          $user['2fa_secret'],
          'aes-256-cbc',
          ENCRYPTION_KEY,
          0,
          base64_decode($user['2fa_iv'])
        );

        // Vérification du code avec l'objet TOTP
        $totp = TOTP::create($decryptedSecret);

        if ($totp->verify($userCode)) {
          // Le code est correct : on valide définitivement l'activation du 2FA
          $update = $pdo->prepare("UPDATE users SET is_2fa_enabled = 1 WHERE id = :id");
          $update->execute(['id' => $user_id]);

          $message = "La double authentification a été activée avec succès !";

          // On bascule la variable d'état pour masquer le QR code et afficher le succès
          $isAlreadyEnabled = true;
        } else {
          $error = "Code incorrect, veuillez réessayer.";
        }
      } else {
        $error = "Aucun secret 2FA en attente. Veuillez rafraîchir la page.";
      }
    } catch (Exception $e) {
      $error = "Une erreur technique est survenue.";
    }
  }
}

// 2. Génération ou récupération du secret pour l'affichage
try {
  $stmt = $pdo->prepare("SELECT 2fa_secret, is_2fa_enabled FROM users WHERE id = :id");
  $stmt->execute(['id' => $user_id]);
  $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

  $isAlreadyEnabled = ($currentUser && $currentUser['is_2fa_enabled'] == 1);

  if (!$isAlreadyEnabled) {
    // Génération d'une nouvelle clé secrète TOTP
    $totp = TOTP::generate();
    $totp->setLabel($_SESSION['user_email'] ?? 'Admin'); // Si tu as l'email en session, sinon ajuste
    $totp->setIssuer('MBS Gestion (mysterb.ddns.net)');

    $secretB32 = $totp->getSecret();

    // Chiffrement du secret avec génération d'un IV sécurisé et encodage base64 pour la base
    $ivBytes = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $ivBase64 = base64_encode($ivBytes);

    $encryptedSecret = openssl_encrypt($secretB32, 'aes-256-cbc', ENCRYPTION_KEY, 0, $ivBytes);

    // Sauvegarde du secret et de l'IV encodé en base de données
    $updateSecret = $pdo->prepare("UPDATE users SET 2fa_secret = :secret, 2fa_iv = :iv WHERE id = :id");
    $updateSecret->execute([
      'secret' => $encryptedSecret,
      'iv'     => $ivBase64,
      'id'     => $user_id
    ]);

    // Génération du QR Code au format SVG local
    $uri = $totp->getProvisioningUri();
    $renderer = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd());
    $writer = new Writer($renderer);
    $svgQrCode = $writer->writeString($uri);
  }
} catch (Exception $e) {
  // On affiche l'erreur technique exacte pour comprendre
  $error = "Erreur lors de la génération du QR Code : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
?>
<!-- Affichage HTML structuré (les classes s'appuient sur ton stylesheet.css) -->
<div class="container mb-4 nav-dashboard-container">

  <?php
  /** ==========================================================================
   *  Section Menu Administrateur et Titre de la page
   *  ========================================================================== */
  ?>
  <div class="glass-card-nav mt-4 mb-4">

    <div class="d-flex flex-wrap align-items-center mt-3">
    </div>

    <div>
      <?php

      if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
        include DIR_LOGIC . 'top-bar-title-page.php';
      }
      ?>
    </div>

    /** ==========================================================================
    * Fin de Section Admin titre
    * ========================================================================== */

    /** ==========================================================================
    * Début d'affichage de la page
    * ========================================================================== */
    ?>
    <hr class="hr-glass mt-2 mb-4" style="opacity:0.6;">
    <!--******************************************* -->
    <!--** Section Menu Revenus Appel page      ** -->
    <!--******************************************* -->
    <div class="glass-card-nav mb-4 align-items-center justify-content-center">
      <div class="row mb-4 text-center align-items-stretch">
        <?php if (!empty($error)): ?>
          <div class="alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
          <div class="alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php elseif (isset($isAlreadyEnabled) && $isAlreadyEnabled): ?>
          <p class="text-success">La double authentification est déjà active sur votre compte.</p>
          <div class="align-content-center align-items-center">
          <a href="<?= BASE_URL ?>index.php" class="btn btn-action-dash px-3" title="Retour au tableau de bord"
            aria-label="Retour au tableau de bord">
            <span class="emoji fs-4">🏠</span>
            Retour Tableau de bord
          </a>
        </div>
        <?php else: ?>
          <h2>1. Scannez ce QR code avec votre application d'authentification (Aegis, Google Authenticator) :</h2>
          <br><br>
          <div class="qr-code-wrapper">
            <?php echo $svgQrCode ?? ''; ?>
          </div>
          <br><br>
          <h2>2. Si vous ne pouvez pas scanner le QR code, entrez cette clé manuellement :</h2>
          <code class="secret-code"><?php echo htmlspecialchars($secretB32 ?? '', ENT_QUOTES, 'UTF-8'); ?></code>
      </div>
      <hr class="hr-glass mt-4 mb-4" style="opacity:0.6;">

      <!-- Conteneur centré pour l'étape 3 -->
      <div class="text-center">
        <h2 class="text-white">3. Entrez le code à 6 chiffres généré par votre application pour confirmer :</h2>
        <br>
        <form method="POST" action="" class="form-2fa d-inline-flex flex-column align-items-center justify-content-center">
          <input type="text" name="code_2fa" maxlength="6" pattern="[0-9]{6}" required autocomplete="one-time-code" placeholder="123456" class="text-center mb-3">
          <button type="submit" name="verify_code" class="btn-action-dash">Activer</button>
        </form>
      </div>
    <?php endif; ?>
    </div>
  </div>
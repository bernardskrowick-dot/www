<?php
/* Version: v1.19.1 (Rev #25) - 2026-08-06 */
/**
 * =====================================================
 * TOP-BAR TITLE PAGE (includes/top-bar-title-page.php)
 * =====================================================
 * RÔLE : Affichage dynamique du bandeau de titre et date.
 * NOTE : init.php est supposé chargé en amont.
 * =====================================================
 */

// 1. Fuseau horaire et formatage de date
date_default_timezone_set('Europe/Paris');
$date = new DateTime('now', new DateTimeZone('Europe/Paris'));

$formatter = new IntlDateFormatter(
    'fr_FR',
    IntlDateFormatter::FULL,
    IntlDateFormatter::NONE
);

// Formate la date et met la première lettre en majuscule (ex: Mardi 14 mai 2026)
$dateFormatee = ucfirst($formatter->format($date));

// 2. Récupération de l'utilisateur (priorité à la SESSION, sinon BDD si absente)
$usernameTopBar = $_SESSION['username'] ?? null;

if (!$usernameTopBar && !empty($_SESSION['user_id']) && isset($pdo)) {
    $stmtTopBar = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $stmtTopBar->execute([$_SESSION['user_id']]);
    $userTopBar = $stmtTopBar->fetch(PDO::FETCH_ASSOC);

    if ($userTopBar) {
        $usernameTopBar = $userTopBar['username'];
        $_SESSION['username'] = $usernameTopBar; // Mise en cache session
    }
}

$usernameTopBar = $usernameTopBar ?? 'Utilisateur';

// 3. Logique de Titre (Dictionnaire vs Surcharge)
$p = $_GET['p'] ?? 'dashboard';
$slug = str_replace('.php', '', basename($p));
$details = getPageDetails($slug);

// Priorité à la surcharge si définie dans la page parente
$affichageTitre = $titreSurcharge ?? $details['text'] ?? 'Page';
$affichageEmoji = $emojiSurcharge ?? $details['emoji'] ?? '📍';

// Nettoyage des variables de surcharge
unset($titreSurcharge, $emojiSurcharge);
?>

<div class="glass-banner d-flex align-items-center justify-content-between p-3 rounded-4 shadow-sm">
    <div class="d-flex align-items-center">
        <div class="avatar-icon bg-primary rounded-circle me-5 d-flex align-items-center justify-content-center">
            <img src="<?= BASE_URL ?>images/MBS Gestions Logo 88px.png"
             alt="MBS Gestions" 
             class="header-logo">
         
        </div>
        <div>
            <span class="text-white-50 small text-uppercase fw-bold d-block mb-0">Bienvenue,</span>
            <span class="username-highlight fw-bold text-primary">
                <?= htmlspecialchars($usernameTopBar, ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>
    </div>

    <div class="sep-grande"></div>
    <div class="sep-petite"></div>

    <div class="text-center px-3 flex-grow-1">
        <h1 class="glass-header mb-0 fs-4 text-white">
            <span class="emoji me-2"><?= htmlspecialchars($affichageEmoji, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="gradient-text">
                <?= htmlspecialchars($affichageTitre, ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </h1>
    </div>

    <div class="sep-petite"></div>
    <div class="sep-grande"></div>

    <div class="time-status text-end">
        <div class="form-label date-now text-muted small mb-0">
            <span class="emoji fs-4">📅 </span>
            <?= htmlspecialchars($dateFormatee, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <div id="top-bar-clock" class="time-now fw-bold text-white fs-5"><?= date('H:i:s'); ?></div>
    </div>
</div>
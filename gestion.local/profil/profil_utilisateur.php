<?php
/* Version: v1.21.0 (Rev #12) - 2026-08-08 */

/**
 * =====================================================
 * PROFIL/ profil_utilisateur EX.PHP – GESTION DU COMPTE (THÈME & CONFIGURATION FINANCIÈRE)
 * =====================================================
 */

/* --- 1. SÉCURITÉ D'ACCÈS --- */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

/* --- 2. RÉCUPÉRATION DES DONNÉES --- */
// Liste des dossiers de thèmes disponibles
$themes_disponibles = array_filter(glob(DIR_THEMES . '*'), 'is_dir');

// Récupération des informations de l'utilisateur avec requêtes préparées (ajout de sticky_pos)
$stmt = $pdo->prepare('SELECT username, email, theme, solde_initial, premiere_periode, jour_debut_periode, sticky_pos FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

/* --- 3. TRAITEMENT DU FORMULAIRE (POST) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF systématique pour la sécurité
    if (function_exists('verify_csrf_token')) {
        verify_csrf_token();
    }

    // Nettoyage et typage des variables saisies
    $nouveau_theme = $_POST['theme'] ?? 'mbs';
    $solde_initial = isset($_POST['solde_initial']) ? (float) $_POST['solde_initial'] : 0.0;
    $premiere_periode = !empty($_POST['premiere_periode']) ? trim($_POST['premiere_periode']) : null;
    $jour_debut_periode = isset($_POST['jour_debut_periode']) ? (int) $_POST['jour_debut_periode'] : 1;

    // Nettoyage et sécurisation du choix du menu sticky (left ou right)
    $sticky_pos_input = $_POST['sticky_pos'] ?? 'right';
    $sticky_pos = in_array($sticky_pos_input, ['left', 'right'], true) ? $sticky_pos_input : 'right';

    // Validation du jour de début de période (entre 1 et 31)
    if ($jour_debut_periode < 1 || $jour_debut_periode > 31) {
        $jour_debut_periode = 1;
    }

    $new_password = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    try {
        $pdo->beginTransaction();

        // A. Mise à jour du thème, des paramètres financiers et du menu sticky de l'utilisateur
        // Ajout de : updated_at = NOW() pour garantir la détection par le script de synchronisation
        $stmtUpdate = $pdo->prepare('UPDATE users SET theme = :theme, solde_initial = :solde, premiere_periode = :periode, jour_debut_periode = :jour, sticky_pos = :sticky_pos, updated_at = NOW() WHERE id = :id');
        $stmtUpdate->execute([
            'theme' => $nouveau_theme,
            'solde' => $solde_initial,
            'periode' => $premiere_periode,
            'jour' => $jour_debut_periode,
            'sticky_pos' => $sticky_pos,
            'id' => $user_id
        ]);

        // Synchronisation des variables en session
        $_SESSION['user_theme'] = $nouveau_theme;
        $_SESSION['solde_initial'] = $solde_initial;
        $_SESSION['premiere_periode'] = $premiere_periode;
        $_SESSION['jour_debut_periode'] = $jour_debut_periode;
        $_SESSION['user_sticky_pos'] = $sticky_pos;

        // B. Mise à jour du mot de passe si un nouveau a été saisi
        if (!empty($new_password)) {
            if ($new_password === $confirm_pass) {
                // Hachage sécurisé du mot de passe
                $hashedPassword = password_hash($new_password, PASSWORD_BCRYPT);
                // Ajout de : updated_at = NOW() ici aussi
                $stmtPass = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
                $stmtPass->execute([$hashedPassword, $user_id]);
            } else {
                throw new Exception('Les mots de passe ne correspondent pas.');
            }
        }

        $pdo->commit();

        // Redirection vers le profil avec message de succès
        header('Location: router.php?p=profil/index.php&success=1');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Traitement du retour après enregistrement réussi
if (isset($_GET['success'])) {
    $success = '✅ Préférences et paramètres mis à jour avec succès !';
}
?>
<div class="container">
    <div>
        <?php
        $titreSurcharge = 'Mon Profil & Préférences (' . htmlspecialchars($user['username'] ?? '') . ')';
        if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
            include DIR_LOGIC . 'top-bar-title-page.php';
        }
        ?>
    </div>
    <hr class="hr-glass">

    <!-- Messages d'erreur et de succès -->
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="card p-4 shadow-sm border-0">
        <div class="mb-3">
            <p><strong>Utilisateur :</strong> <?= htmlspecialchars($user['username'] ?? '') ?></p>
            <p><strong>Email :</strong> <?= htmlspecialchars($user['email'] ?? '') ?></p>
        </div>

        <hr>

        <form method="POST">
            <!-- Protection contre les failles CSRF -->
            <?php csrf_input(); ?>

            <!-- SECTION : CHOIX DU THÈME & NAVIGATION -->
            <div class="row">
                <!-- Thème visuel -->
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Choisir mon thème visuel :</label>
                    <select name="theme" class="form-select">
                        <?php
                        foreach ($themes_disponibles as $tPath):
                            $tName = basename($tPath);
                            $selected = (($user['theme'] ?? 'mbs') === $tName) ? 'selected' : '';
                        ?>
                            <option value="<?= htmlspecialchars($tName) ?>" <?= $selected ?>>
                                <?= ucfirst(htmlspecialchars($tName)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Basculez entre le mode MBS (Clair) ou MBS_DARK (Néon).</div>
                </div>

                <!-- Position du menu sticky -->
                <div class="col-md-6 mb-3">
                    <div class="d-flex align-items-center mb-1">
                        <label class="form-label fw-bold mb-0 me-2">Position du menu flottant :</label>
                        <span class="info-tooltip text-info">
                            ⓘ
                            <span class="info-tooltip-text">
                                <strong>Menu de raccourcis rapide.</strong><br><br>
                                Choisissez si la barre verticale d'accès aux tableaux de bord doit s'afficher à gauche
                                ou à droite de votre écran.
                            </span>
                        </span>
                    </div>
                    <?php $userSticky = $user['sticky_pos'] ?? 'right'; ?>
                    <select name="sticky_pos" class="form-select">
                        <option value="right" <?= ($userSticky === 'right') ? 'selected' : '' ?>>À droite</option>
                        <option value="left" <?= ($userSticky === 'left') ? 'selected' : '' ?>>À gauche</option>
                    </select>
                    <div class="form-text">Emplacement des raccourcis tableaux de bord.</div>
                </div>
            </div>

            <hr>

            <!-- SECTION : CONFIGURATION FINANCIÈRE -->
            <div class="mb-3">
                <h5 class="fw-bold mb-3">📊 Paramètres des relevés bancaires & soldes</h5>

                <div class="row">

                    <!-- Solde initial -->
                    <div class="col-md-4 mb-3">
                        <div class="d-flex align-items-center mb-1">
                            <label class="form-label fw-bold mb-0 me-2">
                                Solde initial (€)
                            </label>

                            <span class="info-tooltip text-info">
                                ⓘ
                                <span class="info-tooltip-text">
                                    <strong>Solde initial du compte bancaire.</strong><br><br>

                                    Il correspond au montant réellement disponible sur votre compte au début de votre
                                    suivi financier.

                                    Ce solde sert de point de départ à tous les calculs de votre tableau de bord, des
                                    relevés bancaires, des soldes mensuels ainsi que des indicateurs (KPI).

                                    Il n'est pris en compte qu'une seule fois, lors de la première période configurée.
                                </span>
                            </span>
                        </div>

                        <input type="number" step="0.01" name="solde_initial" class="form-control"
                            value="<?= htmlspecialchars($user['solde_initial'] ?? '0.00') ?>" required>

                        <div class="form-text">
                            Solde de départ de votre compte.
                        </div>
                    </div>


                    <!-- Première période -->
                    <div class="col-md-4 mb-3">
                        <div class="d-flex align-items-center mb-1">
                            <label class="form-label fw-bold mb-0 me-2">
                                Première période
                            </label>

                            <span class="info-tooltip text-info">
                                ⓘ
                                <span class="info-tooltip-text">
                                    <strong>Début de votre historique bancaire.</strong><br><br>

                                    Indiquez le premier mois à partir duquel vous souhaitez commencer le suivi de vos
                                    achats et échéances.

                                    Toutes les périodes précédentes seront ignorées dans les calculs.

                                    Exemple : <strong>2026-06</strong> pour commencer en juin 2026.
                                </span>
                            </span>
                        </div>

                        <input type="month" name="premiere_periode" class="form-control"
                            value="<?= htmlspecialchars($user['premiere_periode'] ?? '') ?>" required>

                        <div class="form-text">
                            Ex : 2026-06 pour juin 2026.
                        </div>
                    </div>


                    <!-- Jour début de période -->
                    <div class="col-md-4 mb-3">
                        <div class="d-flex align-items-center mb-1">
                            <label class="form-label fw-bold mb-0 me-2">
                                Jour début de période
                            </label>

                            <span class="info-tooltip text-info">
                                ⓘ
                                <span class="info-tooltip-text">
                                    <strong>Détermine vos périodes bancaires.</strong><br><br>

                                    Cette valeur correspond au jour de clôture de votre relevé bancaire.

                                    Par exemple :

                                    • Valeur <strong>1</strong> : période du 1er au dernier jour du mois.

                                    • Valeur <strong>10</strong> : période du 10 d'un mois au 9 du mois suivant.

                                    Tous les tableaux, graphiques, soldes et KPI utiliseront automatiquement cette
                                    règle.
                                </span>
                            </span>
                        </div>

                        <input type="number" min="1" max="31" name="jour_debut_periode" class="form-control"
                            value="<?= htmlspecialchars($user['jour_debut_periode'] ?? '1') ?>" required>

                        <div class="form-text">
                            Jour d'arrêté mensuel de vos relevés.
                        </div>
                    </div>

                </div>
            </div>

            <hr>

            <!-- SECTION : CHANGEMENT DE MOT DE PASSE -->
            <div class="row">
                <label class="form-label fw-bold">🔒 Sécurité (Laissez vide pour ne pas changer)</label>
                <div class="col-md-6 mb-3">
                    <input type="password" name="new_password" class="form-control" placeholder="Nouveau mot de passe">
                </div>
                <div class="col-md-6 mb-3">
                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirmer">
                </div>
            </div>

            <div class="row">
                <label class="form-label fw-bold">🔒 Configurer la double authentification (2FA))</label>
                <div class="col-md-6 mb-3">
                    <a href="<?= BASE_URL ?>router.php?p=enable-2fa.php" class="btn btn-secondary">
                        Configurer la double authentification (2FA)
                    </a>
                </div>
                </div>

                <!-- BOUTONS D'ACTION -->
                <div
                    class="d-flex flex-column flex-md-row justify-content-md-between align-items-stretch align-items-md-center mt-4 gap-3">
                    <button type="submit" class="btn-create-dash shadow-sm w-100 w-md-auto">
                        <span class="emoji">💾</span> Enregistrer les modifications
                    </button>

                    <a href="<?= BASE_URL ?>router.php?p=dashboard.php"
                        class="btn btn-secondary shadow-sm w-100 w-md-auto d-flex align-items-center justify-content-center">
                        <span class="emoji">🏠</span> Retour au Tableau de bord
                    </a>
                </div>

        </form>
    </div>
</div>
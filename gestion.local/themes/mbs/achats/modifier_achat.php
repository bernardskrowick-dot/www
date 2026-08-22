<?php
/* Version: v1.21.0 (Rev #7) - 2026-08-08 */

/**
 * modifier_achat.php - Modification d'un achat existant, de ses échéances et recalcul optionnel
 * THEME : MBS_DARK
 * RÈGLES : CSRF, Constantes de dossier, Router-Ready, Commentaires, PDO Sécurisé
 */

/* ----------------------------- Sécurité & Accès ----------------------------- */
if (!isset($_SESSION['user_id'])) {
    // Redirection vers le login si l'utilisateur n'est pas connecté
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Vérification de l'ID passé en GET
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    $_SESSION['flash_error'] = 'Achat non spécifié.';
    header('Location: ' . BASE_URL . 'router.php?p=dashboard.php');
    exit;
}

$idAchat = (int) $_GET['id'];

/* ----------------------------- Récupération des données ----------------------------- */

// 1. Récupération de l'achat (Sécurité : filtrage par user_id)
$stmt = $pdo->prepare('SELECT * FROM achats WHERE id = ? AND user_id = ?');
$stmt->execute([$idAchat, $_SESSION['user_id']]);
$achat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$achat) {
    $_SESSION['flash_error'] = 'Achat introuvable.';
    header('Location: ' . BASE_URL . 'router.php?p=dashboard.php');
    exit;
}

// 2. Récupération des catégories pour le select (avec hiérarchie si applicable)
$stmtCat = $pdo->prepare('
    SELECT id, nom, parent_id
    FROM categories
    WHERE user_id = ? OR id = 1
    ORDER BY 
        COALESCE(parent_id, id),
        parent_id IS NOT NULL,
        nom ASC
');
$stmtCat->execute([$_SESSION['user_id']]);
$categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

// 3. Récupération des échéances associées à cet achat (triées par ID ou date)
$stmtEch = $pdo->prepare('SELECT id, date_echeance, montant FROM echeances WHERE achat_id = ? ORDER BY id ASC');
$stmtEch->execute([$idAchat]);
$echeancesActuelles = $stmtEch->fetchAll(PDO::FETCH_ASSOC);

/* ----------------------------- Traitement POST ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Règle : Vérification du jeton CSRF
    verify_csrf_token();

    $titre = trim($_POST['titre'] ?? '');
    $nomMarchand = trim($_POST['nom_marchand'] ?? '');
    $montantTotal = filter_var($_POST['montant_total'] ?? '', FILTER_VALIDATE_FLOAT);
    $dateDepart = $_POST['date_depart'] ?? '';
    $categorieId = (int) ($_POST['categorie_id'] ?? 1);
    $recurrence = $_POST['recurrence'] ?? 'unique';
    $datesEcheancesPost = $_POST['dates_echeances'] ?? [];

    $errors = [];

    if ($titre === '')
        $errors[] = 'Le titre est obligatoire.';
    if ($nomMarchand === '')
        $errors[] = 'Le nom du marchand est obligatoire.';
    if ($montantTotal === false || $montantTotal <= 0)
        $errors[] = 'Le montant doit être un nombre supérieur à 0.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDepart))
        $errors[] = 'Date de départ invalide.';

    // Validation des dates d'échéances postées si présentes
    foreach ($datesEcheancesPost as $dateEch) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEch)) {
            $errors[] = 'Une des dates d échéance est invalide.';
            break;
        }
    }

    if ($errors) {
        $_SESSION['flash_error'] = implode('<br>', $errors);
        header('Location: ' . BASE_URL . 'router.php?p=modifier_achat.php&id=' . $idAchat);
        exit;
    }

    // Sauvegarde de l'ancien montant pour vérification du changement
    $ancienMontant = (float) $achat['montant_total'];

    // Mise à jour sécurisée de l'achat (PDO préparé)
    $stmtUpdate = $pdo->prepare('
        UPDATE achats 
        SET titre = :titre, 
            nom_marchand = :nom_marchand, 
            montant_total = :montant_total, 
            date_depart = :date_depart,
            categorie_id = :categorie_id,
            recurrence = :recurrence
        WHERE id = :id 
        AND user_id = :user_id
    ');

    $stmtUpdate->execute([
        'titre' => $titre,
        'nom_marchand' => $nomMarchand,
        'montant_total' => $montantTotal,
        'date_depart' => $dateDepart,
        'categorie_id' => $categorieId,
        'recurrence' => $recurrence,
        'id' => $idAchat,
        'user_id' => $_SESSION['user_id']
    ]);

    /* --- LOGIQUE DE MISE À JOUR DES ÉCHÉANCES --- */
    if (!empty($echeancesActuelles)) {
        // Calcul du nouveau montant par échéance si le montant total a changé
        $changerMontant = abs($montantTotal - $ancienMontant) > 0.001;
        $nbEcheances = count($echeancesActuelles);
        $nouveauMontantEcheance = $changerMontant ? ($montantTotal / $nbEcheances) : null;

        // Mise à jour individuelle de chaque échéance (date + montant éventuel)
        $stmtUpdateEch = $pdo->prepare('
            UPDATE echeances 
            SET date_echeance = :date_echeance' . ($changerMontant ? ', montant = :montant' : '') . '
            WHERE id = :id AND achat_id = :achat_id
        ');

        foreach ($echeancesActuelles as $index => $ech) {
            if (isset($datesEcheancesPost[$index])) {
                $params = [
                    'date_echeance' => $datesEcheancesPost[$index],
                    'id' => $ech['id'],
                    'achat_id' => $idAchat
                ];
                if ($changerMontant) {
                    $params['montant'] = round($nouveauMontantEcheance, 2);
                }
                $stmtUpdateEch->execute($params);
            }
        }
    }

    $_SESSION['flash_success'] = '✅ Achat et échéancier mis à jour avec succès.';

    // Redirection vers l'échéancier ou le tableau de bord
    header('Location: ' . BASE_URL . 'router.php?p=echeancier.php&id=' . (int) $idAchat);
    exit;
}
?>

<div class="container mb-4 nav-dashboard-container">
    <div class="glass-card-nav mt-4 mb-0">

        <div class="d-flex flex-wrap align-items-center mt-3">
        </div>

        <div class="p-3 pt-0">
            <?php
            // Inclusion sécurisée du titre de la page si disponible
            if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
                include DIR_LOGIC . 'top-bar-title-page.php';
            }
            ?>
        </div>

        <hr class="hr-glass">

        <form method="POST" action="<?= BASE_URL ?>router.php?p=modifier_achat.php&id=<?= $idAchat ?>"
            class="card p-4 bg-glass shadow-lg border-0">

            <?php csrf_input(); ?>

            <!-- =============================== -->
            <!-- Informations principales achat -->
            <!-- =============================== -->
            <div class="row">
                <!-- Titre -->
                <div class="col-md-6 mb-3">
                    <label class="form-label text-white-50 small fw-bold uppercase-tracking">Titre de l'achat</label>
                    <input type="text" class="form-control bg-dark text-white border-secondary" name="titre"
                        value="<?= htmlspecialchars($achat['titre']) ?>" required>
                </div>

                <!-- Catégorie -->
                <div class="col-md-6 mb-3">
                    <label class="form-label text-white-50 small fw-bold uppercase-tracking">Catégorie</label>
                    <select class="form-select bg-dark text-white border-secondary" name="categorie_id">
                        <?php foreach ($categories as $cat): ?>
                            <?php
                            $isSousCategorie = !empty($cat['parent_id']);
                            $nomAffiche = $isSousCategorie ? '└── 🏷️ ' . $cat['nom'] : '📁 ' . $cat['nom'];
                            ?>
                            <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $achat['categorie_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nomAffiche) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <!-- Marchand -->
                <div class="col-md-4 mb-3">
                    <label class="form-label text-white-50 small fw-bold uppercase-tracking">Nom du marchand</label>
                    <input type="text" class="form-control bg-dark text-white border-secondary" name="nom_marchand"
                        value="<?= htmlspecialchars($achat['nom_marchand']) ?>" required>
                </div>

                <!-- Montant -->
                <div class="col-md-4 mb-3">
                    <label class="form-label text-white-50 small fw-bold uppercase-tracking">Montant total (€)</label>
                    <input type="number" step="0.01" min="0" class="form-control bg-dark text-white border-secondary"
                        name="montant_total" value="<?= htmlspecialchars($achat['montant_total']) ?>" required>
                </div>

                <!-- Date de départ -->
                <div class="col-md-4 mb-3">
                    <label class="form-label text-white-50 small fw-bold uppercase-tracking">Date de départ</label>
                    <input type="date" class="form-control bg-dark text-white border-secondary" name="date_depart"
                        value="<?= htmlspecialchars($achat['date_depart']) ?>" required>
                </div>
            </div>

            <!-- Récurrence -->
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label text-info small fw-bold uppercase-tracking">Récurrence</label>
                    <select class="form-select bg-dark text-white border-accent-blue" name="recurrence">
                        <option value="unique" <?= (($achat['recurrence'] ?? 'unique') === 'unique') ? 'selected' : '' ?>>Unique
                        </option>
                        <option value="mensuelle" <?= (($achat['recurrence'] ?? '') === 'mensuelle') ? 'selected' : '' ?>>Mensuelle
                        </option>
                        <option value="annuelle" <?= (($achat['recurrence'] ?? '') === 'annuelle') ? 'selected' : '' ?>>Annuelle
                        </option>
                    </select>
                </div>
            </div>

            <!-- =============================== -->
            <!-- Modification des échéances existantes -->
            <!-- =============================== -->
            <?php if (!empty($echeancesActuelles)): ?>
                <div class="card bg-dark-soft p-3 mt-3 border-secondary">
                    <h5 class="text-info mb-3 small fw-bold text-uppercase">
                        🗓️ Modifier les dates des échéances
                    </h5>
                    <div class="row g-2">
                        <?php foreach ($echeancesActuelles as $index => $ech): ?>
                            <div class="col-md-3 mb-2">
                                <label class="small text-muted">
                                    Éch. <?= $index + 1 ?> (<?= number_format($ech['montant'], 2, ',', ' ') ?> €)
                                </label>
                                <input type="date" class="form-control form-control-sm bg-dark text-white border-secondary"
                                    name="dates_echeances[]" value="<?= htmlspecialchars($ech['date_echeance']) ?>" required>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- =============================== -->
            <!-- Actions du formulaire           -->
            <!-- =============================== -->
            <div class="d-flex gap-3 mt-5 w-100">
                <button type="submit" class="btn-create-dash shadow-sm flex-fill">
                    <span class="btn-text">
                        💾 Modifier l'achat
                    </span>
                </button>

                <a href="<?= BASE_URL ?>router.php?p=dashboard_achats.php"
                   class="btn btn-secondary shadow-sm flex-fill d-flex align-items-center justify-content-center">
                    <span class="emoji fs-4 me-2">🛒</span>
                    Retour au Tableau de bord Achats
                </a>
            </div>
        </form>
    </div>
</div>
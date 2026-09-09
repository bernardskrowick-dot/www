<?php
/* Version: v1.21.1 (Rev #14) - 2026-08-18 */

/**
 * ajouter_achat.php - Vue du formulaire
 * Respect des règles : CSRF, Constantes, et Commentaires
 * CORRECTION : Sécurisation de la récupération des données POST pour éviter les notices/warnings.
 */

// 🔒 Sécurité : Vérification de la session
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// --- Récupération des catégories avec hiérarchie parent / sous-catégorie ---
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


// --- Préparation des variables formulaire (Sécurisées avec ?? pour éviter les Undefined array key) ---

$modeEcheances = $_POST['mode_echeances'] ?? 'unique';

// Récupération de la récurrence ('unique' par défaut)
$recurrence = $_POST['recurrence'] ?? 'unique';

// Nombre d'échéances
if ($modeEcheances === 'unique') {
    $nbEcheances = 1;
} else {
    $nbEcheances = intval($_POST['nb_echeances'] ?? 4);

    if ($nbEcheances < 1) {
        $nbEcheances = 4;
    }
}

// Données achat sécurisées
$titre = $_POST['titre'] ?? '';
$nomMarchand = $_POST['nom_marchand'] ?? '';
$montantTotal = $_POST['montant_total'] ?? '';
$dateDepart = $_POST['date_depart'] ?? date('Y-m-d');

// Sécurité date départ
if (empty($dateDepart) || strtotime($dateDepart) === false) {
    $dateDepart = date('Y-m-d');
}

// Date d'échéance du débit bancaire (spécifique au mode unique)
$dateEcheanceBancaire = $_POST['date_echeance_bancaire'] ?? '';
if (!empty($dateEcheanceBancaire) && strtotime($dateEcheanceBancaire) === false) {
    $dateEcheanceBancaire = '';
}

$categorieId = $_POST['categorie_id'] ?? 1;

// --- Gestion des dates d'échéances ---

$datesEcheances = $_POST['dates_echeances'] ?? [];

// Génération automatique des dates en mode automatique
if ($modeEcheances === 'auto' && empty($datesEcheances)) {
    for ($i = 0; $i < $nbEcheances; $i++) {
        // Adaptation de l'intervalle selon la récurrence
        $intervalle = ($recurrence === 'annuelle') ? "+{$i} year" : "+{$i} month";
        $datesEcheances[] = date(
            'Y-m-d',
            strtotime($intervalle, strtotime($dateDepart))
        );
    }
}

// Gestion des erreurs flash provenant de traiter_achat.php
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

?>
<div class="container mb-4 nav-dashboard-container">
    <div class="glass-card-nav mt-4 mb-0">

        <div class="d-flex flex-wrap align-items-center mt-3">
        </div>

        <div class="p-3 pt-0">

            <?php
            if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
                include DIR_LOGIC . 'top-bar-title-page.php';
            }
            ?>

        </div>

        <hr class="hr-glass">

        <?php if ($error): ?>
            <div class="alert alert-danger mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form id="formAchat" method="POST" action="<?= BASE_URL ?>router.php?p=traiter_achat.php"
            class="card p-4 bg-glass shadow-lg border-0">

            <?php csrf_input(); ?>

            <!-- =============================== -->
            <!-- Informations principales achat -->
            <!-- =============================== -->

            <div class="row">

                <!-- Titre -->
                <div class="col-md-6 mb-3">
                    <label class="form-label text-white-50 small fw-bold uppercase-tracking">
                        Titre de l'achat
                    </label>
                    <input type="text" class="form-control bg-dark text-white border-secondary" name="titre"
                        value="<?= htmlspecialchars($titre) ?>" placeholder="ex: Machine à café, Abonnement annuel..."
                        required>
                </div>

                <!-- Catégorie -->
                <div class="col-md-6 mb-3">
                    <label class="form-label text-white-50 small fw-bold uppercase-tracking">
                        Catégorie
                    </label>
                    <select class="form-select bg-dark text-white border-secondary" name="categorie_id">
                        <?php foreach ($categories as $cat): ?>
                            <?php
                            $isSousCategorie = !empty($cat['parent_id']);
                            $nomAffiche = $isSousCategorie
                                ? '└── 🏷️ ' . $cat['nom']
                                : '📁 ' . $cat['nom'];
                            ?>
                            <option value="<?= $cat['id'] ?>" <?= $categorieId == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nomAffiche) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="row">

                <!-- Marchand -->
                <div class="col-md-4 mb-3">
                    <label class="form-label text-white-50 small fw-bold uppercase-tracking">
                        Marchand
                    </label>
                    <input type="text" class="form-control bg-dark text-white border-secondary" name="nom_marchand"
                        value="<?= htmlspecialchars($nomMarchand) ?>" placeholder="ex: Amazon, Apple, Boulanger..."
                        required>
                </div>

                <!-- Montant -->
                <div class="col-md-4 mb-3">
                    <label class="form-label text-white-50 small fw-bold uppercase-tracking">
                        Montant total (€)
                    </label>
                    <input type="number" step="0.01" min="0" class="form-control bg-dark text-white border-secondary"
                        name="montant_total" value="<?= htmlspecialchars($montantTotal) ?>" placeholder="0.00" required>
                </div>

                <!-- Date départ -->
                <div class="col-md-4 mb-3">
                    <label class="form-label text-white-50 small fw-bold uppercase-tracking">
                        <?= $modeEcheances === 'unique' ? "Date de l'achat" : "Date du premier paiement" ?>
                    </label>
                    <input type="date" class="form-control bg-dark text-white border-secondary" name="date_depart"
                        value="<?= htmlspecialchars($dateDepart) ?>" required>
                </div>

            </div>

            <!-- =============================== -->
            <!-- Mode de répartition échéances -->
            <!-- =============================== -->

            <div class="row">

                <!-- Mode -->
                <div class="col-md-4 mb-3">
                    <label class="form-label text-info small fw-bold uppercase-tracking">
                        Mode de répartition
                    </label>
                    <select class="form-select bg-dark text-white border-accent-blue" name="mode_echeances"
                        onchange="refreshForm()">
                        <option value="auto" <?= $modeEcheances === 'auto' ? 'selected' : '' ?>>
                            Automatique
                        </option>
                        <option value="unique" <?= $modeEcheances === 'unique' ? 'selected' : '' ?>>
                            Unique
                        </option>
                    </select>
                </div>

                <!-- Récurrence -->
                <div class="col-md-4 mb-3">
                    <label class="form-label text-info small fw-bold uppercase-tracking">
                        Récurrence
                    </label>
                    <select class="form-select bg-dark text-white border-accent-blue" name="recurrence" onchange="refreshForm()">
                        <option value="unique" <?= $recurrence === 'unique' ? 'selected' : '' ?>>Unique</option>
                        <option value="mensuelle" <?= $recurrence === 'mensuelle' ? 'selected' : '' ?>>Mensuelle</option>
                        <option value="annuelle" <?= $recurrence === 'annuelle' ? 'selected' : '' ?>>Annuelle</option>
                    </select>
                </div>

                <?php if ($modeEcheances === 'auto'): ?>
                    <!-- Nombre échéances -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-white-50 small fw-bold uppercase-tracking">
                            Nombre d'échéances
                        </label>
                        <div class="d-flex gap-2">
                            <input type="number" min="1" max="60" class="form-control bg-dark text-white border-secondary"
                                name="nb_echeances" id="nbEcheancesInput" value="<?= $nbEcheances ?>">
                            <button type="button" class="btn btn-outline-info flex-shrink-0" onclick="refreshForm()">
                                🔄 Mettre à jour
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Date d'échéance du débit bancaire (Visible uniquement en mode unique) -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-white-50 small fw-bold uppercase-tracking">
                            Date d'échéance du débit <span class="text-muted">(Optionnel)</span>
                        </label>
                        <input type="date" class="form-control bg-dark text-white border-secondary" name="date_echeance_bancaire"
                            value="<?= htmlspecialchars($dateEcheanceBancaire) ?>" placeholder="jj/mm/aaaa">
                    </div>
                <?php endif; ?>

            </div>

            <?php if ($modeEcheances === 'auto'): ?>
                <!-- =============================== -->
                <!-- Dates échéances automatiques -->
                <!-- =============================== -->
                <div class="card bg-dark-soft p-3 mt-3 border-secondary">
                    <h5 class="text-info mb-3 small fw-bold text-uppercase">
                        🗓️ Dates des échéances
                    </h5>
                    <div class="row g-2">
                        <?php for ($i = 0; $i < $nbEcheances; $i++): ?>
                            <div class="col-md-3">
                                <label class="small text-muted">
                                    Éch. <?= $i + 1 ?>
                                </label>
                                <input type="date" class="form-control form-control-sm bg-dark text-white" name="dates_echeances[]"
                                    value="<?= htmlspecialchars($datesEcheances[$i] ?? '') ?>" required>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- =============================== -->
            <!-- Actions formulaire             -->
            <!-- =============================== -->
            <div class="d-flex gap-3 mt-5 w-100">
                <button type="submit"
                    onclick="if(this.form.checkValidity()) { this.disabled=true; this.form.submit(); }"
                    class="btn-create-dash shadow-sm flex-fill">
                    <span class="spinner-border spinner-border-sm d-none"
                        role="status"
                        aria-hidden="true"></span>
                    <span class="btn-text">
                        💾 Enregistrer l'achat
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
    <script>
        function refreshForm() {
            const form = document.getElementById('formAchat');
            // Désactive la validation HTML pendant le rafraîchissement
            form.noValidate = true;

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'mode_submit';
            hidden.value = '1';
            form.appendChild(hidden);

            form.action = "<?= BASE_URL ?>router.php?p=ajouter_achat.php";
            form.submit();
        }
    </script>
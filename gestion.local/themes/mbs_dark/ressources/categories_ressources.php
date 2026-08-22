<?php
/* Version: v1.21.0 (Rev #15) - 2026-08-08 */

/**
 * categories_ressources.php - Administration des catégories de ressources
 * Gestion catégories principales / sous-catégories
 * RÈGLES : CSRF, PDO, user_id obligatoire
 */
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// =====================================================
// KPI CATEGORIES RESSOURCES
// =====================================================

// Nombre de catégories principales
$stmtKpiCategories = $pdo->prepare(
    'SELECT COUNT(*) 
     FROM categories_ressources
     WHERE user_id = ?
     AND parent_id IS NULL'
);
$stmtKpiCategories->execute([$userId]);
$nbCategories = (int) $stmtKpiCategories->fetchColumn();

// Nombre de sous-catégories
$stmtKpiSousCategories = $pdo->prepare(
    'SELECT COUNT(*) 
     FROM categories_ressources
     WHERE user_id = ?
     AND parent_id IS NOT NULL'
);
$stmtKpiSousCategories->execute([$userId]);
$nbSousCategories = (int) $stmtKpiSousCategories->fetchColumn();

// =====================================================
// CONTEXTE ASSIGNATION RESSOURCE
// =====================================================

$assign_to = filter_input(INPUT_GET, 'assign_to', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_POST, 'assign_to_post', FILTER_VALIDATE_INT);

$nomRessourceContexte = null;

if ($assign_to) {
    $stmtRessource = $pdo->prepare('SELECT titre FROM ressources WHERE id = ? AND user_id = ?');
    $stmtRessource->execute([$assign_to, $userId]);
    $ressourceData = $stmtRessource->fetch();
    $nomRessourceContexte = $ressourceData['titre'] ?? 'Ressource inconnue';
}

// =====================================================
// ASSIGNATION DIRECTE D'UNE CATEGORIE A UNE RESSOURCE
// =====================================================

if (isset($_GET['select_cat']) && $assign_to) {
    if (isset($_GET['csrf']) && $_GET['csrf'] === $_SESSION['csrf_token']) {
        $idCat = (int) $_GET['select_cat'];

        // Vérification que la catégorie appartient bien à l'utilisateur
        $stmtCheckCat = $pdo->prepare(
            'SELECT id 
             FROM categories_ressources 
             WHERE id = ?
             AND user_id = ?'
        );
        $stmtCheckCat->execute([$idCat, $userId]);

        if ($stmtCheckCat->fetch()) {
            $stmt = $pdo->prepare(
                'UPDATE ressources 
                 SET categorie_id = ?
                 WHERE id = ?
                 AND user_id = ?'
            );
            $stmt->execute([$idCat, $assign_to, $userId]);

            $_SESSION['flash_success'] = "La ressource a été classée avec succès !";
        }
    }

    header('Location: ' . BASE_URL . 'router.php?p=dashboard_revenus.php');
    exit;
}

// =====================================================
// TRAITEMENT AJOUT / MODIFICATION CATEGORIE RESSOURCE
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $nom = trim($_POST['nom_categorie'] ?? '');
    $couleur = $_POST['couleur_categorie'] ?? '#6c757d';
    $parent_id = filter_input(INPUT_POST, 'parent_id', FILTER_VALIDATE_INT);
    $parent_id = $parent_id ?: null;
    $idModif = filter_input(INPUT_POST, 'id_categorie', FILTER_VALIDATE_INT);

    // Une catégorie ne peut pas être son propre parent
    if ($idModif && $parent_id == $idModif) {
        $parent_id = null;
    }

    if (!empty($nom)) {
        // Vérification doublon uniquement chez l'utilisateur
        $stmtCheck = $pdo->prepare(
            'SELECT id 
             FROM categories_ressources
             WHERE LOWER(nom) = LOWER(?)
             AND id != ?
             AND user_id = ?'
        );
        $stmtCheck->execute([$nom, $idModif ?: 0, $userId]);

        if ($stmtCheck->fetch()) {
            $_SESSION['flash_error'] = "Erreur : La catégorie '$nom' existe déjà.";
            header('Location: ' . BASE_URL . 'router.php?p=categories_ressources.php' . ($assign_to ? '&assign_to=' . $assign_to : ''));
            exit;
        }

        // ==============================
        // MODIFICATION
        // ==============================
        if ($idModif) {
            $stmt = $pdo->prepare('UPDATE categories_ressources SET nom = ?, couleur = ?, parent_id = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
            $stmt->execute([$nom, $couleur, $parent_id, $idModif, $userId]);

            $_SESSION['flash_success'] = 'Catégorie de ressource mise à jour !';
        } else {
            // ==============================
            // CREATION
            // ==============================
            $stmt = $pdo->prepare('INSERT INTO categories_ressources (nom, couleur, parent_id, user_id, updated_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute([$nom, $couleur, $parent_id, $userId]);

            if ($assign_to) {
                $newCatId = $pdo->lastInsertId();

                $stmtAssign = $pdo->prepare(
                    'UPDATE ressources
                     SET categorie_id = ?
                     WHERE id = ?
                     AND user_id = ?'
                );
                $stmtAssign->execute([$newCatId, $assign_to, $userId]);

                $_SESSION['flash_success'] = "Catégorie '$nom' créée et assignée !";

                header('Location: ' . BASE_URL . 'router.php?p=dashboard_revenus.php');
                exit;
            }

            $_SESSION['flash_success'] = "Catégorie '$nom' ajoutée !";
        }
    }

    header('Location: ' . BASE_URL . 'router.php?p=categories_ressources.php' . ($assign_to ? '&assign_to=' . $assign_to : ''));
    exit;
}

// =====================================================
// RÉCUPÉRATION DES CATÉGORIES PARENTS
// =====================================================
$stmtParents = $pdo->prepare('
    SELECT id, nom
    FROM categories_ressources
    WHERE user_id = ?
      AND parent_id IS NULL
    ORDER BY nom ASC
');
$stmtParents->execute([$userId]);
$categoriesParentes = $stmtParents->fetchAll();

// =====================================================
// RÉCUPÉRATION POUR MODIFICATION
// =====================================================
$catAModifier = null;

if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT id, nom, couleur, parent_id FROM categories_ressources WHERE id = ? AND user_id = ?');
    $stmt->execute([$_GET['edit'], $userId]);
    $catAModifier = $stmt->fetch();
}

// =====================================================
// RÉCUPÉRATION DE TOUTES LES CATÉGORIES DE RESSOURCES
// =====================================================
$stmtList = $pdo->prepare(
    'SELECT *
     FROM categories_ressources
     WHERE user_id = ?
     ORDER BY nom ASC'
);
$stmtList->execute([$userId]);
$categoriesBrutes = $stmtList->fetchAll();

$categories = [];

foreach ($categoriesBrutes as $cat) {
    if ($cat['parent_id'] === null) {
        $categories[] = $cat;

        foreach ($categoriesBrutes as $sousCat) {
            if ($sousCat['parent_id'] == $cat['id']) {
                $categories[] = $sousCat;
            }
        }
    }
}
?>

<div>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger bg-glass border-danger text-white mb-4 shadow-lg">
            ⚠️ <?= $_SESSION['flash_error'];
            unset($_SESSION['flash_error']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success bg-glass border-success text-white mb-4 shadow-lg">
            ✅ <?= $_SESSION['flash_success'];
            unset($_SESSION['flash_success']); ?>
        </div>
    <?php endif; ?>

    <?php if ($nomRessourceContexte): ?>
        <div class="alert bg-glass border-glass text-white shadow-lg mb-4 d-flex align-items-center justify-content-between p-3"
            style="border-left: 5px solid #00f2ff !important;">
            <div class="fs-5">
                <span class="emoji me-2">🎯</span> Classement de la ressource :
                <strong class="text-accent-blue"><?= htmlspecialchars($nomRessourceContexte, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <a href="<?= BASE_URL ?>router.php?p=categories_ressources.php"
                class="btn-action-dash btn-dash-orange py-1 text-decoration-none">
                Annuler
            </a>
        </div>
    <?php endif; ?>

    <div class="container mb-4 nav-dashboard-container">
        <div class="glass-card-nav mt-4 mb-4">
            <div class="d-flex flex-wrap align-items-center mt-3"></div>

            <div class="p-3 pt-0">
                <?php
                if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
                    include DIR_LOGIC . 'top-bar-title-page.php';
                }
                ?>
            </div>

            <hr class="hr-glass mt-4 mb-4" style="opacity: 0.6;">

           <div class="d-flex align-items-center gap-md-3 gap-1 flex-wrap mt-0 mb-4">
                <!-- Bouton retour -->
                <a href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php"
                    class="glass-card-nav card-kpi border-0 shadow-lg text-decoration-none gap-md-3 gap-1 mb-0 " style="height: 100%; padding: 29px 20px !important;">
                    <div class="d-flex align-items-center gap-md-3 mb-0 text-nowrap">
                        <div class="fw-bold small text-uppercase">
                            <span class="emoji"> 💰</span> Retour au Tableau de bord Revenus
                        </div>
                    </div>
                </a>

                <!-- KPI Catégories -->
                <div class="glass-card-nav card-kpi border-0 shadow-lg gap-md-3 gap-1 mb-0" style="max-width: 350px;">
                    <div class="d-flex align-items-center ">
                        <div class="text-accent-blue fw-bold text-uppercase text-nowrap small">
                            <span class="emoji"> 📁</span> Catégories <span class="fw-bold text-white fs-5 ms-3"><?= (int) $nbCategories ?></span>
                        </div>
                    </div>
                </div>

                <!-- KPI Sous-catégories -->
                <div class="glass-card-nav card-kpi border-0 shadow-lg gap-md-3 gap-1 mb-0" style="max-width: 350px;">
                    <div class="d-flex align-items-center ">
                        <div class="text-accent-blue fw-bold text-uppercase text-nowrap small">
                            <span class="emoji"> 🏷️</span> Sous-catégories <span class="fw-bold text-white fs-5 ms-3"><?= (int) $nbSousCategories ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr class="hr-glass" style="opacity: 0.6;">

            <div class="row mt-0">
                <div class="col-md-4 mb-0">
                    <div class="card bg-glass border-0 p-3 shadow-lg">
                        <div class="card-body" style="padding: 20px 10px !important;">
                            <h5 class="text-white mb-4 text-nowrap">
                                <?= $catAModifier ? '✏️ Modifier la catégorie' : '✨ Ajouter une Catégorie' ?>
                                </h5>

                            <form method="POST">
                                <?php csrf_input(); ?>

                                <?php if ($assign_to): ?>
                                    <input type="hidden" name="assign_to_post" value="<?= (int) $assign_to ?>">
                                <?php endif; ?>

                                <?php if ($catAModifier): ?>
                                                                                                                                    <input type="hidden" name="id_categorie" value="<?= (int) $catAModifier['id'] ?>">
                                                                                                                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="text-accent-blue fw-bold label-style d-block mb-2">NOM</label>
                                    <input type="text" name="nom_categorie"
                                        class="form-control bg-glass-light text-white border-glass"
                                        value="<?= $catAModifier ? htmlspecialchars($catAModifier['nom'], ENT_QUOTES, 'UTF-8') : '' ?>"
                                        placeholder="ex: Salaire, Investissements..." required>
                                </div>

                                <div class="mb-3">
                                    <label class="text-accent-blue fw-bold label-style d-block mb-2">
                                        CATÉGORIE PARENTE
                                    </label>

                                    <select name="parent_id" class="form-select bg-glass-light text-white border-glass">
                                        <option value="">Aucune (Catégorie principale)</option>
                                        <?php foreach ($categoriesParentes as $parent): ?>
                                            <option value="<?= $parent['id'] ?>" <?= ($catAModifier && $catAModifier['parent_id'] == $parent['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($parent['nom'], ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                                <?php endforeach; ?>
                                                </select>

                                    <div class="form-text text-accent-green fst-italic">
                                        💡 Laissez <strong>Aucune</strong> pour créer une catégorie principale.
                                        Sélectionnez une catégorie pour créer une sous-catégorie.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="text-accent-blue fw-bold label-style d-block mb-2">COULEUR</label>
                                    <input type="color" name="couleur_categorie"
                                        class="form-control form-control-color w-100 bg-glass-light border-glass"
                                        value="<?= $catAModifier ? htmlspecialchars($catAModifier['couleur'], ENT_QUOTES, 'UTF-8') : '#0d6efd' ?>">
                                </div>

                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit"
                                        class="<?= $catAModifier ? 'btn-modifier-neon' : 'btn-payer-neon' ?> border-0 py-2">
                                        <?= $catAModifier ? '<span class="emoji fs-4">🔄</span> Mettre à jour' : '<span class="emoji fs-4">💾</span> Enregistrer' ?>
                                    </button>
                                    <?php if ($catAModifier): ?>
                                        <a href="<?= BASE_URL ?>router.php?p=categories_ressources.php<?= $assign_to ? '&assign_to=' . $assign_to : '' ?>"
                                           class="btn btn-secondary text-decoration-none d-flex align-items-center justify-content-center gap-2">
                                           <span class="emoji fs-4">❌</span> 
                                           <div class="fw-bold small text-uppercase">Annuler</div>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-8 p-0 mb-5">
                    <div class="card bg-glass border-0 p-3 shadow-lg">
                        <div class="card-body">
                            <h5 class="text-white mb-4">Catégories Actuelles</h5>
                            <table class="table-custom-dark align-middle text-white w-100">
                                <thead>
                                    <tr class="text-accent-blue label-style">
                                        <th class="th-title">COULEUR</th>
                                        <th class="th-title">NOM</th>
                                        <th class="th-action text-end">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody class="border-0">
                                    <?php foreach ($categories as $cat): ?>
                                                                                                                                        <?php
                                                                                                                                        $isSousCategorie = !empty($cat['parent_id']);
                                                                                                                                        $nomAffiche = $isSousCategorie
                                                                                                                                            ? '└── 🏷️ ' . $cat['nom']
                                                                                                                                            : '📁 ' . $cat['nom'];
                                                                                                                                        ?>
                                        <tr>
                                            <td data-label="Couleurs" style="width: 80px;">
                                                <div style="
                                                    background-color: <?= htmlspecialchars($cat['couleur'], ENT_QUOTES, 'UTF-8') ?>;
                                                    width: 40px;
                                                    height: 12px;
                                                    border-radius: 6px;
                                                    box-shadow: 0 0 10px <?= htmlspecialchars($cat['couleur'], ENT_QUOTES, 'UTF-8') ?>88;
                                                "></div>
                                            </td>

                                            <td data-label="Nom" class="fw-bold fs-6">
                                                <?php if ($isSousCategorie): ?>
                                                                                                                                                                                                                                                    <span class="ms-4" style="color: <?= htmlspecialchars($cat['couleur'], ENT_QUOTES, 'UTF-8') ?>;">
                                                                                                                                                                                                                                                <?= htmlspecialchars($nomAffiche, ENT_QUOTES, 'UTF-8') ?>
                                                                                                                                                                                                                                            </span>
                                                                                                                                                                                                                                            <?php else: ?>
                                                    <?= htmlspecialchars($nomAffiche, ENT_QUOTES, 'UTF-8') ?>
                                                    <?php endif; ?>
                                                    </td>

                                            <td class="td-action text-end">
                                                <?php if ($assign_to): ?>
                                                                                                                                                                                                                                                    <a href="<?= BASE_URL ?>router.php?p=categories_ressources.php&assign_to=<?= (int) $assign_to ?>&select_cat=<?= (int) $cat['id'] ?>&csrf=<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                                                                                                                                                                                                                                                class="btn-action-dash btn-dash-green me-2" title="Choisir cette catégorie">
                                                                                                                                                                                                                                                ✅ Choisir
                                                                                                                                                                                                                                            </a>
                                                                                                                                                                                                                                            <?php endif; ?>

                                                <a href="<?= BASE_URL ?>router.php?p=categories_ressources.php&edit=<?= (int) $cat['id'] ?><?= $assign_to ? '&assign_to=' . (int) $assign_to : '' ?>"
                                                    class="btn-action-dash btn-dash-blue" title="Modifier">
                                                    ✏️ Modifier
                                                </a>

                                                <?php if ($cat['id'] != 1): ?>
                                                                                                                                                                                                                                                                    <a href="<?= BASE_URL ?>router.php?p=supprimer_categorie_ressource.php&id=<?= (int) $cat['id'] ?>&csrf=<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                                                                                                                                                                                                                                                class="btn-action-dash btn-dash-orange" onclick="return confirm('Attention : suppression de cette catégorie ?')">
                                                                                                                                                                                                                                                🗑️ Supprimer
                                                                                                                                                                                                                                            </a>
                                                                                                                                                                                                                                            <?php endif; ?>
                                                                                                                                                                                                                                            </td>
                                                                                                                                                                                                                                            </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
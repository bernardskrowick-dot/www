<?php
/* Version: v1.19.1 (Rev #25) - 2026-08-06 */
/**
 * Page : Historique des Ressources
 * RÈGLES APPLIQUÉES :
 * - Sécurité session & requêtes préparées PDO
 * - Harmonisation des jointures de catégories (principale + parente)
 * - Correction des variables de requêtes ($stmtTotal et $stmt)
 */

// 🔒 Sécurité : Vérification de la session
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$aujourdhui = date('Y-m-d');

// -------------------------------------------------------------------------
// 1. Pagination
// -------------------------------------------------------------------------
$ressourcesParPage = 10;
$pageCourante = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($pageCourante - 1) * $ressourcesParPage;

// -------------------------------------------------------------------------
// 2. Filtres
// -------------------------------------------------------------------------
$search = $_GET['search'] ?? '';
$categorieFilter = $_GET['categorie_filter'] ?? '';
$dateFilter = (!empty($_GET['date_filter']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_filter']))
    ? $_GET['date_filter']
    : null;
$anneeFilter = $_GET['annee_filter'] ?? '';

// -------------------------------------------------------------------------
// 3. Totaux globaux (Correction de la variable $stmtTotal et des jointures)
// -------------------------------------------------------------------------
$stmtTotal = $pdo->prepare("
    SELECT 
        SUM(v.montant_prevu) AS total_prevu,
        SUM(CASE WHEN v.statut='percu' THEN COALESCE(v.montant_reel, v.montant_prevu) ELSE 0 END) AS total_percu
    FROM ressources r
    LEFT JOIN categories_ressources c ON c.id = r.categorie_id
    LEFT JOIN categories_ressources parent_cat ON c.parent_id = parent_cat.id
    LEFT JOIN versements v ON v.ressource_id = r.id
    WHERE r.user_id = :user_id
      AND (:search = '' OR r.titre LIKE :search)
      AND (:categorie_filter = '' OR r.categorie_id = :categorie_filter OR r.categorie_id IN (SELECT id FROM categories_ressources WHERE parent_id = :categorie_filter))
      AND (:date_filter IS NULL OR v.date_versement_prevue >= :date_filter OR v.date_perception >= :date_filter)
      AND (:annee_filter = '' OR YEAR(v.date_versement_prevue) = :annee_filter OR YEAR(v.date_perception) = :annee_filter)
");
$stmtTotal->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmtTotal->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
$stmtTotal->bindValue(':categorie_filter', $categorieFilter, PDO::PARAM_STR);
$stmtTotal->bindValue(':date_filter', $dateFilter, $dateFilter === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
$stmtTotal->bindValue(':annee_filter', $anneeFilter, PDO::PARAM_STR);
$stmtTotal->execute();
$resultTotal = $stmtTotal->fetch(PDO::FETCH_ASSOC);

$totalPrevu = $resultTotal['total_prevu'] ?? 0;
$totalPercu = $resultTotal['total_percu'] ?? 0;
$resteGlobal = $totalPrevu - $totalPercu;
$pourcentageGlobal = ($totalPrevu > 0) ? ($totalPercu / $totalPrevu) * 100 : 0;

// -------------------------------------------------------------------------
// 4. Comptage total pour pagination
// -------------------------------------------------------------------------
$stmtCount = $pdo->prepare("
    SELECT COUNT(DISTINCT r.id) AS total
    FROM ressources r
    LEFT JOIN versements v ON v.ressource_id = r.id
    WHERE r.user_id = :user_id
      AND (:search = '' OR r.titre LIKE :search)
      AND (:categorie_filter = '' OR r.categorie_id = :categorie_filter OR r.categorie_id IN (SELECT id FROM categories_ressources WHERE parent_id = :categorie_filter))
      AND (:date_filter IS NULL OR v.date_versement_prevue >= :date_filter OR v.date_perception >= :date_filter)
      AND (:annee_filter = '' OR YEAR(v.date_versement_prevue) = :annee_filter OR YEAR(v.date_perception) = :annee_filter)
");
$stmtCount->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmtCount->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
$stmtCount->bindValue(':categorie_filter', $categorieFilter, PDO::PARAM_STR);
$stmtCount->bindValue(':date_filter', $dateFilter, $dateFilter === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
$stmtCount->bindValue(':annee_filter', $anneeFilter, PDO::PARAM_STR);
$stmtCount->execute();
$totalRessources = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$totalPages = ceil($totalRessources / $ressourcesParPage);

// -------------------------------------------------------------------------
// 5. Liste des ressources (Intégration propre de la catégorie et de sa parente)
// -------------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT r.*, 
           cr.nom AS categorie_nom,
           cr.parent_id AS categorie_parent_id,
           parent_cat.nom AS categorie_parente_nom,
           cr.couleur AS categorie_couleur,
           SUM(v.montant_prevu) AS total_prevu,
           SUM(CASE WHEN v.statut='percu' THEN COALESCE(v.montant_reel, v.montant_prevu) ELSE 0 END) AS total_percu,
           MAX(CASE WHEN v.statut='percu' THEN COALESCE(v.date_perception, v.date_versement_prevue) END) AS derniere_date_percue,
           MIN(CASE WHEN v.statut='attendu' THEN v.date_versement_prevue END) AS prochaine_versement
    FROM ressources r
    LEFT JOIN versements v ON v.ressource_id = r.id
    LEFT JOIN categories_ressources cr ON cr.id = r.categorie_id
    LEFT JOIN categories_ressources parent_cat ON cr.parent_id = parent_cat.id
    WHERE r.user_id = :user_id
      AND (:search = '' OR r.titre LIKE :search)
      AND (:categorie_filter = '' OR r.categorie_id = :categorie_filter OR r.categorie_id IN (SELECT id FROM categories_ressources WHERE parent_id = :categorie_filter))
      AND (:date_filter IS NULL OR v.date_versement_prevue >= :date_filter OR v.date_perception >= :date_filter)
      AND (:annee_filter = '' OR YEAR(v.date_versement_prevue) = :annee_filter OR YEAR(v.date_perception) = :annee_filter)
    GROUP BY r.id, cr.nom, cr.parent_id, parent_cat.nom, cr.couleur
    ORDER BY COALESCE(MAX(CASE WHEN v.statut='percu' THEN COALESCE(v.date_perception, v.date_versement_prevue) END), MIN(CASE WHEN v.statut='attendu' THEN v.date_versement_prevue END), r.date_depart) DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
$stmt->bindValue(':categorie_filter', $categorieFilter, PDO::PARAM_STR);
$stmt->bindValue(':date_filter', $dateFilter, $dateFilter === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
$stmt->bindValue(':annee_filter', $anneeFilter, PDO::PARAM_STR);
$stmt->bindValue(':limit', $ressourcesParPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$ressourcesPage = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------------------------------------------------------------
// 6. Liste des années pour filtre
// -------------------------------------------------------------------------
$stmtAnnees = $pdo->prepare("
    SELECT DISTINCT YEAR(COALESCE(v.date_perception, v.date_versement_prevue)) AS annee 
    FROM versements v
    INNER JOIN ressources r ON r.id = v.ressource_id
    WHERE r.user_id = :uid 
      AND (v.date_perception IS NOT NULL OR v.date_versement_prevue IS NOT NULL)
    ORDER BY annee DESC
");
$stmtAnnees->execute(['uid' => $user_id]);
$anneesDisponibles = $stmtAnnees->fetchAll(PDO::FETCH_COLUMN);

// -------------------------------------------------------------------------
// 7. Vue annuelle des ressources
// -------------------------------------------------------------------------
$anneeVue = date('Y');

$stmtAnnee = $pdo->prepare("
    SELECT
        MONTH(v.date_versement_prevue) AS mois,
        SUM(v.montant_prevu) AS total_prevu,
        SUM(
            CASE
                WHEN v.statut = 'percu'
                THEN COALESCE(v.montant_reel, v.montant_prevu)
                ELSE 0
            END
        ) AS total_percu
    FROM versements v
    INNER JOIN ressources r ON r.id = v.ressource_id
    WHERE r.user_id = :user_id
      AND YEAR(v.date_versement_prevue) = :annee
    GROUP BY MONTH(v.date_versement_prevue)
");

$stmtAnnee->execute([
    ':user_id' => $user_id,
    ':annee' => $anneeVue
]);

$donneesAnnuelles = [];
foreach ($stmtAnnee->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
    $donneesAnnuelles[(int) $ligne['mois']] = $ligne;
}
?>

   <div class="container mb-5 nav-dashboard-container">
    <div class="glass-card-nav mt-4 mb-4">

        <div class="d-flex flex-wrap align-items-center mt-3">
        </div>

        <div class="p-3 pt-0">
            <?php
            if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
                include DIR_LOGIC . 'top-bar-title-page.php';
            }
            ?>
        </div>

    <hr class="hr-glass" style="opacity: 0.6;">

    <div class="text-left mt-0 mb-0">
        <a href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php" class="btn btn-secondary shadow-sm mt-0">
            <span class="emoji fs-4">💰</span> Retour au Tableau de bord Revenus
        </a>
    </div>

    <hr class="hr-glass" style="opacity: 0.6;">

    <div class="row g-3 mb-4 mt-2">

    <!-- KPI : Total prévu -->
    <div class="col-md-4">
        <div class="form-label glass-card-nav  border-accent-blue p-3 text-center card-kpi">

            <small class="text-accent-blue fw-bold uppercase-tracking">
                <span class="emoji fs-4">💰</span> Total prévu

                <!-- Infobulle -->
                <span class="info-tooltip ms-1 text-info fs-5">
                    ⓘ
                    <span class="info-tooltip-text">
                        Montant total des revenus prévus sur la période sélectionnée.
                        <br><br>
                        Il s'agit de la somme de tous les revenus attendus,
                        qu'ils aient été perçus ou non.
                    </span>
                </span>
            </small>

            <div class="fs-3 fw-800 text-white mt-2">
                <?= number_format($totalPrevu, 2, ',', ' ') ?> €
            </div>

        </div>
    </div>


    <!-- KPI : Total perçu -->
    <div class="col-md-4">
        <div class="form-label glass-card-nav  border-success p-3 text-center card-kpi">

            <small class="text-success fw-bold uppercase-tracking">
                <span class="emoji fs-4">✅</span> Total perçu

                <!-- Infobulle -->
                <span class="info-tooltip ms-1 text-info fs-5">
                    ⓘ
                    <span class="info-tooltip-text">
                        Somme de tous les revenus effectivement encaissés
                        sur la période sélectionnée.
                    </span>
                </span>
            </small>

            <div class="fs-3 fw-800 text-white mt-2">
                <?= number_format($totalPercu, 2, ',', ' ') ?> €
            </div>

        </div>
    </div>


    <!-- KPI : Reste attendu -->
    <div class="col-md-4">
        <div class="form-label glass-card-nav  <?= ($resteGlobal > 0) ? 'border-danger' : 'border-secondary' ?> p-3 text-center card-kpi">

            <small class="<?= ($resteGlobal > 0) ? 'text-danger' : 'text-muted' ?> fw-bold uppercase-tracking">
                <span class="emoji fs-4">💵</span> Reste attendu

                <!-- Infobulle -->
                <span class="info-tooltip ms-1 text-info fs-5">
                    ⓘ
                    <span class="info-tooltip-text">
                        Différence entre le montant total prévu et le montant déjà perçu.
                        <br><br>
                        Calcul :
                        <strong>Total prévu − Total perçu</strong>.
                    </span>
                </span>
            </small>

            <div class="fs-3 fw-800 text-white mt-2">
                <?= number_format($resteGlobal, 2, ',', ' ') ?> €
            </div>

        </div>
    </div>

</div>

    <!-- 📊 VUE ANNUELLE DES RESSOURCES -->
    <div class="glass-card-nav  p-4 mb-4 border-0">
        <h5 class="text-white mb-4">
            📅 Vue annuelle <?= $anneeVue ?>
        </h5>

        <div class="row g-3">
            <?php
            $moisNoms = [
                1 => 'Janvier',
                2 => 'Février',
                3 => 'Mars',
                4 => 'Avril',
                5 => 'Mai',
                6 => 'Juin',
                7 => 'Juillet',
                8 => 'Août',
                9 => 'Septembre',
                10 => 'Octobre',
                11 => 'Novembre',
                12 => 'Décembre'
            ];

            foreach ($moisNoms as $num => $nom):
                $prevu = $donneesAnnuelles[$num]['total_prevu'] ?? 0;
                $percu = $donneesAnnuelles[$num]['total_percu'] ?? 0;
                $pourcentageMois = ($prevu > 0) ? round(($percu / $prevu) * 100) : 0;
                ?>

                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card bg-dark border-secondary h-100">
                        <div class="card-body text-center">
                            <h6 class="text-info mb-3"><?= $nom ?></h6>
                            <small class="text-muted d-block">Prévu</small>
                            <div class="text-white fw-bold mb-2"><?= number_format($prevu, 2, ',', ' ') ?> €</div>
                            <small class="text-muted d-block">Perçu</small>
                            <div class="text-success fw-bold mb-3"><?= number_format($percu, 2, ',', ' ') ?> €</div>
                            <div class="progress progress-custom-dark">
                                <div class="progress-bar progress-bar-neon bg-neon-amber"
                                    style="width: <?= $pourcentageMois ?>%"></div>
                            </div>
                            <small class="text-muted"><?= $pourcentageMois ?>%</small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Filtres -->
    <form method="GET" action="router.php" class="glass-card-nav  p-3 mb-4 border-0">
        <input type="hidden" name="p" value="historique_ressources.php">
        <div class="row g-3 align-items-center justify-content-center ">
            <div class="col-md-3">
                <label class="text-white small mb-1">
                    <span class="emoji fs-4">🔍</span> Rechercher</label>
                <input type="text" name="search" class="form-control bg-dark text-white border-secondary shadow-sm"
                    value="<?= htmlspecialchars($search) ?>" placeholder="Titre...">
            </div>
            
            <!-- EXEMPLE APPLIQUÉ : Filtre de catégorie hiérarchique -->
            <div class="col-md-3">
                <label class="form-label small fw-bold text-white-50"><span class="emoji fs-4">📁</span> CATÉGORIE</label>
                <select name="categorie_filter" class="form-select bg-dark text-white border-secondary w-100">
                    <option value="">Toutes les catégories</option>
                    <?php
                    $catStmt = $pdo->prepare('
                        SELECT id, nom, parent_id
                        FROM categories_ressources
                        WHERE user_id = ?
                        ORDER BY 
                            COALESCE(parent_id, id),
                            parent_id IS NOT NULL,
                            nom ASC
                    ');
                    $catStmt->execute([$user_id]);

                    foreach ($catStmt->fetchAll() as $c):
                        $nomCategorie = !empty($c['parent_id'])
                            ? '└── 🏷️ ' . $c['nom']
                            : '📁 ' . $c['nom'];
                        ?>
                        <option value="<?= $c['id'] ?>" <?= ($categorieFilter == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nomCategorie) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filtre par année -->
            <div class="col-md-2">
                <label class="text-white small mb-1">
                    <span class="emoji fs-4">📅</span> Année</label>
                <select name="annee_filter" class="form-select bg-dark text-white border-secondary shadow-sm">
                    <option value="">Toutes</option>
                    <?php foreach ($anneesDisponibles as $annee): ?>
                        <?php if ($annee): ?>
                            <option value="<?= htmlspecialchars($annee) ?>" <?= ($anneeFilter == $annee) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($annee) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="text-white small mb-1">
                    <span class="emoji fs-4">📅</span> Depuis le</label>
                <input type="date" name="date_filter" class="form-control bg-dark text-white border-secondary shadow-sm"
                    value="<?= htmlspecialchars($dateFilter ?? '') ?>">
            </div>
<div class="row g-3 mt-1">
            <div class="col d-flex align-items-end gap-2">
                
                <!-- BOUTON FILTRER -->
                <button type="submit"
                    class="btn-create-dash shadow-sm px-3 d-inline-flex align-items-center justify-content-center w-100"
                    style="height: 38px;">
                    <span class="emoji fs-4">🔍</span> Filtrer
                </button>

                <!-- BOUTON Effacer -->
                <a href="<?= BASE_URL ?>router.php?p=historique_ressources.php"
                    class="btn-modifier-neon shadow-sm px-3 text-nowrap text-decoration-none d-inline-flex align-items-center justify-content-center w-100"
                    style="height: 38px;" title="Réinitialiser tous les filtres">
                    <span class="emoji fs-4">🔄 </span> Effacer les filtres
                </a>
            </div>
        </div>
        </div>
        
    </form>

    <!-- Tableau des ressources -->
    <div class="table-responsive shadow-lg">
        <table class="table table-dark table-hover mb-0 bg-glass table-custom-dark">
            <thead>
                <tr>
                    <th style="width:20%">Titre</th>
                    <th style="width:16%">Organisme</th>
                    <th style="width:10%" class="text-end">Total prévu</th>
                    <th style="width:10%" class="text-end">Perçu</th>
                    <th style="width:12%" class="text-center">Date perçu</th>
                    <th style="width:10%" class="text-end">Reste attendu</th>
                    <th style="width:12%" class="text-center">Prochain versement</th>
                    <th style="width:10%" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ressourcesPage)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Aucune ressource trouvée.</td>
                    </tr>
                <?php else: ?>
                    <?php
                    foreach ($ressourcesPage as $r):
                        $totalPrevuRes = $r['total_prevu'] ?? 0;
                        $totalPercuRes = $r['total_percu'] ?? 0;
                        $reste = $totalPrevuRes - $totalPercuRes;
                        $estEnRetard = ($r['prochaine_versement'] && $r['prochaine_versement'] < $aujourdhui && $reste > 0);
                        $classeBordure = ($reste <= 0 && $totalPrevuRes > 0) ? 'border-success' : ($estEnRetard ? 'border-danger' : 'border-secondary');

                        // Gestion des couleurs et de la hiérarchie des catégories
                        $catColor = $r['categorie_couleur'] ?? '#ff8000';
                        $aUneParente = !empty($r['categorie_parente_nom']);
                        $ressourceId = (int) $r['id'];
                        ?>
                        <tr class="align-middle <?= $classeBordure ?>">
                            <td data-label="Titre">
                                <!-- Titre principal -->
                                <div class="fw-bold text-white mb-1 me-4">
                                    <?= htmlspecialchars($r['titre'], ENT_QUOTES, 'UTF-8') ?>
                                </div>

                                <!-- Conteneur des badges corrigé (Hiérarchie inversée : Catégorie directe d'abord, puis Parente) -->
                                <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                    
                                    <!-- Badge Catégorie Directe (ex: Salaires) -->
                                    <?php if (!empty($r['categorie_nom'])): ?>
                                        <a href="<?= BASE_URL ?>router.php?p=categories_ressources.php&assign_to=<?= $ressourceId ?>"
                                            class="badge-dash-pill badge-cat-neon text-decoration-none" title="Changer la catégorie de cette ressource" style="
                                                                                    border-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>66;
                                                                                    background-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>11;
                                                                                    color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>;
                                                                                    box-shadow: 0 0 8px <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>33;
                                                                                ">
                                            📁 <?= htmlspecialchars($r['categorie_nom'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    <?php endif; ?>

                                    <!-- Badge Catégorie Parente (si existante, ex: MBS Gestion) -->
                                    <?php if ($aUneParente): ?>
                                        <a href="<?= BASE_URL ?>router.php?p=categories_ressources.php&assign_to=<?= $ressourceId ?>"
                                            class="badge-dash-pill badge-cat-neon text-decoration-none" title="Changer la catégorie de cette ressource" style="
                                                                                    border-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>66;
                                                                                    background-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>22;
                                                                                    color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>;
                                                                                    box-shadow: 0 0 8px <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>33;
                                                                                ">
                                            🏷️ <?= htmlspecialchars($r['categorie_parente_nom'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                    <?php endif; ?>

                                    <!-- Badge À Classer (si non classé) -->
                                    <?php if (empty($r['categorie_nom'])): ?>
                                                    <a href="<?= BASE_URL ?>router.php?p=categories_ressources.php&assign_to=<?= $ressourceId ?>"
                                            class="text-decoration-none" title="Cliquez pour classer cette ressource">
                                            <span class="badge badge-warning-pulse">
                                                ⚠️ À CLASSER
                                            </span>
                                        </a>
                                    <?php endif; ?>

                                    <!-- Badge Versements -->
                                    <a href="<?= BASE_URL ?>router.php?p=ressource_versements.php&id=<?= $ressourceId ?>"
                                        class="badge-neon badge-echeancier text-decoration-none">
                                        📅 Versements
                                    </a>
                                </div>
                            </td>
                            <td data-label="Organisme"><?= htmlspecialchars($r['organisme'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Total prévu" class="text-end"><?= number_format($totalPrevuRes, 2, ',', ' ') ?> €</td>
                            <td data-label="Perçu" class="text-end"><?= number_format($totalPercuRes, 2, ',', ' ') ?> €</td>
                            <td data-label="Date perçu" class="text-center">
                                <?= $r['derniere_date_percue'] ? date('d/m/Y', strtotime($r['derniere_date_percue'])) : '-' ?>
                            </td>
                            <td data-label="Reste attendu" class="text-end"><?= number_format($reste, 2, ',', ' ') ?> €</td>
                            <td data-label="Prochain versement" class="text-center">
                                <?= $r['prochaine_versement'] ? date('d/m/Y', strtotime($r['prochaine_versement'])) : '-' ?>
                            </td>
                            <td class="td-action text-center" data-label="Actions">
                                <!-- MODIFIER -->
                                <a href="<?= BASE_URL ?>router.php?p=modifier_ressource.php&id=<?= (int) $r['id'] ?>"
                                    class="btn-modifier-neon mb-2 d-inline-block">
                                    ✏️ Modifier
                                </a>
                                <!-- SUPPRIMER -->
                                <form method="POST" action="<?= BASE_URL ?>router.php?p=supprimer_ressource.php"
                                    onsubmit="return confirm('⚠️ Confirmer la suppression de cette ressource ?');">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <? csrf_input() ?>
                                    <button type="submit" class="btn btn-sm btn-danger btn-action-dash w-100">
                                        🗑️ Supprimer
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php
    if ($totalPages > 1):
        $baseUrl = BASE_URL . 'router.php?p=historique_ressources.php&search=' . urlencode($search) . '&categorie_filter=' . urlencode($categorieFilter) . '&date_filter=' . urlencode($dateFilter ?? '') . '&annee_filter=' . urlencode($anneeFilter);
        ?>
        <nav class="d-flex justify-content-center mt-4">
            <ul class="pagination pagination-sm">
                <li class="page-item <?= ($pageCourante <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $baseUrl ?>&page=1">«</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= ($i == $pageCourante) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $baseUrl ?>&page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($pageCourante >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $baseUrl ?>&page=<?= $totalPages ?>">»</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
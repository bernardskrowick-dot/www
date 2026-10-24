<?php
/* Version: v1.21.0 (Rev #4) - 2026-08-08 */
/**
 * dashboard_revenus.php - Partie PHP / Traitement et initialisation
 * 
 * RÈGLES APPLIQUÉES :
 * - Sécurité : Requêtes préparées PDO avec gestion des sous-catégories.
 * - Constantes : Utilisation de __DIR__ et BASE_URL.
 * - Commentaires : Documentation claire et sections numérotées.
 */

require_once __DIR__ . '/header.php';

// Vérification de la session utilisateur
$user_role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$aujourdhui = date('Y-m-d');

// -------------------------------------------------------------------------
// 1. RÉCUPÉRATION ET VALIDATION DES FILTRES
// -------------------------------------------------------------------------
$filtreOrganisme = !empty($_GET['organisme']) ? trim($_GET['organisme']) : '';
$filtreStatut = !empty($_GET['statut_filter']) ? trim($_GET['statut_filter']) : '';
if (!empty($filtreStatut) && !in_array($filtreStatut, ['attendu', 'percu', 'en_retard'], true)) {
    $filtreStatut = '';
}

$categorieFilter = !empty($_GET['categorie_filter']) ? (int) $_GET['categorie_filter'] : 0;
$anneeFilter = !empty($_GET['annee_filter']) ? trim($_GET['annee_filter']) : '';

// -------------------------------------------------------------------------
// 2. RÉCUPÉRATION DES ANNÉES DISPONIBLES POUR LE FILTRE
// -------------------------------------------------------------------------
$sqlAnnees = "
    SELECT DISTINCT YEAR(v.date_versement_prevue) AS annee
    FROM versements v
    INNER JOIN ressources r ON r.id = v.ressource_id
    WHERE r.user_id = :user_id AND v.date_versement_prevue IS NOT NULL
    ORDER BY annee DESC
";
$stmtAnnees = $pdo->prepare($sqlAnnees);
$stmtAnnees->execute(['user_id' => $user_id]);
$anneesDisponibles = $stmtAnnees->fetchAll(PDO::FETCH_COLUMN);

// -------------------------------------------------------------------------
// 3. REQUÊTE PRINCIPALE DES RESSOURCES (AVEC GESTION DES CATÉGORIES & PARENTS)
// -------------------------------------------------------------------------
$sql = "
SELECT
    r.*,
    c.nom AS nom_categorie,
    c.parent_id AS categorie_parent_id,
    parent_cat.nom AS nom_categorie_parente,
    c.couleur AS couleur_categorie,
    COUNT(v.id) AS nb_total_versements,
    SUM(
        CASE
            WHEN v.statut = 'percu'
            THEN 1
            ELSE 0
        END
    ) AS nb_percus,
    SUM(
        CASE
            WHEN v.statut <> 'percu'
                 OR v.statut IS NULL
            THEN 1
            ELSE 0
        END
    ) AS nb_restants,
    SUM(v.montant_prevu) AS total_prevu,
    SUM(
        CASE
            WHEN v.statut = 'percu'
            THEN COALESCE(
                v.montant_reel,
                v.montant_prevu
            )
            ELSE 0
        END
    ) AS total_percu,
    MIN(
        CASE
            WHEN v.statut <> 'percu'
            THEN v.date_versement_prevue
        END
    ) AS prochain_versement,
    (
        SELECT v2.montant_prevu
        FROM versements v2
        WHERE v2.ressource_id = r.id
          AND (v2.statut <> 'percu' OR v2.statut IS NULL)
        ORDER BY v2.date_versement_prevue ASC
        LIMIT 1
    ) AS prochain_montant_prevu
FROM ressources r
LEFT JOIN versements v ON r.id = v.ressource_id
LEFT JOIN categories_ressources c ON (
    r.categorie_id = c.id
    AND (
        c.user_id = r.user_id
        OR c.user_id IS NULL
    )
)
LEFT JOIN categories_ressources parent_cat ON c.parent_id = parent_cat.id
WHERE
    r.user_id = :user_id
    AND r.actif = 1
";

$params = [
    'user_id' => $user_id
];

// Application du filtre par catégorie (incluant les sous-catégories)
if ($categorieFilter > 0) {
    $sqlCats = "SELECT id FROM categories_ressources WHERE user_id = :cat_user_id AND (id = :cat_id OR parent_id = :cat_id)";
    $stmtCats = $pdo->prepare($sqlCats);
    $stmtCats->execute([
        'cat_user_id' => $user_id,
        'cat_id' => $categorieFilter
    ]);
    $idsCategories = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

    if (empty($idsCategories)) {
        $idsCategories = [$categorieFilter];
    }

    $placeholders = [];
    foreach ($idsCategories as $index => $catIdKey) {
        $ph = 'cat_sub_' . $index;
        $placeholders[] = ':' . $ph;
        $params[$ph] = $catIdKey;
    }
    $sql .= ' AND r.categorie_id IN (' . implode(', ', $placeholders) . ') ';
}

// Application du filtre Organisme
if ($filtreOrganisme !== '') {
    $sql .= ' AND r.organisme = :organisme ';
    $params['organisme'] = $filtreOrganisme;
}

// Application du filtre Année
if ($anneeFilter !== '') {
    $sql .= '
        AND EXISTS (
            SELECT 1 FROM versements v_sub 
            WHERE v_sub.ressource_id = r.id 
            AND YEAR(v_sub.date_versement_prevue) = :annee_ressource
        )
    ';
    $params['annee_ressource'] = $anneeFilter;
}

// Regroupement et Tri des résultats
$sql .= '
GROUP BY
    r.id,
    c.nom,
    c.parent_id,
    parent_cat.nom,
    c.couleur
ORDER BY
    prochain_versement ASC,
    r.titre ASC
';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ressources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtrage complémentaire par statut en PHP (aligné sur la logique des cartes)
if ($filtreStatut !== '') {
    $ressourcesFiltrees = [];
    foreach ($ressources as $ressource) {
        $resteAPercevoir = (float) $ressource['total_prevu'] - (float) $ressource['total_percu'];
        $statutRessource = 'attendu';

        if ($resteAPercevoir <= 0) {
            $statutRessource = 'percu';
        } elseif (!empty($ressource['prochain_versement']) && $ressource['prochain_versement'] < $aujourdhui) {
            $statutRessource = 'en_retard';
        }

        if ($statutRessource === $filtreStatut) {
            $ressourcesFiltrees[] = $ressource;
        }
    }
    $ressources = $ressourcesFiltrees;
}

// -------------------------------------------------------------------------
// 4. CALCULS GLOBAUX ET INITIALISATION DES DONNÉES RESSOURCES
// -------------------------------------------------------------------------
$totalMontantPrevu = 0;
$totalMontantPercu = 0;

foreach ($ressources as &$ressource) {
    $ressource['total_prevu'] = (float) ($ressource['total_prevu'] ?? 0);
    $ressource['total_percu'] = (float) ($ressource['total_percu'] ?? 0);
    $ressource['nb_total_versements'] = (int) ($ressource['nb_total_versements'] ?? 0);
    $ressource['nb_percus'] = (int) ($ressource['nb_percus'] ?? 0);
    $ressource['nb_restants'] = (int) ($ressource['nb_restants'] ?? 0);

    $ressource['reste_a_percevoir'] = max(0, $ressource['total_prevu'] - $ressource['total_percu']);
    $ressource['pourcentage'] = ($ressource['total_prevu'] > 0) ? min(100, ($ressource['total_percu'] / $ressource['total_prevu']) * 100) : 0;

    $totalMontantPrevu += $ressource['total_prevu'];
    $totalMontantPercu += $ressource['total_percu'];
}
unset($ressource);

// -------------------------------------------------------------------------
// 5. CHARGEMENT ET TRAITEMENT DES REVENUS MENSUELS
// -------------------------------------------------------------------------
$sqlRevenusMois = "
    SELECT 
        DATE_FORMAT(v.date_versement_prevue, '%Y-%m') AS mois,
        SUM(
            CASE 
                WHEN v.statut = 'percu'
                THEN COALESCE(v.montant_reel, v.montant_prevu)
                ELSE 0
            END
        ) AS revenu_percu,
        SUM(
            CASE 
                WHEN (v.statut = 'attendu' OR v.statut IS NULL OR v.statut = '')
                     AND v.date_versement_prevue >= :aujourdhui
                THEN v.montant_prevu
                ELSE 0
            END
        ) AS revenu_attendu,
        SUM(v.montant_prevu) AS revenu_total_prevu
    FROM versements v
    INNER JOIN ressources r ON r.id = v.ressource_id
    WHERE r.user_id = :user_id
";

$paramsRevenusMois = [
    'user_id' => $user_id,
    'aujourdhui' => $aujourdhui
];

// Application filtre catégorie pour les revenus mensuels
if ($categorieFilter > 0) {
    $sqlCatsMois = "SELECT id FROM categories_ressources WHERE user_id = :cat_user_id AND (id = :cat_id OR parent_id = :cat_id)";
    $stmtCatsMois = $pdo->prepare($sqlCatsMois);
    $stmtCatsMois->execute([
        'cat_user_id' => $user_id,
        'cat_id' => $categorieFilter
    ]);
    $idsCategoriesMois = $stmtCatsMois->fetchAll(PDO::FETCH_COLUMN);

    if (empty($idsCategoriesMois)) {
        $idsCategoriesMois = [$categorieFilter];
    }

    $placeholdersMois = [];
    foreach ($idsCategoriesMois as $indexMois => $catIdKeyMois) {
        $phMois = 'cat_mois_sub_' . $indexMois;
        $placeholdersMois[] = ':' . $phMois;
        $paramsRevenusMois[$phMois] = $catIdKeyMois;
    }
    $sqlRevenusMois .= ' AND r.categorie_id IN (' . implode(', ', $placeholdersMois) . ') ';
}

// Application filtre organisme pour les revenus mensuels
if ($filtreOrganisme !== '') {
    $sqlRevenusMois .= ' AND r.organisme = :organisme ';
    $paramsRevenusMois['organisme'] = $filtreOrganisme;
}

// Application filtre statut pour les revenus mensuels
if ($filtreStatut !== '') {
    if ($filtreStatut === 'percu') {
        $sqlRevenusMois .= " AND v.statut = 'percu' ";
    } elseif ($filtreStatut === 'attendu') {
        $sqlRevenusMois .= " AND (v.statut = 'attendu' OR v.statut IS NULL OR v.statut = '') AND v.date_versement_prevue >= :aujourdhui ";
    } elseif ($filtreStatut === 'en_retard') {
        $sqlRevenusMois .= " AND (v.statut != 'percu' OR v.statut IS NULL) AND v.date_versement_prevue < :aujourdhui ";
    }
}

// Application filtre année pour les revenus mensuels
if ($anneeFilter !== '') {
    $sqlRevenusMois .= ' AND YEAR(v.date_versement_prevue) = :annee_revenu ';
    $paramsRevenusMois['annee_revenu'] = $anneeFilter;
}

// Regroupement mensuel
$sqlRevenusMois .= ' GROUP BY mois ORDER BY mois ASC ';

$stmtRevenusMois = $pdo->prepare($sqlRevenusMois);
$stmtRevenusMois->execute($paramsRevenusMois);
$rows = $stmtRevenusMois->fetchAll(PDO::FETCH_ASSOC);

$revenusParMois = [];
$totalRevenusPrevu = 0;
$totalRevenusRecu = 0;
$nbVersementsAttendus = 0;
$totalRevenusAttendus = 0;

foreach ($rows as $r) {
    $mois = $r['mois'];
    $revenusParMois[$mois] = [
        'percu' => (float) $r['revenu_percu'],
        'attendu' => (float) $r['revenu_attendu'],
        'total' => (float) $r['revenu_total_prevu'],
    ];

    $totalRevenusPrevu += (float) $r['revenu_total_prevu'];
    $totalRevenusRecu += (float) $r['revenu_percu'];
    $totalRevenusAttendus += (float) $r['revenu_attendu'];

    if ((float) $r['revenu_attendu'] > 0) {
        $nbVersementsAttendus++;
    }
}

$totalPaye = 0;
$totalEcheances = 0;

$pourcentageRevenus = ($totalRevenusPrevu > 0)
    ? ($totalRevenusRecu / $totalRevenusPrevu) * 100
    : 0;

// -------------------------------------------------------------------------
// 6. CONFIGURATION DE LA PAGINATION
// -------------------------------------------------------------------------
$parPage = 6;
$pageCourante = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$totalRessourcesFiltrees = count($ressources);
$totalPages = max(1, ceil($totalRessourcesFiltrees / $parPage));

$parPageMois = 5;
$pageCouranteMois = isset($_GET['page_m']) ? max(1, (int) $_GET['page_m']) : 1;
$totalMois = count($revenusParMois);
$totalPagesMois = max(1, ceil($totalMois / $parPageMois));
?>

<!------  Fin des requêtes SQL ---->

<!------  Début d'affichage de la page  ---->
<div id="flash-container" class="container mt-4" style="position: relative; z-index: 9999;">
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success bg-success text-white border-0 shadow-lg p-3 rounded alert-dismissible fade show position-relative"
            role="alert">
            <div class="d-flex align-items-center">
                <span class="fs-4 me-2">✅</span>
                <div class="pe-5">
                    <strong>Succès !</strong><br>
                    <?= $_SESSION['flash_success']; ?>
                </div>
            </div> <button type="button" data-bs-dismiss="alert" class="btn-flash-close">
                ❌ FERMER
            </button>
        </div> <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger bg-danger text-white border-0 shadow-lg p-3 rounded" role="alert">
            <div class="d-flex align-items-center">
                <span class="fs-4 me-2">⚠️</span>
                <div>
                    <strong>Erreur !</strong><br>
                    <?= $_SESSION['flash_error']; ?>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
</div>

<!--****************************************************** -->
<!--** Section Menu Administrateur et Titre de la page  ** -->
<!--****************************************************** -->

<div class="container mb-4 nav-dashboard-container">
    <div class="glass-card-nav mt-4 mb-0">

        <div class="d-flex flex-wrap align-items-center mt-3">

        </div>

        <div class="p-3 pt-0">
            <?php
            $titreSurcharge = 'Tableau de Bord des revenus';
            // Utilisation de la constante globale définie dans init.php
            if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
                include DIR_LOGIC . 'top-bar-title-page.php';
            }
            ?>
        </div>
    </div>

    <!--******************************************* -->
    <!--** Fin de Section Admin titre            ** -->
    <!--******************************************* -->

    <!--******************************************* -->
    <!--** Section Menu Appel page     ** -->
    <!--******************************************* -->

    <div class="glass-card-nav  mb-2 align-items-center justify-content-center">

        <div class="d-flex justify-content-between align-items-center mt-2 mb-2">
            <div class="d-flex gap-3 flex-wrap">
                <a href="<?= BASE_URL ?>router.php?p=ajouter_ressource.php" class="btn btn-action-dash btn-dash-green px-3 text-nowrap">
                    <span class="emoji fs-4">💰</span> Ajouter un revenu
                </a>
                <a href="<?= BASE_URL ?>router.php?p=historique_ressources.php"
                    class="btn btn-action-dash btn-dash-green px-3 text-nowrap">
                    <span class="emoji fs-4">📜</span> Historique revenu
                </a>
                <a href="<?= BASE_URL ?>router.php?p=categories_ressources.php"
                    class="btn btn-action-dash btn-dash-indigo px-3 ">
                    <span class="emoji fs-4">📁</span> Catégories ressources
                </a>
                <a href="<?= BASE_URL ?>router.php?p=statistiques_revenus.php"
                    class="btn btn-action-dash btn-dash-indigo px-3">
                    <span class="emoji fs-4">📊</span> Statistiques ressources
                </a>
                <a href="<?= BASE_URL ?>index.php" class="btn btn-action-dash px-3" title="Retour au tableau de bord"
                    aria-label="Retour au tableau de bord">
                    <span class="emoji fs-4">🏠</span>
                    Retour Tableau de bord
                </a>
            </div>
        </div>
    </div>

    <?php
    /* */
    /** Section Compteurs Ressources Revenus */
    /* */

    // Nombre de ressources revenus
    $totalRessources = count($ressources);

    // Revenus restant à percevoir
    $totalRestant = max(
        0,
        $totalRevenusPrevu - $totalRevenusRecu
    );

    // Nombre total de versements
    $totalVersements = array_sum(
        array_map(
            fn($r) => $r['nb_total_versements'],
            $ressources
        )
    );

    ?>

    <!--******************************************* -->
    <!--** KPI REVENUS Compteurs Totaux           ** -->
    <!--******************************************* -->

    <div class="row mt-5 mb-5 text-center align-items-stretch">

        <!-- Montant prévu -->
        <div class="col-md-4 mb-4 mb-md-0">
            <div class="glass-card-nav  p-3 card-kpi h-100 d-flex flex-column justify-content-between">
                <div>
                    <h6 class="form-label">
                        <span class="emoji fs-4">💶</span> Montant prévu

                        <!-- Infobulle Montant prévu -->
                        <span class="info-tooltip ms-1 text-info fs-4">
                            ⓘ
                            <span class="info-tooltip-text">
                                Montant total de l'ensemble des revenus enregistrés ou estimés.
                                <br><br>
                                Calcul : somme globale des montants théoriques de toutes les ressources de revenus.
                            </span>
                        </span>
                    </h6>
                </div>
                <div class="my-auto">
                    <span class="badge-neon badge-neon-green fs-5">
                        <?= number_format($totalRevenusPrevu, 2) ?> €
                    </span>
                </div>
                <div></div> <!-- Élément vide pour équilibrer l'espace si besoin -->
            </div>
        </div>

        <!-- Total revenus perçus -->
        <div class="col-md-4 mb-4 mb-md-0">
            <div class="glass-card-nav  p-3 card-kpi h-100 d-flex flex-column justify-content-between">
                <div>
                    <h6 class="form-label fs-5">
                        <span class="emoji fs-4">💰</span> Total revenus perçus

                        <!-- Infobulle Total revenus perçus -->
                        <span class="info-tooltip ms-1 text-info fs-4">
                            ⓘ
                            <span class="info-tooltip-text">
                                Montant total des revenus effectivement encaissés à ce jour.
                                <br><br>
                                Calcul : somme de tous les versements réellement perçus et validés.
                            </span>
                        </span>
                    </h6>
                </div>

                <div class="my-auto">
                    <span class="badge-neon badge-neon-success fs-5">
                        <?= number_format($totalRevenusRecu, 2) ?> €
                    </span>
                </div>
                <div>
                    <small class="text-muted d-block mt-2">
                        Revenus prévus : <?= number_format($totalRevenusPrevu, 2) ?> € |
                        Versements en attente : <?= $nbVersementsAttendus ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Revenus à percevoir -->
        <div class="col-md-4">
            <div class="glass-card-nav  p-3 card-kpi h-100 d-flex flex-column justify-content-between">
                <div>
                    <h6 class="form-label">
                        <span class="emoji fs-4">⏳</span> Revenus à percevoir

                        <!-- Infobulle Revenus à percevoir -->
                        <span class="info-tooltip ms-1 text-info fs-4">
                            ⓘ
                            <span class="info-tooltip-text">
                                Solde des revenus restant à encaisser pour atteindre le total prévu.
                                <br><br>
                                Calcul : Montant prévu - Total revenus perçus (avec un minimum à 0 €).
                            </span>
                        </span>
                    </h6>
                </div>
                <div class="my-auto">
                    <span class="badge-neon badge-neon-orange fs-5">
                        <?= number_format($totalRestant, 2) ?> €
                    </span>
                </div>
                <div></div> <!-- Élément vide pour équilibrer l'espace si besoin -->
            </div>
        </div>

    </div>
    <!--******************************************* -->
    <!--** Fin de Section KPI REVENUS Compteurs Totaux ** -->
    <!--******************************************* -->
    <!--******************************************* -->
    <!--** Section Compteurs Badges Revenus      ** -->
    <!--******************************************* -->

    <?php

    // Nombre de sources de revenus
    $totalSourcesRevenus = count($ressources);

    // Nombre total de versements
    $totalVersementsRevenus = 0;

    // Revenus encore attendus
    $totalRevenusAttendus = 0;

    foreach ($ressources as $ressource) {
        $totalVersementsRevenus += (int) ($ressource['nb_total_versements'] ?? 0);

        $totalRevenusAttendus += max(
            0,
            ($ressource['total_prevu'] ?? 0)
                - ($ressource['total_percu'] ?? 0)
        );
    }
/* */
    /** Section Libellé dynamique KPI Catégorie */
    /* */

    $titreKpiCategorie = 'Total encaissé';

    if ($categorieFilter > 0) {
        $stmtCategorie = $pdo->prepare('
        SELECT nom
        FROM categories_ressources
        WHERE id = :id
        LIMIT 1
    ');

        $stmtCategorie->execute([
            'id' => $categorieFilter
        ]);

        $categorieSelectionnee = $stmtCategorie->fetch(PDO::FETCH_ASSOC);

        if ($categorieSelectionnee) {
            $titreKpiCategorie =
                'Total ' . $categorieSelectionnee['nom'];
        }
    }
    ?>


    <div class="row mb-4 text-center">


        <!-- Revenus actifs -->
        <div class="col-md-4">
            <div class="glass-card-nav  p-3 card-kpi h-100 d-flex flex-column justify-content-between">

                <h6 class="form-label">
                    <span class="emoji fs-4">💼</span> Sources de revenus

                    <!-- Infobulle Sources de revenus -->
                    <span class="info-tooltip ms-1 text-info fs-4">
                        ⓘ
                        <span class="info-tooltip-text">
                            Nombre total de ressources de revenus enregistrées.
                            <br><br>
                            Calcul : décompte de l'ensemble des sources de revenus configurées.
                        </span>
                    </span>
                </h6>

                <span class="badge-neon badge-neon-blue fs-5">
                    <?= $totalRessources ?>
                </span>

            </div>
        </div>

        <div class="col-md-4">

            <div class="glass-card-nav  p-3 card-kpi h-100 d-flex flex-column justify-content-between">

                <h6 class="form-label">
                    <span class="emoji fs-4">💰</span> <?= $titreKpiCategorie ?>

                    <!-- Infobulle Catégorie dynamique -->
                    <span class="info-tooltip ms-1 text-info fs-4">
                        ⓘ
                        <span class="info-tooltip-text">
                            Affichage contextuel selon le filtre de catégorie sélectionné.
                            <br><br>
                            Calcul : somme des montants perçus pour la catégorie active ou indication générale si toutes
                            catégories.
                        </span>
                    </span>
                </h6>

                <?php if ($categorieFilter > 0): ?>

                    <small class="text-muted d-block mt-2">
                        Total encaissé pour cette catégorie
                    </small>
                    <span class="badge-neon badge-neon-green fs-5">
                        <?= number_format($totalRevenusRecu, 2) ?> €
                    </span>
                <?php else: ?>

                    <small class="text-muted d-block mt-2">
                        Toutes catégories confondues
                    </small>
                    <span class="badge-neon badge-neon-green fs-5">
                        <?= number_format($totalRevenusRecu, 2) ?> €
                    </span>

                <?php endif; ?>

            </div>

        </div>

        <div class="col-md-4">

            <div class="glass-card-nav  p-3 card-kpi h-100 d-flex flex-column justify-content-between">

                <h6 class="form-label">
                    <span class="emoji fs-4">📅</span> Versements

                    <!-- Infobulle Versements -->
                    <span class="info-tooltip ms-1 text-info fs-4">
                        ⓘ
                        <span class="info-tooltip-text">
                            Nombre total d'échéances ou versements de revenus prévus.
                            <br><br>
                            Calcul : somme cumulée du nombre de versements de toutes les ressources.
                        </span>
                    </span>
                </h6>

                <span class="badge-neon badge-neon-blue fs-5">
                    <?= $totalVersementsRevenus ?>
                </span>

            </div>

        </div>

    </div>

    <!--******************************************* -->
    <!--** Fin Section Badge Compteurs Revenus   ** -->
    <!--******************************************* -->

    <!--******************************************* -->
    <!--** Section Progressions des revenus      ** -->
    <!--******************************************* -->

    <div class="row">
        <div class="col-12">
            <div class="form-label glass-card-nav p-4">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="glass-header-small mb-0">
                            <span class="emoji fs-4">💰</span> Avancement global des revenus

                            <!-- Infobulle Avancement global des revenus -->
                            <span class="info-tooltip ms-1 text-info fs-4">
                                ⓘ
                                <span class="info-tooltip-text">
                                    Pourcentage de réalisation du montant total des revenus prévus.
                                    <br><br>
                                    Calcul : (Total revenus perçus / Montant prévu) × 100.
                                </span>
                            </span>
                        </h2>
                    </div>

                    <div>
                        <span class="badge-neon badge-neon-blue badge-percent text-accent-blue fw-bold fs-5">
                            <?= number_format($pourcentageRevenus, 1) ?> %
                        </span>
                    </div>
                </div>

                <div class="progress progress-custom-dark">
                    <div class="progress-bar progress-bar-neon bg-neon-blue" role="progressbar"
                        style="width: <?= $pourcentageRevenus ?>%;" aria-valuenow="<?= $pourcentageRevenus ?>"
                        aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-2">
                    <small class="text-label-muted">Revenus prévus</small>
                    <small class="text-label-muted">100% encaissé</small>
                </div>

            </div>
        </div>
    </div>
    <!--******************************************* -->
    <!--** Fin Section Progression               ** -->
    <!--******************************************* -->

    <!--******************************************* -->
    <!--** Section Filtres                       ** -->
    <!--******************************************* -->
    <hr class="hr-glass" style="opacity:0.6;">

    <div class="row align-items-center justify-content-center mb-2">
        <!-- FORM : Utilisation de col-12 pour occuper toute la largeur du container -->
        <div class="col-12 align-items-center justify-content-center">
            <div class="card p-3 bg-dark text-white border-0 align-items-center justify-content-center">
                <form method="GET" action="<?= BASE_URL ?>router.php" class="row g-2 align-items-center w-100 m-0">

                    <input type="hidden" name="p" value="dashboard_revenus.php">
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
                    <!-- Organisme -->
                    <div class="col-md">
                        <label class="form-label text-white-50 mb-1 small">
                            <span class="emoji fs-4">🏢</span> Organisme
                        </label>
                        <select name="organisme" class="form-select bg-dark text-white border-secondary w-100">
                            <option value="">Tous les organismes</option>
                            <?php
                            $organismesStmt = $pdo->prepare('
                            SELECT DISTINCT organisme
                            FROM ressources
                            WHERE user_id = :user_id
                            ORDER BY organisme ASC
                        ');
                            $organismesStmt->execute(['user_id' => $user_id]);

                            foreach ($organismesStmt->fetchAll(PDO::FETCH_ASSOC) as $o):
                            ?>
                                <option value="<?= htmlspecialchars($o['organisme']) ?>"
                                    <?= (($filtreOrganisme ?? '') === $o['organisme']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($o['organisme']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Statut -->
                    <div class="col-md">
                        <label class="form-label text-white-50 mb-1 small">
                            <span class="emoji fs-4">📌</span> Statut
                        </label>
                        <select name="statut_filter" class="form-select bg-dark text-white border-secondary w-100">
                            <option value="">Tous les statuts</option>
                            <option value="attendu" <?= ($filtreStatut === 'attendu') ? 'selected' : '' ?>>
                                ⏳ Attendu
                            </option>
                            <option value="percu" <?= ($filtreStatut === 'percu') ? 'selected' : '' ?>>
                                ✅ Perçu
                            </option>
                            <option value="en_retard" <?= ($filtreStatut === 'en_retard') ? 'selected' : '' ?>>
                                ⚠️ En retard
                            </option>
                        </select>
                    </div>

                    <!-- Catégorie -->
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

                    <!-- Boutons -->
                    <div class="col-md-auto d-flex gap-2 align-self-end">
                        <!-- BOUTON FILTRER -->
                        <button type="submit"
                            class="btn-create-dash shadow-sm px-3 text-nowrap d-inline-flex align-items-center justify-content-center"
                            style="height: 38px;">
                            🔍 Filtrer
                        </button>

                        <!-- BOUTON Effacer -->
                        <a href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php"
                            class="btn-modifier-neon shadow-sm px-3 text-nowrap text-decoration-none d-inline-flex align-items-center justify-content-center"
                            style="height: 38px;"
                            title="Réinitialiser tous les filtres">
                            🔄 Effacer
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!--******************************************* -->
    <!--** Fin de Section filtres                ** -->
    <!--******************************************* -->


    <!-- ------------------------------------------------------------------------- -->
    <!-- 5. RENDU HTML / AFFICHAGE DES CARTES DE RESSOURCES                        -->
    <!-- ------------------------------------------------------------------------- -->
    <div class="row">
        <?php if (empty($ressources)): ?>
            <div class="col-12 text-center py-5">
                <div class="alert alert-light border shadow-sm">📭 Aucune ressource trouvée.</div>
            </div>
        <?php else: ?>
            <?php foreach ($ressources as $ressource): ?>
                <?php
                // Variables d'affichage pour la carte individuelle
                $pourcentageIndividuel = $ressource['pourcentage'];
                $isCompleted = ($pourcentageIndividuel >= 100);
                $resteAPercevoir = $ressource['reste_a_percevoir'];
                $prochainMontant = (float) ($ressource['prochain_montant_prevu'] ?? 0);

                // Détermination du statut et de la classe de bordure
                $statutRessource = 'attendu';
                $classeBordure = 'border-a-venir';
                $classeBadge = 'badge-marchand';

                if ($isCompleted) {
                    $statutRessource = 'percu';
                    $classeBordure = 'border-solde';
                    $classeBadge = 'badge-solde';
                } elseif (!empty($ressource['prochain_versement']) && $ressource['prochain_versement'] < $aujourdhui) {
                    $statutRessource = 'en_retard';
                    $classeBordure = 'border-en-retard';
                    $classeBadge = 'badge-warning-pulse';
                }
                ?>

                <div class="col-md-6 mb-5">
                    <div class="glass-card-nav h-100 border-2 <?= $classeBordure ?> card-achat-hover">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="text-start">
                                    <h5 class="text-white mb-3">
                                        <?= htmlspecialchars($ressource['titre'], ENT_QUOTES, 'UTF-8') ?>
                                    </h5>

                                    <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
                                        <!-- Badge Organisme -->
                                        <span class="badge-dash-pill badge-marchand">
                                            🏢 <?= htmlspecialchars($ressource['organisme'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                        </span>

                                        <?php
                                        // Gestion des badges de catégories (Principale & Sous-catégorie)
                                        $catColor = !empty($ressource['couleur_categorie']) ? $ressource['couleur_categorie'] : '#ffffff';
                                        $aUneParente = !empty($ressource['nom_categorie_parente']);
                                        $nomAffichage = $aUneParente ? $ressource['nom_categorie_parente'] : (!empty($ressource['nom_categorie']) ? $ressource['nom_categorie'] : 'Sans catégorie');
                                        $ressourceId = (int) $ressource['id'];
                                        ?>

                                        <!-- Badge Catégorie Principale -->
                                        <span class="badge-dash-pill badge-cat-neon" style="
                                        border-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>66;
                                                                            background-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>11;
                                                                            color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>;
                                                                            box-shadow: 0 0 8px <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>33;
                                                                        ">
                                            📁 <?= htmlspecialchars($nomAffichage, ENT_QUOTES, 'UTF-8') ?>
                                        </span>

                                        <!-- Badge Sous-Catégorie (si existante) -->
                                        <?php if ($aUneParente): ?>
                                            <span class="badge-dash-pill badge-cat-neon" style="
                                                                                border-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>66;
                                                                                background-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>22;
                                                                                color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>;
                                                                                box-shadow: 0 0 8px <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>33;
                                                                            ">
                                                🏷️ <?= htmlspecialchars($ressource['nom_categorie'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>

                                        <!-- Badge À Classer (si non classé) -->
                                        <?php if (empty($ressource['nom_categorie']) || $nomAffichage === 'Sans catégorie'): ?>
                                            <a href="<?= BASE_URL ?>router.php?p=modifier_ressource.php&id=<?= $ressourceId ?>" class="text-decoration-none"
                                                title="Cliquez pour classer cette ressource">
                                                <span class="badge badge-warning-pulse">
                                                    ⚠️ À CLASSER
                                                </span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Jauge de progression circulaire -->
                                <div class="d-flex flex-column align-items-center" style="min-width:100px;">
                                    <div class="progress-circle-custom <?= $isCompleted ? 'completed' : '' ?>"
                                        style="--percentage:<?= $pourcentageIndividuel ?>;">
                                        <span><?= number_format($pourcentageIndividuel, 0) ?>%</span>
                                    </div>
                                    <div class="text-center mt-2">
                                        <small class="text-muted d-block">Encaissement</small>
                                        <strong class="text-white"><?= $isCompleted ? '✅ Perçu' : '⏳ En attente' ?></strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Section Montants et Statistiques -->
                            <div class="mt-2 d-flex flex-wrap gap-2">
                                <span class="badge-marchand">
                                    💰 Prévu : <?= number_format($ressource['total_prevu'], 2, ',', ' ') ?> €
                                </span>
                                <span class="badge-solde">
                                    ✅ Perçu : <?= number_format($ressource['total_percu'], 2, ',', ' ') ?> €
                                </span>
                                <span class="<?= $classeBadge ?>">
                                    <?php if ($statutRessource === 'percu'): ?>
                                        ✅ TOTAL PERÇU
                                    <?php else: ?>
                                        ⏳ Reste : <?= number_format($resteAPercevoir, 2, ',', ' ') ?> €
                                    <?php endif; ?>
                                </span>
                                <?php if ($statutRessource !== 'percu' && $prochainMontant > 0): ?>
                                    <span class="badge-marchand">
                                        💳 Prochaine échéance : <?= number_format($prochainMontant, 2, ',', ' ') ?> €
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Section Versements et Échéances -->
                            <div class="mt-3 d-flex flex-wrap gap-2 justify-content-start">
                                <span class="badge-marchand">
                                    📅 Versements : <?= $ressource['nb_restants'] ?> / <?= $ressource['nb_total_versements'] ?>
                                </span>
                                <?php if (!empty($ressource['prochain_versement'])): ?>
                                    <span class="badge-warning-pulse">
                                        ⏳ Prochain : <?= date('d/m/Y', strtotime($ressource['prochain_versement'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Boutons d'actions -->
                            <div class="mt-4 d-flex flex-column flex-md-row gap-2 w-100">
                                <a href="<?= BASE_URL ?>router.php?p=modifier_ressource.php&id=<?= $ressourceId ?>" class="btn-pill-dash w-100"
                                    style="--btn-color:#ffc107;--btn-glow:rgba(255,193,7,.30);">
                                    ✏️ Modifier
                                </a>

                                <form method="POST" action="<?= BASE_URL ?>router.php?p=supprimer_ressource.php" class="w-100 m-0 p-0">
                                    <?php csrf_input(); ?>
                                    <input type="hidden" name="id" value="<?= $ressourceId ?>">
                                    <button type="submit" class="btn btn-sm btn-danger btn-action-dash w-100"
                                        onclick="return confirm('Supprimer cette ressource ?');">
                                        🗑️ Supprimer
                                    </button>
                                </form>

                                <a href="<?= BASE_URL ?>router.php?p=ressource_versements.php&id=<?= $ressourceId ?>" class="btn-pill-dash w-100"
                                    style="--btn-color:#00d2ff;--btn-glow:rgba(0,210,255,.30);">
                                    📅 Versements
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
/* */
    /** Fin Section Badges Ressources                 ** */
    /* */
    ?>

    <?php
    // Pagination //

    if ($totalPages > 1):
    ?>
        <nav class="d-flex flex-column align-items-center my-5">
            <ul class="pagination pagination-minimal">

                <li class="page-item <?= ($pageCourante <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php&organisme=<?= urlencode($filtreOrganisme) ?>&statut_filter=<?= urlencode($filtreStatut) ?>&categorie_filter=<?= $categorieFilter ?>&annee_filter=<?= urlencode($anneeFilter) ?>&page=<?= $pageCourante - 1 ?>">
                        <span>&lsaquo;</span>
                    </a>
                </li>

                <?php
                // Affichage des numéros de page
                for ($i = 1; $i <= $totalPages; $i++):
                    if ($i == 1 || $i == $totalPages || ($i >= $pageCourante - 1 && $i <= $pageCourante + 1)):
                ?>
                        <li class="page-item <?= ($i == $pageCourante ? 'active' : '') ?>">
                            <a class="page-link" href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php&organisme=<?= urlencode($filtreOrganisme) ?>&statut_filter=<?= urlencode($filtreStatut) ?>&categorie_filter=<?= $categorieFilter ?>&annee_filter=<?= urlencode($anneeFilter) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php elseif ($i == $pageCourante - 2 || $i == $pageCourante + 2): ?>
                        <li class="page-item disabled"><span class="page-link border-0" style="background:none !important;">...</span></li>
                <?php endif;
                endfor; ?>

                <li class="page-item <?= ($pageCourante >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php&organisme=<?= urlencode($filtreOrganisme) ?>&statut_filter=<?= urlencode($filtreStatut) ?>&categorie_filter=<?= $categorieFilter ?>&annee_filter=<?= urlencode($anneeFilter) ?>&page=<?= $pageCourante + 1 ?>">
                        <span>&rsaquo;</span>
                    </a>
                </li>
            </ul>

            <div class="text-muted mt-2" style="font-size: 0.7rem; letter-spacing: 2px;">
                AFFICHAGE DES REVENUS <?= (($pageCourante - 1) * $parPage) + 1 ?> À <?= min($pageCourante * $parPage, $totalRessourcesFiltrees) ?> SUR <?= $totalRessourcesFiltrees ?>
            </div>
        </nav>
    <?php endif; ?>
    <?php
    /**
     * Fin de Section Badges Revenus                        **
     */
    /* */
    ?>
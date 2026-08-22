<?php
/* Version: v1.19.1 (Rev #25) - 2026-08-06 */

/**
 * statistiques_revenus.php
 *
 * Tableau de bord statistique des revenus
 *
 * Fonctionnalités :
 * - Filtres dynamiques (année, organisme, catégorie)
 * - Indicateurs de performance (KPI)
 * - Graphiques Chart.js
 * - Synthèse des versements
 *
 * Développement :
 * - Requêtes PDO préparées
 * - Respect de la sécurité utilisateur
 * - Compatible avec l'architecture du projet
 */


/* ******************************************** */
/* ** SECTION 1 : SÉCURITÉ & INITIALISATION   ** */
/* ******************************************** */

if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$userId = $_SESSION['user_id'];

$aujourdhui = date('Y-m-d');


/* ******************************************** */
/* ** SECTION 2 : RÉCUPÉRATION DES FILTRES    ** */
/* ******************************************** */

$filtreAnnee = $_GET['annee'] ?? '';

$filtreOrganisme = $_GET['organisme'] ?? '';

$filtreCategorie = $_GET['categorie_filter'] ?? '';


/* ******************************************** */
/* ** SECTION 3 : CHARGEMENT DES FILTRES      ** */
/* ******************************************** */


/* ===== Liste des années disponibles ===== */

$sqlAnnees = '

SELECT DISTINCT
    YEAR(v.date_versement_prevue) AS annee

FROM versements v

INNER JOIN ressources r
        ON r.id = v.ressource_id

WHERE r.user_id = ?

ORDER BY annee DESC

';

$stmtA = $pdo->prepare($sqlAnnees);

$stmtA->execute([
    $userId
]);

$listeAnnees = $stmtA->fetchAll(PDO::FETCH_COLUMN);


/* ===== Liste des organismes ===== */

$sqlOrganismes = '

SELECT DISTINCT
    organisme

FROM ressources

WHERE user_id = ?

ORDER BY organisme ASC

';

$stmtO = $pdo->prepare($sqlOrganismes);

$stmtO->execute([
    $userId
]);

$listeOrganismes = $stmtO->fetchAll(PDO::FETCH_COLUMN);


/* ===== Liste des catégories de revenus ===== */

$sqlCategories = '

SELECT
    id,
    nom

FROM categories_ressources

WHERE
    user_id = :uid
    OR user_id IS NULL

ORDER BY nom ASC

';

$stmtC = $pdo->prepare($sqlCategories);

$stmtC->execute([
    'uid' => $userId
]);

$listeCategories = $stmtC->fetchAll(PDO::FETCH_ASSOC);

/* ******************************************** */
/* ** SECTION 5 : CONSTRUCTION DES FILTRES SQL** */
/* ******************************************** */


/* ===== Clause WHERE commune ===== */

$where = '

WHERE
    r.user_id = :uid

';

$params = [
    'uid' => $userId
];


/* ===== Filtre Année ===== */

if ($filtreAnnee) {

    $where .= '

    AND YEAR(v.date_versement_prevue) = :annee

    ';

    $params['annee'] = $filtreAnnee;
}


/* ===== Filtre Organisme ===== */

if ($filtreOrganisme) {

    $where .= '

    AND r.organisme = :organisme

    ';

    $params['organisme'] = $filtreOrganisme;
}


/* ===== Filtre Catégorie hiérarchique ===== */

if ($filtreCategorie) {


    /*
       Vérification si la catégorie sélectionnée
       possède des sous-catégories
    */

    $sqlSousCategories = "

    SELECT id

    FROM categories_ressources

    WHERE parent_id = :parent

    ";


    $stmtSousCategories = $pdo->prepare($sqlSousCategories);


    $stmtSousCategories->execute([
        'parent' => $filtreCategorie
    ]);


    $sousCategories = $stmtSousCategories->fetchAll(PDO::FETCH_COLUMN);



    /*
       Catégorie principale :
       on ajoute la catégorie + ses enfants
    */

    if (!empty($sousCategories)) {


        $categories = array_merge(
            [$filtreCategorie],
            $sousCategories
        );


    } else {


        /*
           Sous-catégorie :
           uniquement cette catégorie
        */

        $categories = [
            $filtreCategorie
        ];

    }



    /*
       Construction dynamique du IN()
       pour PDO
    */

    $placeholders = [];


    foreach ($categories as $index => $categorie) {


        $key = 'categorie_' . $index;


        $placeholders[] = ':' . $key;


        $params[$key] = $categorie;

    }



    $where .= '

    AND r.categorie_id IN (' . implode(',', $placeholders) . ')

    ';

}

/* ******************************************** */
/* ** SECTION 4 : KPI GLOBAUX DES REVENUS     ** */
/* ******************************************** */


/* ===== Total des revenus prévus ===== */

$sqlTotalPrevu = '

SELECT
    SUM(v.montant_prevu)

FROM versements v

INNER JOIN ressources r
        ON r.id = v.ressource_id

WHERE
    r.user_id = :uid

';

$stmtTotalPrevu = $pdo->prepare($sqlTotalPrevu);

$stmtTotalPrevu->execute([
    'uid' => $userId
]);

$totalRevenusPrevu = $stmtTotalPrevu->fetchColumn() ?: 0;


/* ===== Total des revenus encaissés ===== */

$sqlTotalPercu = "

SELECT
    SUM(
        CASE
            WHEN v.statut = 'percu'
            THEN COALESCE(v.montant_reel, v.montant_prevu)
            ELSE 0
        END
    )

FROM versements v

INNER JOIN ressources r
        ON r.id = v.ressource_id

WHERE
    r.user_id = :uid

";

$stmtTotalPercu = $pdo->prepare($sqlTotalPercu);

$stmtTotalPercu->execute([
    'uid' => $userId
]);

$totalRevenusPercus = $stmtTotalPercu->fetchColumn() ?: 0;


/* ===== Revenus restant à percevoir ===== */

$totalRevenusAttendus = max(
    0,
    $totalRevenusPrevu - $totalRevenusPercus
);


/* ===== Taux global d'encaissement ===== */

$tauxEncaissement = ($totalRevenusPrevu > 0)
    ? ($totalRevenusPercus / $totalRevenusPrevu) * 100
    : 0;


/* ===== Prochain versement attendu ===== */

$sqlProchain = "

SELECT
    MIN(v.date_versement_prevue)

FROM versements v

INNER JOIN ressources r
        ON r.id = v.ressource_id

WHERE
    r.user_id = :uid

AND
    v.statut <> 'percu'

";

$stmtProchain = $pdo->prepare($sqlProchain);

$stmtProchain->execute([
    'uid' => $userId
]);

$dateProchainRaw = $stmtProchain->fetchColumn();

$dateProchainVersement = $dateProchainRaw
    ? date('d/m/Y', strtotime($dateProchainRaw))
    : 'Aucun versement prévu';


/* ******************************************** */
/* ** SECTION KPI ANALYSE REVENUS              ** */
/* ******************************************** */


/* */
/** KPI 4 : Revenu moyen par versement */
/* */


$sqlRevenuMoyen = "

SELECT

    AVG(
        CASE
            WHEN v.statut = 'percu'
            THEN COALESCE(v.montant_reel, v.montant_prevu)
            ELSE NULL
        END
    ) AS revenu_moyen

FROM versements v

INNER JOIN ressources r
        ON r.id = v.ressource_id


$where


";


$stmtRevenuMoyen = $pdo->prepare($sqlRevenuMoyen);


$stmtRevenuMoyen->execute($params);


$revenuMoyen = $stmtRevenuMoyen->fetchColumn() ?: 0;



/* */
/** KPI 5 : Nombre de versements encaissés */
/* */


$sqlNbVersementsPercus = "

SELECT

    COUNT(*) AS nb_versements

FROM versements v

INNER JOIN ressources r
        ON r.id = v.ressource_id


$where


AND
    v.statut = 'percu'


";


$stmtNbVersementsPercus = $pdo->prepare($sqlNbVersementsPercus);


$stmtNbVersementsPercus->execute($params);


$nbVersementsPercus = $stmtNbVersementsPercus->fetchColumn() ?: 0;



/* */
/** KPI 6 : Nombre de versements en retard */
/* */


$sqlNbRetards = "

SELECT

    COUNT(*) AS nb_retards

FROM versements v

INNER JOIN ressources r
        ON r.id = v.ressource_id


$where


AND
    v.statut <> 'percu'


AND
    v.date_versement_prevue < CURDATE()


";


$stmtNbRetards = $pdo->prepare($sqlNbRetards);


$stmtNbRetards->execute($params);


$nbRetards = $stmtNbRetards->fetchColumn() ?: 0;



/* */
/** KPI 7 : Principal organisme financeur */
/* */


$sqlTopOrganisme = "

SELECT

    r.organisme,

    SUM(
        CASE
            WHEN v.statut = 'percu'
            THEN COALESCE(v.montant_reel, v.montant_prevu)
            ELSE 0
        END
    ) AS total


FROM versements v


INNER JOIN ressources r
        ON r.id = v.ressource_id


$where


GROUP BY
    r.organisme


ORDER BY
    total DESC


LIMIT 1


";


$stmtTopOrganisme = $pdo->prepare($sqlTopOrganisme);


$stmtTopOrganisme->execute($params);


$topOrganisme = $stmtTopOrganisme->fetch(PDO::FETCH_ASSOC);


$nomTopOrganisme = $topOrganisme['organisme'] ?? 'Aucun';


$totalTopOrganisme = $topOrganisme['total'] ?? 0;

 /* */
/** KPI 8 : Revenu mensuel moyen */
/* */


$sqlRevenuMensuelMoyen = "

SELECT

    AVG(total_mois) AS revenu_mensuel_moyen

FROM (

    SELECT

        DATE_FORMAT(v.date_versement_prevue, '%Y-%m') AS mois,


        SUM(
            CASE
                WHEN v.statut = 'percu'
                THEN COALESCE(v.montant_reel, v.montant_prevu)
                ELSE 0
            END
        ) AS total_mois


    FROM versements v


    INNER JOIN ressources r
            ON r.id = v.ressource_id


    $where


    GROUP BY
        DATE_FORMAT(v.date_versement_prevue, '%Y-%m')


) AS revenus_mensuels


";


$stmtRevenuMensuelMoyen = $pdo->prepare($sqlRevenuMensuelMoyen);


$stmtRevenuMensuelMoyen->execute($params);


$revenuMensuelMoyen = $stmtRevenuMensuelMoyen->fetchColumn() ?: 0;




/* */
/** KPI 9 : Nombre d'organismes actifs */
/* */


$sqlNbOrganismes = "

SELECT

    COUNT(DISTINCT r.organisme)


FROM ressources r


INNER JOIN versements v
        ON v.ressource_id = r.id



$where



";


$stmtNbOrganismes = $pdo->prepare($sqlNbOrganismes);


$stmtNbOrganismes->execute($params);


$nbOrganismes = $stmtNbOrganismes->fetchColumn() ?: 0;




/* */
/** KPI 10 : Prochain versement attendu */
/* */


$sqlProchainVersement = "

SELECT

    r.organisme,

    v.date_versement_prevue,

    v.montant_prevu


FROM versements v


INNER JOIN ressources r
        ON r.id = v.ressource_id



$where



AND

    v.statut = 'attendu'


ORDER BY

    v.date_versement_prevue ASC


LIMIT 1


";



$stmtProchainVersement = $pdo->prepare($sqlProchainVersement);



$stmtProchainVersement->execute($params);



$prochainVersement = $stmtProchainVersement->fetch(PDO::FETCH_ASSOC);



if ($prochainVersement) {


    $prochainOrganisme = $prochainVersement['organisme'];


    $prochaineDate = date(
        'd/m/Y',
        strtotime($prochainVersement['date_versement_prevue'])
    );


    $prochainMontant = $prochainVersement['montant_prevu'];


} else {


    $prochainOrganisme = '';

    $prochaineDate = '';

    $prochainMontant = 0;

}



/* ******************************************** */
/* ** SECTION 6 : DONNÉES DES GRAPHIQUES      ** */
/* ******************************************** */


/* ===== G1 : Répartition des revenus par catégorie ===== */

$dataG1 = $pdo->prepare("

SELECT

    c.nom,

    c.couleur,

    SUM(
        CASE
            WHEN v.statut = 'percu'
            THEN COALESCE(v.montant_reel, v.montant_prevu)
            ELSE 0
        END
    ) AS total

FROM versements v

INNER JOIN ressources r
        ON r.id = v.ressource_id

LEFT JOIN categories_ressources c
       ON r.categorie_id = c.id

$where

GROUP BY
    c.id,
    c.nom,
    c.couleur

ORDER BY total DESC

");

$dataG1->execute($params);

$resG1 = $dataG1->fetchAll(PDO::FETCH_ASSOC);


/* ===== G2 : Historique annuel des revenus ===== */

$dataG2 = $pdo->prepare("

SELECT

    YEAR(v.date_versement_prevue) AS annee,

    SUM(
        CASE
            WHEN v.statut IN ('percu','attendu')
            THEN COALESCE(v.montant_reel, v.montant_prevu)
            ELSE 0
        END
    ) AS total

FROM versements v

INNER JOIN ressources r
        ON r.id = v.ressource_id

WHERE
    r.user_id = :uid

" . ($filtreAnnee ? "AND YEAR(v.date_versement_prevue) = :annee" : "") . "

GROUP BY
    annee

ORDER BY
    annee ASC

");

$paramsG2 = [
    'uid' => $userId
];

if ($filtreAnnee) {
    $paramsG2['annee'] = $filtreAnnee;
}

$dataG2->execute($paramsG2);

$resG2 = $dataG2->fetchAll(PDO::FETCH_ASSOC);

/* ===== G3 : Évolution mensuelle des revenus ===== */

$dataG3 = $pdo->prepare("

SELECT

    DATE_FORMAT(
        v.date_versement_prevue,
        '%Y-%m'
    ) AS mois,

    SUM(
        CASE
    WHEN v.statut IN ('percu', 'attendu')
    THEN COALESCE(v.montant_reel, v.montant_prevu)
    ELSE 0
END
    ) AS total

FROM versements v

INNER JOIN ressources r
        ON r.id = v.ressource_id

$where

GROUP BY
    mois

ORDER BY
    mois ASC

");

$dataG3->execute($params);

$resG3 = $dataG3->fetchAll(PDO::FETCH_ASSOC);

$fluxMensuel = [];

for ($mois = 1; $mois <= 12; $mois++) {

    $cle = sprintf(
        '%d-%02d',
        $filtreAnnee,
        $mois
    );

    $fluxMensuel[$cle] = 0;
}

foreach ($resG3 as $ligne) {

    $fluxMensuel[$ligne['mois']] = (float)$ligne['total'];

}

$resG3 = [];

foreach ($fluxMensuel as $mois => $total) {

    $resG3[] = [
        'mois' => $mois,
        'total' => $total
    ];

}

/* ===== G4 : Top 5 des organismes ===== */

$dataG4 = $pdo->prepare("

SELECT

    r.organisme,

    SUM(
        CASE
            WHEN v.statut = 'percu'
            THEN COALESCE(v.montant_reel, v.montant_prevu)
            ELSE 0
        END
    ) AS total

FROM versements v

INNER JOIN ressources r
        ON r.id = v.ressource_id

$where

GROUP BY
    r.organisme

ORDER BY
    total DESC

LIMIT 5

");

$dataG4->execute($params);

$resG4 = $dataG4->fetchAll(PDO::FETCH_ASSOC);


/* ******************************************** */
/* ** SECTION 7 : AFFICHAGE DE LA PAGE        ** */
/* ******************************************** */

?>

<div>

    <!--******************************************* -->
    <!--** Section Titre de page                  ** -->
    <!--******************************************* -->
<div class="container mb-4 nav-dashboard-container">
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
        <hr class="hr-glass" style="opacity:0.6;">



        <!--******************************************* -->
        <!--** Section Retour Dashboard              ** -->
        <!--******************************************* -->
        
        <div class="text-left mt-0 mb-0">
            <a href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php" class="btn btn-secondary shadow-sm mt-0">
                <span class="emoji">💰</span>
                Retour Tableau de Bord Revenus
            </a>
        </div>
        <hr class="hr-glass" style="opacity:0.6;">

<!--******************************************* -->
<!--** Section KPI Revenus                     ** -->
<!--******************************************* -->

<div class="row mb-4">

    <div class="col-md-4 mb-3">
        <div class="form-label glass-card-nav p-4 border-start border-success border-4">

            <h6 class="small text-uppercase text-white-50 fw-bold">
               <span class="emoji fs-4">💰</span> Revenus encaissés

                <span class="info-tooltip ms-1 text-info fs-4">
                    ⓘ
                    <span class="info-tooltip-text">
                        Total des revenus effectivement perçus sur la période sélectionnée.
                        <br><br>
                        Ce montant correspond uniquement aux versements déjà encaissés.
                    </span>
                </span>
            </h6>

            <div class="display-5 fw-900 text-white">
                <?= number_format($totalRevenusPercus, 2, ',', ' ') ?> €
            </div>

        </div>
    </div>


    <div class="col-md-4 mb-3">
        <div class="form-label glass-card-nav p-4 border-start border-warning border-4">

            <h6 class="small text-uppercase text-white-50 fw-bold">
                <span class="emoji fs-4">⏳</span> Revenus attendus

                <span class="info-tooltip ms-1 text-info fs-4">
                    ⓘ
                    <span class="info-tooltip-text">
                        Somme des revenus restant à percevoir.
                        <br><br>
                        Calcul :
                        <br>
                        Montant prévu − Revenus déjà encaissés.
                    </span>
                </span>
            </h6>

            <div class="display-5 fw-900 text-warning">
                <?= number_format($totalRevenusAttendus, 2, ',', ' ') ?> €
            </div>

        </div>
    </div>


    <div class="col-md-4 mb-3">
        <div class="form-label glass-card-nav p-4 border-start border-info border-4">

            <h6 class="small text-uppercase text-white-50 fw-bold">
                <span class="emoji fs-4">📈</span> Taux encaissement

                <span class="info-tooltip ms-1 text-info fs-4">
                    ⓘ
                    <span class="info-tooltip-text">
                        Pourcentage des revenus déjà encaissés par rapport au montant total prévu.
                        <br><br>
                        Calcul :
                        <br>
                        (Revenus encaissés / Revenus prévus) × 100.
                    </span>
                </span>
            </h6>

            <div class="display-5 fw-900 text-info">
                <?= number_format($tauxEncaissement, 1) ?> %
            </div>

        </div>
    </div>
</div>

<!--******************************************* -->
<!--** KPI complémentaires revenus            ** -->
<!--******************************************* -->

<div class="row mb-4">


    <!-- KPI 4 : Revenu moyen par versement -->
    <div class="col-md-4 mb-3">
        <div class="form-label glass-card-nav p-4 border-start border-primary border-4">

            <h6 class="small text-uppercase text-white-50 fw-bold text-nowrap">
                <span class="emoji fs-4">📊</span> Revenu moyen par versement

                <span class="info-tooltip ms-1 text-info fs-4">
                    ⓘ
                    <span class="info-tooltip-text">
                        Montant moyen des revenus encaissés par versement.
                        <br><br>
                        Calcul :
                        <br>
                        Total revenus encaissés ÷ Nombre de versements encaissés.
                    </span>
                </span>
            </h6>

            <div class="display-5 fw-900 text-white">
                <?= number_format($revenuMoyen, 2, ',', ' ') ?> €
            </div>

        </div>
    </div>



    <!-- KPI 5 : Nombre de versements encaissés -->
    <div class="col-md-4 mb-3">
        <div class="form-label glass-card-nav p-4 border-start border-success border-4">

            <h6 class="small text-uppercase text-white-50 fw-bold">
                <span class="emoji fs-4">🔢</span> Versements encaissés

                <span class="info-tooltip ms-1 text-info fs-4">
                    ⓘ
                    <span class="info-tooltip-text">
                        Nombre total de versements dont le statut est encaissé.
                    </span>
                </span>
            </h6>

            <div class="display-5 fw-900 text-white">
                <?= number_format($nbVersementsPercus, 0, ',', ' ') ?>
            </div>

        </div>
    </div>



    <!-- KPI 6 : Retards -->
    <div class="col-md-4 mb-3">
        <div class="form-label glass-card-nav p-4 border-start border-danger border-4">

            <h6 class="small text-uppercase text-white-50 fw-bold">
                <span class="emoji fs-4">⏰</span> Versements en retard

                <span class="info-tooltip ms-1 text-info fs-4">
                    ⓘ
                    <span class="info-tooltip-text">
                        Nombre de versements prévus dont la date est dépassée
                        et qui ne sont pas encore encaissés.
                    </span>
                </span>
            </h6>

            <div class="display-5 fw-900 text-danger">
                <?= number_format($nbRetards, 0, ',', ' ') ?>
            </div>

        </div>
    </div>
</div>

<!--******************************************* -->
<!--** KPI Analyse des sources de revenus     ** -->
<!--******************************************* -->

<div class="row mb-4">


    <!-- KPI 7 : Principal organisme financeur -->
    <div class="col-md-4 mb-3">
        <div class="form-label glass-card-nav p-4 border-start border-info border-4">

            <h6 class="small text-uppercase text-white-50 fw-bold">
                <span class="emoji fs-4">🏢</span> Principal organisme

                <span class="info-tooltip ms-1 text-info fs-4">
                    ⓘ
                    <span class="info-tooltip-text">
                        Organisme ayant généré le plus de revenus encaissés.
                        <br><br>
                        Ce KPI permet d'identifier votre principale source de revenus.
                    </span>
                </span>
            </h6>

            <div class="fs-4 fw-900 text-white">
                <?= htmlspecialchars($nomTopOrganisme) ?>
            </div>

            <small class="text-white-50">
                <?= number_format($totalTopOrganisme, 2, ',', ' ') ?> €
            </small>

        </div>
    </div>



    <!-- KPI 8 : Revenu mensuel moyen -->
    <div class="col-md-4 mb-3">
        <div class="form-label glass-card-nav p-4 border-start border-primary border-4">

            <h6 class="small text-uppercase text-white-50 fw-bold">
                <span class="emoji fs-4">📅</span> Revenu mensuel moyen

                <span class="info-tooltip ms-1 text-info fs-4">
                    ⓘ
                    <span class="info-tooltip-text">
                        Moyenne des revenus encaissés chaque mois.
                        <br><br>
                        Calcul :
                        <br>
                        Total des revenus mensuels ÷ Nombre de mois actifs.
                    </span>
                </span>
            </h6>

            <div class="display-5 fw-900 text-white">
                <?= number_format($revenuMensuelMoyen, 2, ',', ' ') ?> €
            </div>

        </div>
    </div>



    <!-- KPI 9 : Organismes actifs -->
    <div class="col-md-4 mb-3">
        <div class="form-label glass-card-nav p-4 border-start border-warning border-4">

            <h6 class="small text-uppercase text-white-50 fw-bold">
                <span class="emoji fs-4">🏛️</span> Organismes actifs

                <span class="info-tooltip ms-1 text-info fs-4">
                    ⓘ
                    <span class="info-tooltip-text">
                        Nombre d'organismes différents ayant généré des revenus.
                        <br><br>
                        Permet de mesurer la diversification des sources de revenus.
                    </span>
                </span>
            </h6>

            <div class="display-5 fw-900 text-warning">
                <?= number_format($nbOrganismes, 0, ',', ' ') ?>
            </div>

        </div>
    </div>
</div>

<!--******************************************* -->
<!--** KPI Prochaine échéance revenu          ** -->
<!--******************************************* -->

<div class="row mb-4">

    <div class="col-md-4 mb-3">

        <div class="form-label glass-card-nav p-4 border-start border-success border-4">

            <h6 class="small text-uppercase text-white-50 fw-bold">

                <span class="emoji fs-4">📬</span> Prochain versement

                <span class="info-tooltip ms-1 text-info fs-4">
                    ⓘ
                    <span class="info-tooltip-text">
                        Prochain revenu attendu selon les échéances prévues.
                        <br><br>
                        Affiche le prochain versement non encore encaissé.
                    </span>
                </span>

            </h6>


            <?php if (!empty($prochainVersement)) : ?>


                <div class="fs-5 fw-900 text-white mb-2">
                    <?= htmlspecialchars($prochainOrganisme) ?>
                </div>


                <div class="text-white-50 small mb-2">
                   
                </div>


                <div class="d-flex justify-content-between align-items-center">

                    <span class="text-success fw-bold">
                        📅 <?= $prochaineDate ?>
                    </span>


                    <span class="text-white fw-bold">
                        <?= number_format($prochainMontant, 2, ',', ' ') ?> €
                    </span>

                </div>


            <?php else : ?>


                <div class="fs-5 fw-900 text-white">
                    Aucun versement prévu
                </div>


            <?php endif; ?>


        </div>

    </div>

</div>
        <!--******************************************* -->
        <!--** Section Filtres                         ** -->
        <!--******************************************* -->

        <div class="glass-card-nav p-4 mb-4">
            <form method="GET" action="router.php" class="row g-3 align-items-end">
                <input type="hidden" name="p" value="statistiques_revenus.php">

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-white-50">📅 ANNÉE</label>
                    <select name="annee" class="form-select bg-dark text-white border-secondary">
                        <option value="">Toutes</option>
                        <?php foreach ($listeAnnees as $ann): ?>
                            <option value="<?= $ann ?>" <?= ($filtreAnnee == $ann) ? 'selected' : '' ?>>
                                <?= $ann ?>
                            </option>
                        <?php endforeach; ?>
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
                    $catStmt->execute([$userId]);

                    foreach ($catStmt->fetchAll() as $c):
                        $nomCategorie = !empty($c['parent_id'])
                            ? '└── 🏷️ ' . $c['nom']
                            : '📁 ' . $c['nom'];
                        ?>
                        <option value="<?= $c['id'] ?>" <?= ($filtreCategorie == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nomCategorie) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-white-50">🏢 ORGANISME</label>
                    <select name="organisme" class="form-select bg-dark text-white border-secondary">
                        <option value="">Tous les organismes</option>
                        <?php foreach ($listeOrganismes as $o): ?>
                            <option value="<?= htmlspecialchars($o) ?>" <?= ($filtreOrganisme == $o) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($o) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end gap-2">
                    <!-- BOUTON FILTRER / ANALYSER -->
                    <button type="submit"
                        class="btn-create-dash shadow-sm px-3 d-inline-flex align-items-center justify-content-center w-100"
                        style="height: 38px;">
                        🔍 Analyser
                    </button>

                    <!-- BOUTON Effacer -->
                    <a href="<?= BASE_URL ?>router.php?p=statistiques_revenus.php"
                        class="btn-modifier-neon shadow-sm px-3 text-nowrap text-decoration-none d-inline-flex align-items-center justify-content-center w-100"
                        style="height: 38px;" title="Réinitialiser tous les filtres">
                        🔄 Effacer
                    </a>
                </div>
            </form>
        </div>


        <!--******************************************* -->
        <!--** Section Graphiques                     ** -->
        <!--******************************************* -->


        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="glass-card-nav p-4 h-100">
                    <h6 class="text-white-50 text-uppercase mb-3 small fw-bold">
                        Répartition catégories revenus
                    </h6>
                    <div style="height:300px">
                        <canvas id="chartG1"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="glass-card-nav p-4 h-100">
                    <h6 class="text-white-50 text-uppercase mb-3 small fw-bold">
                        Historique annuel revenus
                    </h6>
                    <div style="height:300px">
                        <canvas id="chartG2"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="glass-card-nav p-4 h-100">
                    <h6 class="text-white-50 text-uppercase mb-3 small fw-bold">
                        Flux mensuel revenus
                    </h6>
                    <div style="height:300px">
                        <canvas id="chartG3"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="glass-card-nav p-4 h-100">
                    <h6 class="text-white-50 text-uppercase mb-3 small fw-bold">
                        Top organismes
                    </h6>
                    <div style="height:300px">
                        <canvas id="chartG4"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>js/chart.umd.min.js?v=<?= time() ?>"></script>
<script src="<?= BASE_URL ?>js/chartjs-plugin-datalabels.min.js?v=<?= time() ?>"></script>

<script>

    window.onload = function () {

        if (typeof Chart === 'undefined') return;

        Chart.register(ChartDataLabels);

        const optionsBase = {

            responsive: true,

            maintainAspectRatio: false,


            plugins: {


                legend: {


                    position: 'bottom'


                },


                datalabels: {


                    color: '#FFFFFF',


                    font: { weight: 'bold' },


                    formatter: (value) => {


                        return value.toLocaleString() + ' €';

                    }
                }
            }
        };

        new Chart(document.getElementById('chartG1'), {

            type: 'doughnut',

            data: {

                labels: <?= json_encode(array_column($resG1, 'nom')) ?>,

                datasets: [{


                    data: <?= json_encode(array_column($resG1, 'total')) ?>,


                    backgroundColor: <?= json_encode(array_column($resG1, 'couleur')) ?>


                }]


            },


            options: optionsBase


        });


        new Chart(document.getElementById('chartG2'), {


            type: 'bar',


            data: {


                labels: <?= json_encode(array_column($resG2, 'annee')) ?>,


                datasets: [{


                    label: 'Revenus',


                    data: <?= json_encode(array_column($resG2, 'total')) ?>


                }]


            },


            options: optionsBase


        });





        new Chart(document.getElementById('chartG3'), {


            type: 'line',


            data: {


                labels: <?= json_encode(array_column($resG3, 'mois')) ?>,


                datasets: [{


                    label: 'Revenus',


                    data: <?= json_encode(array_column($resG3, 'total')) ?>


                }]


            },


            options: optionsBase


        });

        // --- G4 : Top 5 Organismes Revenus (Histogramme horizontal) ---

        new Chart(document.getElementById('chartG4'), {

            type: 'bar',

            data: {

                labels: <?= json_encode(array_column($resG4, 'organisme')) ?>,

                datasets: [{

                    label: 'Revenus perçus',

                    data: <?= json_encode(array_column($resG4, 'total')) ?>,

                    borderRadius: 5

                }]

            },

            options: {

                ...optionsBase,

                indexAxis: 'y',

                plugins: {

                    ...optionsBase.plugins,

                    legend: {

                        display: false

                    },

                    datalabels: {

                        anchor: 'end',

                        align: 'right',

                        color: '#FFFFFF',

                        font: {

                            weight: 'bold'

                        },

                        formatter: (value) => {

                            return value.toLocaleString() + ' €';

                        }

                    }

                },

                scales: {

                    x: {

                        beginAtZero: true

                    },

                    y: {

                        grid: {

                            display: false

                        }

                    }

                }

            }

        });
    };
</script>
<?php
/* Version: v1.21.0 (Rev #18) - 2026-08-08 */

/** echeance_mois.php - Vue mensuelle détaillée */

// 🔒 Sécurité session
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$aujourdhui = date('Y-m-d');

/* ******************************************** */
/* Jour de début du relevé bancaire */
/* ******************************************** */

// =====================================================
// RÉCUPÉRATION EN BDD & MAPPING DES ANCIENNES VARIABLES
// =====================================================

// 1. Requête SQL PDO pour extraire la configuration de l'utilisateur
$stmtUser = $pdo->prepare('
    SELECT solde_initial, premiere_periode, jour_debut_periode 
    FROM users 
    WHERE id = :id 
    LIMIT 1
');

$stmtUser->execute([
    'id' => $_SESSION['user_id']
]);

$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

// 2. Mapping : Correspondance Ancien Nom = Nouvelle Valeur BDD

$jourDebutPeriode = (int) $userData['jour_debut_periode'];
$premierePeriode  = (string) $userData['premiere_periode'];
$soldeInitial     = (float) $userData['solde_initial'];

// =======================================================
// FILTRES (RÉCUPÉRATION DES PARAMÈTRES)
// =======================================================

$mois = $_GET['mois'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
    $mois = date('Y-m');
}

$filtreMarchand = $_GET['marchand'] ?? '';

// Sécurisation du filtre catégorie pour les Ressources (Revenus)
$filtreCategorieRessource = isset($_GET['categorie_ressource']) && $_GET['categorie_ressource'] !== ''
    ? (int) $_GET['categorie_ressource']
    : '';

// Unified & fallback pour le filtre de catégorie d'achats
$filtreCategorie = '';
if (isset($_GET['categorie']) && $_GET['categorie'] !== '') {
    $filtreCategorie = (int) $_GET['categorie'];
} elseif (isset($_GET['categorie_achat']) && $_GET['categorie_achat'] !== '') {
    $filtreCategorie = (int) $_GET['categorie_achat'];
}

$filtreCategorieAchat = $filtreCategorie;  // Alignement pour rétrocompatibilité

// -------------------------------------------------------------------------
// ADAPTATION SOUS-CATÉGORIES : Récupération des IDs enfants si parent sélectionné
// -------------------------------------------------------------------------
$categoriesIncluses = [];
if ($filtreCategorie !== '') {
    $stmtCatSubs = $pdo->prepare('
        SELECT id 
        FROM categories 
        WHERE (id = :cat_id OR parent_id = :cat_id) 
          AND user_id = :user_id
    ');
    $stmtCatSubs->execute([
        'cat_id' => $filtreCategorie,
        'user_id' => $userId
    ]);
    $categoriesIncluses = $stmtCatSubs->fetchAll(PDO::FETCH_COLUMN);
}

$filtreStatut = $_GET['statut'] ?? '';

// =======================================================
// ACHATS
// =======================================================

$paramsAchats = [
    'mois' => $mois,
    'user_id' => $_SESSION['user_id'], // Utilisation directe et sécurisée de la session
    'jour_debut' => $jourDebutPeriode
];

// =========================================================================
// REQUÊTE SELECTION DES ÉCHÉANCES ET ACHATS AVEC CATÉGORIES
// =========================================================================

$queryAchats = "
SELECT
    echeances.id AS operation_id,
    echeances.id,
    echeances.achat_id,
    echeances.date_echeance AS date_operation,
    echeances.montant,
    echeances.statut,
    achats.nom_marchand,
    achats.titre,
    achats.categorie_id,
    
    -- RÉCUPÉRATION DE LA CATÉGORIE (Nom et Couleur)
    c.nom AS nom_categorie,
    c.parent_id AS categorie_parent_id,
    parent_cat.nom AS nom_categorie_parente,
    c.couleur AS couleur_categorie,
    
    'achat' AS type_operation
FROM echeances

-- Jointure obligatoire sur la table des achats
INNER JOIN achats
    ON echeances.achat_id = achats.id

-- JOINTURE OPTIONNELLE : Récupération des détails de la catégorie liée
LEFT JOIN categories c
    ON achats.categorie_id = c.id

-- JOINTURE OPTIONNELLE : Récupération du parent de la catégorie (si sous-catégorie)
LEFT JOIN categories parent_cat
    ON c.parent_id = parent_cat.id

-- FILTRES OBLIGATOIRES : Mois glissant et appartenance utilisateur
WHERE DATE_FORMAT(
        CASE
            WHEN :jour_debut > 1 AND DAY(echeances.date_echeance) >= :jour_debut
            THEN DATE_ADD(echeances.date_echeance, INTERVAL 1 MONTH)
            ELSE echeances.date_echeance
        END,
        '%Y-%m'
      ) = :mois
  AND achats.user_id = :user_id
";

// Préparation sécurisée avec PDO
$stmt = $pdo->prepare($queryAchats);

// Exécution avec le tableau de paramètres d'origine ($paramsAchats)
$stmt->execute($paramsAchats);

// Récupération des résultats sous forme de tableau associatif
$achats = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ******************************************** */
/* KPI Plus grosse dépense prévue de la période */
/* ******************************************** */

$queryPlusGrosseDepense = "
SELECT
    achats.nom_marchand,
    achats.titre,
    echeances.montant,
    echeances.date_echeance

FROM echeances

INNER JOIN achats
    ON echeances.achat_id = achats.id

WHERE DATE_FORMAT(
        CASE
            WHEN :jour_debut > 1 AND DAY(echeances.date_echeance) >= :jour_debut
            THEN DATE_ADD(echeances.date_echeance, INTERVAL 1 MONTH)
            ELSE echeances.date_echeance
        END,
        '%Y-%m'
      ) = :mois

AND achats.user_id = :user_id

ORDER BY echeances.montant DESC

LIMIT 1
";

$stmtPlusGrosseDepense = $pdo->prepare($queryPlusGrosseDepense);

$stmtPlusGrosseDepense->execute([
    'jour_debut' => $jourDebutPeriode,
    'mois' => $mois,
    'user_id' => $_SESSION['user_id']
]);

$plusGrosseDepense = $stmtPlusGrosseDepense->fetch(PDO::FETCH_ASSOC);

if ($plusGrosseDepense) {
    $nomPlusGrosseDepense = $plusGrosseDepense['nom_marchand'];
    $titrePlusGrosseDepense = $plusGrosseDepense['titre'];
    $montantPlusGrosseDepense = $plusGrosseDepense['montant'];
    $datePlusGrosseDepense = $plusGrosseDepense['date_echeance'];
} else {
    $nomPlusGrosseDepense = 'Aucune dépense';
    $titrePlusGrosseDepense = '';
    $montantPlusGrosseDepense = 0;
    $datePlusGrosseDepense = null;
}

// =======================================================
// VERSEMENTS (REVENUS)
// =======================================================

$paramsVersements = [
    'mois' => $mois,
    'user_id' => $_SESSION['user_id'],
    'jour_debut' => $jourDebutPeriode
];

$queryVersements = "
SELECT
    versements.id AS operation_id,
    versements.id,
    versements.ressource_id,
    versements.date_versement_prevue AS date_operation,
    versements.montant_prevu AS montant,
    versements.statut,
    ressources.organisme AS nom_marchand,
    ressources.titre,
    ressources.categorie_id AS categorie_id
FROM versements
LEFT JOIN ressources
    ON ressources.id = versements.ressource_id
WHERE DATE_FORMAT(
        CASE
            WHEN :jour_debut > 1 AND DAY(versements.date_versement_prevue) >= :jour_debut
            THEN DATE_ADD(versements.date_versement_prevue, INTERVAL 1 MONTH)
            ELSE versements.date_versement_prevue
        END,
        '%Y-%m'
      ) = :mois
  AND ressources.user_id = :user_id
";

$stmt = $pdo->prepare($queryVersements);
$stmt->execute($paramsVersements);

$versements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =======================================================
// NORMALISATION TYPES
// =======================================================

$achats = array_map(function ($a) {
    $a['type_operation'] = 'achat';
    return $a;
}, $achats);

$versements = array_map(function ($v) {
    $v['type_operation'] = 'ressource';
    return $v;
}, $versements);

// =======================================================
// MERGE & NORMALISATION DE LA CATÉGORIE
// =======================================================

$operations = array_merge($achats, $versements);
$operations = array_map(function ($op) {
    // Normalisation explicite de categorie_id
    if (isset($op['categorie_id']) && $op['categorie_id'] !== null && $op['categorie_id'] !== '') {
        $op['categorie_id'] = (int) $op['categorie_id'];
    } else {
        $op['categorie_id'] = null;
    }
    return $op;
}, $operations);

// =======================================================
// DUPLICATION : $operations (BRUT) vs $operationsFiltrees (FILTRÉ)
// =======================================================

$operationsFiltrees = array_values(array_filter($operations, function ($op) use ($filtreMarchand, $filtreCategorieRessource, $filtreCategorie, $categoriesIncluses, $filtreStatut, $aujourdhui) {
    // -------------------------
    // FILTRE MARCHAND
    // -------------------------
    if ($filtreMarchand !== '' && ($op['nom_marchand'] ?? '') !== $filtreMarchand) {
        return false;
    }

    // -------------------------
    // FILTRE CATÉGORIE (revenus uniquement)
    // -------------------------
    if ($filtreCategorieRessource !== '') {
        if (($op['type_operation'] ?? '') !== 'ressource') {
            return false;
        }

        if (!isset($op['categorie_id']) || $op['categorie_id'] === null) {
            return false;
        }

        if ((int) $op['categorie_id'] !== (int) $filtreCategorieRessource) {
            return false;
        }
    }

    // -------------------------
    // FILTRE CATÉGORIE (achats uniquement) - PARENT + SOUS-CATÉGORIES
    // -------------------------
    if ($filtreCategorie !== '') {
        if (($op['type_operation'] ?? '') !== 'achat') {
            return false;
        }

        if (!isset($op['categorie_id']) || $op['categorie_id'] === null) {
            return false;
        }

        if (!in_array((int) $op['categorie_id'], $categoriesIncluses, true)) {
            return false;
        }
    }

    // -------------------------
    // FILTRE STATUT
    // -------------------------
    if ($filtreStatut !== '') {
        $statut = $op['statut'] ?? '';
        $type = $op['type_operation'] ?? '';
        $dateOp = $op['date_operation'] ?? '';

        if ($filtreStatut === 'retard') {
            $estEnRetard = false;
            if ($type === 'ressource' && $statut === 'attendu' && $dateOp < $aujourdhui) {
                $estEnRetard = true;
            } elseif ($type === 'achat' && $statut === 'en_attente' && $dateOp < $aujourdhui) {
                $estEnRetard = true;
            }

            if (!$estEnRetard) {
                return false;
            }
        } elseif (in_array($filtreStatut, ['attendu', 'en_attente'])) {
            if ($type === 'ressource' && $statut !== 'attendu') {
                return false;
            }
            if ($type === 'achat' && $statut !== 'en_attente') {
                return false;
            }
        } elseif (in_array($filtreStatut, ['percu', 'payee'])) {
            if ($type === 'ressource' && $statut !== 'percu') {
                return false;
            }
            if ($type === 'achat' && $statut !== 'payee') {
                return false;
            }
        } else {
            if ($statut !== $filtreStatut) {
                return false;
            }
        }
    }

    return true;
}));

// =======================================================
// TRI DES TABLEAUX D'OPÉRATIONS
// =======================================================

usort($operations, function ($a, $b) {
    return strcmp($a['date_operation'], $b['date_operation']);
});

usort($operationsFiltrees, function ($a, $b) {
    return strcmp($a['date_operation'], $b['date_operation']);
});

/* ******************************************** */
/* Calcul du solde précédent de la période (NON FILTRÉ) */
/* ******************************************** */

$soldeMoisPrecedent = $soldeInitial;

$moisCourant = new DateTime($premierePeriode . '-01');
$moisCourant->modify('+1 month');
$moisFin = new DateTime($mois . '-01');
$moisFin->modify('-1 month');

while ($moisCourant <= $moisFin) {
    $cleMois = $moisCourant->format('Y-m');

    $revenusAvant = 0;
    $depensesAvant = 0;

    $stmtSolde = $pdo->prepare("
        SELECT
            SUM(e.montant) AS depenses
        FROM echeances e
        INNER JOIN achats a
            ON a.id = e.achat_id
        WHERE a.user_id = :user_id
        AND DATE_FORMAT(
            CASE
                WHEN :jour_debut > 1 AND DAY(e.date_echeance) >= :jour_debut
                THEN DATE_ADD(e.date_echeance, INTERVAL 1 MONTH)
                ELSE e.date_echeance
            END,
            '%Y-%m'
        ) = :mois
    ");

    $stmtSolde->execute([
        'user_id' => $_SESSION['user_id'],
        'jour_debut' => $jourDebutPeriode,
        'mois' => $cleMois
    ]);

    $depensesAvant = (float) ($stmtSolde->fetchColumn() ?? 0);

    $stmtRevenu = $pdo->prepare("
        SELECT SUM(
            CASE
                WHEN v.statut='percu'
                THEN COALESCE(v.montant_reel,v.montant_prevu)
                ELSE v.montant_prevu
            END
        )
        FROM versements v
        INNER JOIN ressources r
            ON r.id=v.ressource_id
        WHERE r.user_id=:user_id
        AND DATE_FORMAT(
            CASE
                WHEN :jour_debut > 1 AND DAY(v.date_versement_prevue) >= :jour_debut
                THEN DATE_ADD(v.date_versement_prevue, INTERVAL 1 MONTH)
                ELSE v.date_versement_prevue
            END,
            '%Y-%m'
        )=:mois
    ");

    $stmtRevenu->execute([
        'user_id' => $_SESSION['user_id'],
        'jour_debut' => $jourDebutPeriode,
        'mois' => $cleMois
    ]);

    $revenusAvant = (float) ($stmtRevenu->fetchColumn() ?? 0);

    $soldeMoisPrecedent += $revenusAvant - $depensesAvant;
    $moisCourant->modify('+1 month');
}

$moisPrecedent = date(
    'Y-m',
    strtotime($mois . '-01 -1 month')
);

// Utilisation du tableau global $operations pour le solde prévisionnel global
$totalRevenusMois = 0;
$totalDepensesMois = 0;

foreach ($operations as $op) {
    if ($op['type_operation'] === 'ressource') {
        $totalRevenusMois += (float) $op['montant'];
    } else {
        $totalDepensesMois += (float) $op['montant'];
    }
}

$soldeMois = $soldeMoisPrecedent
    + $totalRevenusMois
    - $totalDepensesMois;

/* ******************************************** */
/* KPI : Solde réel à ce jour (NON FILTRÉ) */
/* ******************************************** */

$soldeReel = $soldeMoisPrecedent;

foreach ($operations as $op) {
    $montant = (float) $op['montant'];
    $statutOp = $op['statut'] ?? '';

    if ($op['type_operation'] === 'ressource') {
        // Pour les versements : on intègre uniquement si le statut est 'percu'
        $montantReel = (isset($op['montant_reel']) && $op['montant_reel'] !== null) ? (float) $op['montant_reel'] : $montant;

        if ($statutOp === 'percu') {
            $soldeReel += $montantReel;
        }
    } else {
        // Pour les échéances : on intègre uniquement si le statut est 'payee'
        if ($statutOp === 'payee') {
            $soldeReel -= $montant;
        }
    }
}

// =======================================================
// KPI IMPACTÉS PAR LES FILTRES : 🛒 DÉPENSES
// =======================================================

$totalAchats = 0;
$totalAchatsPayes = 0;
$totalRessources = 0;
$totalRessourcesPercues = 0;

foreach ($operationsFiltrees as $op) {
    if ($op['type_operation'] === 'achat') {
        $totalAchats += (float) $op['montant'];

        if ($op['statut'] === 'payee') {
            $totalAchatsPayes += (float) $op['montant'];
        }
    } else {
        $totalRessources += (float) $op['montant'];

        if ($op['statut'] === 'percu') {
            $totalRessourcesPercues += (float) $op['montant'];
        }
    }
}

$resteAchats = $totalAchats - $totalAchatsPayes;
$resteRessources = $totalRessources - $totalRessourcesPercues;
$nbReste = 0;

foreach ($operationsFiltrees as $op) {
    if (
        $op['type_operation'] === 'achat' &&
        $op['statut'] !== 'payee'
    ) {
        $nbReste++;
    }
}

// =======================================================
// KPI IMPACTÉS PAR LES FILTRES : ⚡ ACTIVITÉ
// =======================================================

$nomMois = moisFrancais($mois);

$nbAchats = 0;
$nbRevenus = 0;

foreach ($operationsFiltrees as $op) {
    if ($op['type_operation'] === 'achat') {
        $nbAchats++;
    } else {
        $nbRevenus++;
    }
}

$totalOperationsFiltrees = count($operationsFiltrees);

$pourcentageAchats = $totalOperationsFiltrees > 0
    ? ($nbAchats / $totalOperationsFiltrees) * 100
    : 0;

$pourcentageRevenus = $totalOperationsFiltrees > 0
    ? ($nbRevenus / $totalOperationsFiltrees) * 100
    : 0;

/* ******************************************** */
/* Analyse dynamique du solde réel du jour */
/* ******************************************** */

if ($soldeReel < 0) {
    $classeSoldeReel = 'border-danger';
    $texteSoldeReel = '🔴 Attention découvert';
    $couleurTexteSolde = 'text-danger';
} elseif ($soldeReel < 200) {
    $classeSoldeReel = 'border-warning';
    $texteSoldeReel = '⚠️ Solde faible';
    $couleurTexteSolde = 'text-warning';
} else {
    $classeSoldeReel = 'border-success';
    $texteSoldeReel = '✅ Solde positif';
    $couleurTexteSolde = 'text-success';
}

/* ******************************************** */
/* Calcul écart prévisionnel */
/* ******************************************** */

$ecartPrevisionnel = $soldeMois - $soldeReel;

if ($ecartPrevisionnel < 0) {
    $texteEcart = '📉 Baisse prévue';
    $classeEcart = 'text-danger';
} elseif ($ecartPrevisionnel > 0) {
    $texteEcart = '📈 Amélioration prévue';
    $classeEcart = 'text-success';
} else {
    $texteEcart = '⚖️ Stable';
    $classeEcart = 'text-warning';
}

/* ******************************************** */
/* Calcul jours restants période bancaire      */
/* ******************************************** */

$dateFinRef = new DateTime($mois . '-01');

if ($jourDebutPeriode === 1) {
    $dateFinPeriode = (clone $dateFinRef)->modify('last day of this month');
} else {
    $dateFinPeriode = clone $dateFinRef;
    $dateFinPeriode->setDate(
        (int) $dateFinPeriode->format('Y'),
        (int) $dateFinPeriode->format('m'),
        min($jourDebutPeriode, (int) $dateFinPeriode->format('t'))
    );
    $dateFinPeriode->modify('-1 day');
}

// Force minuit sur les deux dates
$dateAujourdhui = new DateTime('today');
$dateFinPeriode->setTime(0, 0, 0);

if ($dateAujourdhui <= $dateFinPeriode) {
    // +1 pour inclure le jour actuel
    $joursRestants = $dateAujourdhui->diff($dateFinPeriode)->days + 1;
} else {
    $joursRestants = 0;
}

// On force les deux dates à minuit pour éviter les problèmes d'heure
$dateAujourdhui = new DateTime('today');
$dateFinPeriode->setTime(0, 0, 0);

if ($dateAujourdhui <= $dateFinPeriode) {
    $joursRestants = $dateAujourdhui->diff($dateFinPeriode)->days + 1;
} else {
    $joursRestants = 0;
}

/* ******************************************** */
/* Statut période bancaire                     */
/* ******************************************** */

if ($dateAujourdhui > $dateFinPeriode) {
    $texteJours = '⏳ Période échue';
    $classeJours = 'text-muted';
    $descriptionJours = 'relevé bancaire terminé';
} elseif ($joursRestants <= 5) {
    $texteJours = '⚠️ Fin de période proche';
    $classeJours = 'text-warning';
    $descriptionJours = 'avant le prochain relevé bancaire';
} elseif ($joursRestants <= 15) {
    $texteJours = '📅 Période en cours';
    $classeJours = 'text-info';
    $descriptionJours = 'avant le prochain relevé bancaire';
} else {
    $texteJours = '✅ Temps restant confortable';
    $classeJours = 'text-success';
    $descriptionJours = 'avant le prochain relevé bancaire';
}
/* ******************************************** */
/* Libellé plus grosse dépense */
/* ******************************************** */

$libellePlusGrosseDepense = !empty($titrePlusGrosseDepense)
    ? $titrePlusGrosseDepense
    : $nomPlusGrosseDepense;

/* ******************************************** */
/* Niveau d'importance de la dépense */
/* ******************************************** */

if ($montantPlusGrosseDepense >= 1000) {
    $niveauDepense = '🔴 Exceptionnelle';
    $classeDepense = 'text-danger';
} elseif ($montantPlusGrosseDepense >= 500) {
    $niveauDepense = '🟠 Très importante';
    $classeDepense = 'text-warning';
} elseif ($montantPlusGrosseDepense >= 300) {
    $niveauDepense = '🟡 Importante';
    $classeDepense = 'text-warning';
} elseif ($montantPlusGrosseDepense >= 100) {
    $niveauDepense = '🔵 Modérée';
    $classeDepense = 'text-info';
} else {
    $niveauDepense = '🟢 Faible';
    $classeDepense = 'text-success';
}

/* ******************************************** */
/* KPI - Tendance financière */
/* ******************************************** */

$variationPeriode = $soldeMois - $soldeMoisPrecedent;

if ($variationPeriode > 0) {
    $texteTendance = '📈 Situation favorable';
    $classeTendance = 'text-success';
} elseif ($variationPeriode < 0) {
    $texteTendance = '📉 À surveiller';
    $classeTendance = 'text-danger';
} else {
    $texteTendance = '➖ Situation stable';
    $classeTendance = 'text-info';
}

/* ******************************************** */
/* Description de la tendance */
/* ******************************************** */

if ($variationPeriode > 0) {
    $descriptionTendance = 'le solde progresse sur cette période';
} elseif ($variationPeriode < 0) {
    $descriptionTendance = 'le solde diminue sur cette période';
} else {
    $descriptionTendance = 'aucune évolution du solde';
}

// =======================================================
// COMPTAGES GLOBAUX POUR LA PÉRIODE (NON FILTRÉS)
// =======================================================

// 1. Nombre et total des versements attendus (revenus à venir)
$stmtNbVersements = $pdo->prepare("
    SELECT 
        COUNT(*) AS nb,
        COALESCE(SUM(v.montant_prevu), 0) AS total
    FROM versements v
    INNER JOIN ressources r ON r.id = v.ressource_id
    WHERE r.user_id = :user_id
      AND v.statut = 'attendu'
      AND DATE_FORMAT(
            CASE
                WHEN :jour_debut > 1 AND DAY(v.date_versement_prevue) >= :jour_debut
                THEN DATE_ADD(v.date_versement_prevue, INTERVAL 1 MONTH)
                ELSE v.date_versement_prevue
            END,
            '%Y-%m'
          ) = :mois
");
$stmtNbVersements->execute([
    'user_id' => $_SESSION['user_id'],
    'jour_debut' => $jourDebutPeriode,
    'mois' => $mois
]);
$kpiVersementsAttendus = $stmtNbVersements->fetch(PDO::FETCH_ASSOC);

$nbVersementsAttendus = (int) ($kpiVersementsAttendus['nb'] ?? 0);
$totalVersementsAttendus = (float) ($kpiVersementsAttendus['total'] ?? 0);

// 2. Nombre et total des paiements à venir (échéances d'achats non payées)
$stmtNbPaiements = $pdo->prepare("
    SELECT 
        COUNT(*) AS nb,
        COALESCE(SUM(e.montant), 0) AS total
    FROM echeances e
    INNER JOIN achats a ON a.id = e.achat_id
    WHERE a.user_id = :user_id
      AND e.statut = 'en_attente'
      AND DATE_FORMAT(
            CASE
                WHEN :jour_debut > 1 AND DAY(e.date_echeance) >= :jour_debut
                THEN DATE_ADD(e.date_echeance, INTERVAL 1 MONTH)
                ELSE e.date_echeance
            END,
            '%Y-%m'
          ) = :mois
");
$stmtNbPaiements->execute([
    'user_id' => $_SESSION['user_id'],
    'jour_debut' => $jourDebutPeriode,
    'mois' => $mois
]);
$kpiPaiementsAVenir = $stmtNbPaiements->fetch(PDO::FETCH_ASSOC);

$nbPaiementsAVenir = (int) ($kpiPaiementsAVenir['nb'] ?? 0);
$totalPaiementsAVenir = (float) ($kpiPaiementsAVenir['total'] ?? 0);
?>
<!-- ******************************************** -->
<!--  Fin des requête SQL -->
<!-- ******************************************** -->
<!-- ******************************************** -->
<!--  Début d'affichage de la page-->
<!-- ******************************************** -->
<div class="container mb-4 nav-dashboard-container">
    <div class="glass-card-nav p-1 mt-4 mb-4">

        <div class="d-flex flex-wrap align-items-center mt-3">
        </div>

        <div class="p-3 pt-0">

            <?php

            $titreSurcharge = 'Échéances du ' . periodeBancaire($mois, $jourDebutPeriode);


            if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
                include DIR_LOGIC . 'top-bar-title-page.php';
            }

            ?>

        </div>


        <hr class="hr-glass mt-4 mb-4" style="opacity:0.6;">

        <!--******************************************* -->
        <!--** Section Menu Appel page et Filtres    ** -->
        <!--******************************************* -->

        <div class="p-1 mt-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?= BASE_URL ?>router.php?p=ajouter_ressource.php" class="btn btn-action-dash btn-dash-green">
                        <span class="emoji fs-4">💰</span> Ajouter un revenu
                    </a>
                    <a href="<?= BASE_URL ?>router.php?p=historique_ressources.php"
                        class="btn btn-action-dash btn-dash-green">
                        <span class="emoji fs-4">📜</span> Historique revenu
                    </a>
                    <a href="<?= BASE_URL ?>router.php?p=categories_ressources.php"
                        class="btn btn-action-dash btn-dash-indigo">
                        <span class="emoji fs-4">📁</span>Catégories ressources
                    </a>
                    <a href="<?= BASE_URL ?>router.php?p=ajouter_achat.php" class="btn btn-action-dash btn-dash-green">
                        <span class="emoji fs-4">➕</span>Ajouter un achat
                    </a>

                    <a href="<?= BASE_URL ?>router.php?p=historique.php" class="btn btn-action-dash btn-dash-silver">
                        <span class="emoji fs-4">📜</span> Historique complet
                    </a>

                    <a href="<?= BASE_URL ?>router.php?p=marchands.php" class="btn btn-action-dash btn-dash-blue">
                        <span class="emoji fs-4">🏪</span> Mes Marchands
                    </a>

                    <a href="<?= BASE_URL ?>router.php?p=gestion_echeances.php&statut_filter=en_retard&marchand_filter=&tri_filter=titre"
                        class="btn btn-action-dash btn-dash-orange d-inline-flex align-items-center">
                        <span class="emoji fs-4">📅</span> Échéances
                        <?php if ($nbReste > 0): ?>
                            <span class="badge-neon ms-2"><?= $nbReste ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="<?= BASE_URL ?>router.php?p=statistiques.php" class="btn btn-action-dash btn-dash-blue">
                        <span class="emoji fs-4">📊</span> Statistiques
                    </a>

                    <a href="<?= BASE_URL ?>router.php?p=gestion_categories.php"
                        class="btn btn-action-dash btn-dash-indigo">
                        <span class="emoji fs-4">📁</span> Catégories
                    </a>
                    <a href="<?= BASE_URL ?>router.php?p=Tableau_mensuel.php" class="btn btn-action-dash btn-dash-indigo">
                        <span class="emoji fs-4">📁</span> Tableau mensuel
                    </a>
                    <a href="<?= BASE_URL ?>router.php?p=Tableau_mensuel.php<?= $filtreMarchand ? '?marchand=' . urlencode($filtreMarchand) : '' ?>"
                        class="btn btn-action-dash " title="Retour au tableau de bord"
                        aria-label="Retour au tableau de bord">

                        <span class="emoji fs-4">🏠</span>
                        Retour au Tableau de bord

                    </a>
                </div>
            </div>
            <hr class="hr-glass mt-4 mb-4" style="opacity:0.6;">
            <!-- Section : Affichage des indicateurs clés de performance (KPI) -->
            <div class="row g-3 mb-4 text-center">

                <!-- KPI : SOLDE PRÉCÉDENT -->
                <div class="col-12 col-md-4">
                    <div class="form-label glass-card-nav border-info p-3 h-100">

                        <small class="text-info fw-bold">
                            💳 SOLDE PRÉCÉDENT
                            <!-- Tooltip d'explication du solde précédent -->
                            <span class="info-tooltip ms-1 text-info fs-4">
                                ⓘ
                                <span class="info-tooltip-text">
                                    Solde final reporté du mois précédent.
                                    Calcul : point de départ financier pour calculer la projection du mois en cours.
                                </span>
                            </span>
                        </small>

                        <div class="fs-4 text-white fw-bold mt-2">
                            <?= number_format($soldeMoisPrecedent, 2, ',', ' ') ?> €
                        </div>

                    </div>
                </div>


                <!-- KPI : REVENUS -->
                <div class="col-12 col-md-4">
                    <div class="form-label glass-card-nav border-success p-3 h-100">

                        <small class="text-success fw-bold">
                            💰 REVENUS
                            <!-- Tooltip d'explication des revenus -->
                            <span class="info-tooltip ms-1 text-info fs-4">
                                ⓘ
                                <span class="info-tooltip-text">
                                    Total global des revenus prévus ou perçus sur la période selon les filtres actifs.
                                    Calcul : somme de toutes les ressources enregistrées pour ce mois.
                                </span>
                            </span>
                        </small>

                        <div class="fs-4 text-white fw-bold mt-2">
                            <?= number_format($totalRessources, 2, ',', ' ') ?> €
                        </div>

                    </div>
                </div>


                <!-- KPI : DÉPENSES -->
                <div class="col-12 col-md-4">
                    <div class="form-label glass-card-nav border-danger p-3 h-100">

                        <small class="text-danger fw-bold">
                            🛒 DÉPENSES
                            <!-- Tooltip d'explication des dépenses -->
                            <span class="info-tooltip ms-1 text-info fs-4">
                                ⓘ
                                <span class="info-tooltip-text">
                                    Total global des dépenses prévues ou réglées sur la période selon les filtres actifs.
                                    Calcul : somme de toutes les échéances d'achats du mois.
                                </span>
                            </span>
                        </small>

                        <div class="fs-4 text-white fw-bold mt-2">
                            <?= number_format($totalAchats, 2, ',', ' ') ?> €
                        </div>

                    </div>
                </div>


                <!-- KPI : SOLDE FIN DE PÉRIODE (PRÉVISIONNEL) -->
                <div class="col-12 col-md-4">
                    <div class="form-label  glass-card-nav border-warning p-3 h-100">

                        <small class="text-warning fw-bold">
                            🏦 SOLDE FIN DE PÉRIODE
                            <!-- Tooltip d'explication du solde de fin de période -->
                            <span class="info-tooltip ms-1 text-info fs-4">
                                ⓘ
                                <span class="info-tooltip-text">
                                    Estimation du solde disponible au dernier jour du mois.
                                    Calcul : Solde Précédent + Total Revenus - Total Dépenses.
                                </span>
                            </span>
                        </small>

                        <div class="fs-3 text-white fw-bold mt-2">
                            <?= number_format($soldeMois, 2, ',', ' ') ?> €
                        </div>
                        <small class="text-muted">
                            prévision après tous les prélèvements et revenus à venir
                        </small>

                    </div>
                </div>


                <!-- KPI : SOLDE RÉEL DU JOUR -->
                <div class="col-12 col-md-4">

                    <div class=" form-label glass-card-nav <?= $classeSoldeReel ?> p-3 h-100">

                        <small class="<?= $couleurTexteSolde ?> fw-bold">
                            💳 SOLDE RÉEL DU JOUR
                            <!-- Tooltip d'explication du solde réel -->
                            <span class="info-tooltip ms-1 text-info fs-4">
                                ⓘ
                                <span class="info-tooltip-text">
                                    État théorique de votre compte à la date d'aujourd'hui.
                                    Calcul : Solde Précédent + Revenus perçus/dus jusqu'à ce jour - Dépenses réglées/dues
                                    jusqu'à ce jour.
                                </span>
                            </span>
                        </small>

                        <div class="fs-3 text-white fw-bold mt-2">
                            <?= number_format($soldeReel, 2, ',', ' ') ?> €
                        </div>


                        <small class="<?= $couleurTexteSolde ?> fw-bold mt-2">
                            <?= $texteSoldeReel ?>
                        </small>


                        <small class="text-muted mt-1">
                            opérations jusqu'à aujourd'hui
                        </small>


                    </div>

                </div>


                <!-- KPI : ÉCART PRÉVISIONNEL -->
                <div class="col-12 col-md-4">

                    <div class="form-label glass-card-nav border-info p-3 h-100">

                        <small class="text-info fw-bold">
                            📉 ÉCART PRÉVISIONNEL
                            <!-- Tooltip d'explication de l'écart prévisionnel -->
                            <span class="info-tooltip ms-1 text-info fs-4">
                                ⓘ
                                <span class="info-tooltip-text">
                                    Évolution estimée de votre trésorerie d'ici la fin de la période.<br><br>
                                    Calcul : Solde prévisionnel de fin de mois - Solde réel actuel.<br><br>
                                    📈 <strong>Positif</strong> : Votre solde va augmenter d'ici la fin du mois.<br><br>
                                    📉 <strong>Négatif</strong> : Votre solde va diminuer d'ici la fin du mois.
                                </span>
                            </span>
                        </small>

                        <div class="fs-3 fw-bold mt-2"
                            style="color: <?= $ecartPrevisionnel >= 0 ? '#198754' : '#dc3545' ?> !important;">
                            <?= $ecartPrevisionnel >= 0 ? '+' : '' ?><?= number_format($ecartPrevisionnel, 2, ',', ' ') ?> €
                        </div>

                        <small class="<?= $classeEcart ?> fw-bold mt-2">
                            <?= htmlspecialchars($texteEcart, ENT_QUOTES, 'UTF-8'); ?>
                        </small>

                    </div>

                </div>
                <!-- KPI : JOURS RESTANTS DANS LA PÉRIODE -->
                <div class="col-12 col-md-4">

                    <div class="form-label glass-card-nav border-primary p-3 h-100">

                        <small class="text-primary fw-bold">
                            📅 JOURS RESTANTS
                            <!-- Tooltip d'explication du décompte des jours -->
                            <span class="info-tooltip ms-1 text-info fs-4">
                                ⓘ
                                <span class="info-tooltip-text">
                                    Nombre de jours séparant la date du jour de la fin de la période bancaire.
                                </span>
                            </span>
                        </small>


                        <div class="fs-3 text-white fw-bold mt-2">

                            <?= $joursRestants ?>

                        </div>


                        <small class="<?= $classeJours ?> fw-bold mt-2">

                            <?= $texteJours ?>

                        </small>


                        <small class="text-muted mt-1">
                            <?= $descriptionJours ?>
                        </small>

                    </div>

                </div>

                <!-- KPI : PLUS GROSSE DÉPENSE DU MOIS -->
                <div class="col-12 col-md-4">

                    <div class="form-label glass-card-nav border-danger p-3 h-100">

                        <small class="text-danger fw-bold">
                            🔥 PLUS GROSSE DÉPENSE
                            <!-- Tooltip d'explication de la plus grosse dépense -->
                            <span class="info-tooltip ms-1 text-info fs-4">
                                ⓘ
                                <span class="info-tooltip-text">
                                    Dépense unique au montant le plus élevé parmi les échéances de la période en cours.
                                </span>
                            </span>
                        </small>

                        <div class="fs-5 text-white fw-bold mt-2">

                            <?= htmlspecialchars(
                                !empty($titrePlusGrosseDepense)
                                    ? $titrePlusGrosseDepense
                                    : $nomPlusGrosseDepense
                            ) ?>

                        </div>

                        <?php if (!empty($titrePlusGrosseDepense)): ?>

                            <small class="text-muted d-block">
                                <?= htmlspecialchars($nomPlusGrosseDepense) ?>
                            </small>

                        <?php endif; ?>

                        <div class="fs-3 text-danger fw-bold mt-3">
                            <?= number_format($montantPlusGrosseDepense, 2, ',', ' ') ?> €
                        </div>

                        <small class="<?= $classeDepense ?> fw-bold d-block mt-2">
                            <?= $niveauDepense ?>
                        </small>

                        <?php if (!empty($datePlusGrosseDepense)): ?>

                            <small class="text-muted">

                                prévue le
                                <?= date('d/m/Y', strtotime($datePlusGrosseDepense)) ?>

                            </small>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- KPI : TENDANCE FINANCIÈRE DE LA PÉRIODE -->
                <div class="col-12 col-md-4">

                    <div class="form-label glass-card-nav border-primary p-3 h-100">

                        <small class="text-primary fw-bold">
                            📊 TENDANCE
                            <!-- Tooltip d'explication de la tendance -->
                            <span class="info-tooltip ms-1 text-info fs-4">
                                ⓘ
                                <span class="info-tooltip-text">
                                    Évolution globale du solde par rapport au mois précédent (prend en compte le report de trésorerie).
                                    Calcul : Solde final de la période - Solde final du mois précédent.
                                </span>
                            </span>
                        </small>

                        <div class="fs-3 fw-bold mt-2 <?= $classeTendance ?>">

                            <?= ($variationPeriode >= 0 ? '+' : '')
                                . number_format($variationPeriode, 2, ',', ' ') ?> €

                        </div>

                        <small class="<?= $classeTendance ?> fw-bold d-block mt-2">
                            <?= $texteTendance ?>
                        </small>

                        <small class="text-muted">
                            <?= $descriptionTendance ?>
                        </small>

                    </div>

                </div>

                <!-- KPI : VERSEMENTS ATTENDUS (REVENUS NON ENCAISSÉS) -->
                <div class="col-12 col-md-4">
                    <div class="form-label  glass-card-nav border-success p-3 h-100">

                        <small class="text-success fw-bold">
                            📥 VERSEMENTS ATTENDUS
                            <!-- Tooltip d'explication des versements attendus -->
                            <span class="info-tooltip ms-1 text-info fs-4">
                                ⓘ
                                <span class="info-tooltip-text">
                                    Somme des ressources dont le statut n'est pas encore marqué comme 'perçu'.
                                    Calcul : total des revenus en attente d'encaissement.
                                </span>
                            </span>
                        </small>

                        <div class="fs-3 text-white fw-bold mt-2">
                            <?= number_format($totalVersementsAttendus, 2, ',', ' ') ?> €
                        </div>

                        <small class="text-success fw-bold mt-2">
                            <?= $nbVersementsAttendus ?> versement(s) en attente
                        </small>

                        <small class="text-muted mt-1">
                            revenus à encaisser sur la période
                        </small>

                    </div>
                </div>


                <!-- KPI : PAIEMENTS À VENIR (DÉPENSES NON RÉGLÉES) -->
                <div class="col-12 col-md-4">
                    <div class="form-label glass-card-nav border-danger p-3 h-100">

                        <small class="fw-bold">
                            <span class="text-danger">📤 PAIEMENTS À VENIR</span>
                            <!-- Tooltip d'explication des paiements à venir -->
                            <span class="info-tooltip ms-1 text-info fs-4">
                                ⓘ
                                <span class="info-tooltip-text">
                                    Somme des échéances d'achats dont le statut n'est pas encore 'payée'.
                                    Calcul : total des prélèvements et dépenses restant à régler.
                                </span>
                            </span>
                        </small>

                        <div class="fs-3 text-white fw-bold mt-2">
                            <?= number_format($totalPaiementsAVenir, 2, ',', ' ') ?> €
                        </div>

                        <small class="fw-bold mt-2 d-block">
                            <span class="text-danger"><?= $nbPaiementsAVenir ?> échéance(s) à régler</span>
                        </small>

                        <small class="text-muted mt-1 d-block">
                            prélèvements/dépenses à venir
                        </small>

                    </div>
                </div>


                <!-- KPI : ACTIVITÉ ET RÉPARTITION DES OPÉRATIONS -->
                <div class="col-12 col-md-4">
                    <div class="form-label glass-card-nav border-primary p-3 h-100">

                        <small class="text-primary fw-bold">
                            ⚡ ACTIVITÉ
                            <!-- Tooltip d'explication du volume d'activité -->
                            <span class="info-tooltip ms-1 text-info fs-4">
                                ⓘ
                                <span class="info-tooltip-text">
                                    Volume d'opérations sur la période et répartition en pourcentage entre achats et
                                    revenus.
                                </span>
                            </span>
                        </small>

                        <div class="fs-4 text-white fw-bold mt-2">
                            <?= count($operations) ?>
                        </div>

                        <small class="text-muted">
                            mouvements ce mois
                        </small>


                        <div class="small mt-2">

                            <span class="text-danger">
                                🛒 <?= $nbAchats ?> achats
                            </span>

                            <span class="text-muted mx-1">
                                |
                            </span>

                            <span class="text-success">
                                💰 <?= $nbRevenus ?> revenus
                            </span>

                        </div>


                        <!-- Barre de répartition de l'activité -->
                        <div class="progress mt-3" style="height:6px;background:#222;">

                            <div class="progress-bar bg-danger" style="width:<?= $pourcentageAchats ?>%">
                            </div>

                            <div class="progress-bar bg-success" style="width:<?= $pourcentageRevenus ?>%">
                            </div>

                        </div>

                    </div>
                </div>

            </div>
            <!-- FILTRES -->
            <div class="mt-4 mb-4 d-flex justify-content-between">
            </div>

            <!-- Formulaire de filtres sécurisé -->
            <form method="GET" action="<?= BASE_URL ?>router.php"
                class="d-flex justify-content-center align-items-end gap-2 flex-wrap flex-nowrap-md w-100">

                <!-- Page active pour le routeur -->
                <input type="hidden" name="p" value="echeances_mois.php">

                <!-- MOIS -->
                <div class="d-flex flex-column">
                    <label class="form-label text-white-50 small mb-1">📅 Mois</label>
                    <input type="month" name="mois" class="form-control bg-dark text-white border-secondary"
                        style="max-width: 160px;" value="<?= htmlspecialchars($mois) ?>">
                </div>

                <!-- MARCHAND -->
                <div class="d-flex flex-column">
                    <label class="form-label text-white-50 small mb-1">🏪 Marchand</label>
                    <select name="marchand" class="form-select bg-dark text-white border-secondary"
                        style="max-width: 190px;">
                        <option value="">Tous les marchands</option>

                        <?php
                        // Récupération sécurisée des marchands de l'utilisateur
                        $stmtM = $pdo->prepare('
                SELECT DISTINCT nom_marchand
                FROM achats
                WHERE user_id = :user_id
                ORDER BY nom_marchand ASC
            ');
                        $stmtM->execute(['user_id' => $userId]);

                        foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $m):
                        ?>
                            <option value="<?= htmlspecialchars($m['nom_marchand']) ?>"
                                <?= ($filtreMarchand === $m['nom_marchand']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nom_marchand']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- STATUT -->
                <div class="d-flex flex-column">
                    <label class="form-label text-white-50 small mb-1">📌 Statut</label>
                    <select name="statut" class="form-select bg-dark text-white border-secondary" style="max-width: 150px;">
                        <option value="">Tous statuts</option>
                        <!-- CORRECTION : value="payee" pour correspondre au traitement PHP -->
                        <option value="payee" <?= ($filtreStatut === 'payee') ? 'selected' : '' ?>>✅ Payé / Perçu</option>
                        <!-- CORRECTION : value="attendu" (était "attente") pour aligner value et sélection -->
                        <option value="attendu" <?= ($filtreStatut === 'attendu') ? 'selected' : '' ?>>⏳ À venir</option>
                        <!-- CORRECTION : value="retard" pour les opérations hors délais -->
                        <option value="retard" <?= ($filtreStatut === 'retard') ? 'selected' : '' ?>>⚠️ Retard</option>
                    </select>
                </div>

                <!-- CATÉGORIES (ACHATS) -->
                <div class="d-flex flex-column">
                    <label class="form-label text-white-50 small mb-1">🏷️ Catégorie</label>
                    <select name="categorie" class="form-select bg-dark text-white border-secondary"
                        style="max-width: 190px;">
                        <option value="">Toutes catégories</option>

                        <?php
                        // Récupération hiérarchique des catégories (parents et sous-catégories) de l'utilisateur
                        $stmtCatAchats = $pdo->prepare('
                SELECT id, nom, parent_id
                FROM categories
                WHERE user_id = :user_id
                ORDER BY 
                    COALESCE(parent_id, id) ASC,
                    parent_id IS NOT NULL ASC,
                    nom ASC
            ');
                        $stmtCatAchats->execute(['user_id' => $userId]);

                        foreach ($stmtCatAchats->fetchAll(PDO::FETCH_ASSOC) as $catAchat):
                            // Formatage du nom selon le niveau (Parent ou Sous-catégorie)
                            $nomCategorie = !empty($catAchat['parent_id'])
                                ? '└── 🏷️ ' . $catAchat['nom']
                                : '📁 ' . $catAchat['nom'];
                        ?>
                            <option value="<?= (int) $catAchat['id'] ?>" <?= ((string) $filtreCategorie === (string) $catAchat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nomCategorie) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- REVENUS -->
                <div class="d-flex flex-column">
                    <label class="form-label text-white-50 small mb-1">💰 Revenus</label>
                    <select name="categorie_ressource" class="form-select bg-dark text-white border-secondary"
                        style="max-width: 190px;">

                        <option value="">Tous les revenus</option>

                        <?php
                        // Récupération des catégories de ressources de l'utilisateur
                        $stmtCat = $pdo->prepare('
                SELECT DISTINCT c.id, c.nom, c.couleur
                FROM categories_ressources c
                LEFT JOIN ressources r 
                    ON c.id = r.categorie_id AND r.user_id = :user_id
                WHERE c.user_id = :user_id 
                   OR r.id IS NOT NULL
                ORDER BY c.nom ASC
            ');
                        $stmtCat->execute(['user_id' => $userId]);

                        foreach ($stmtCat->fetchAll(PDO::FETCH_ASSOC) as $cat):
                        ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= ((string) $filtreCategorieRessource === (string) $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nom']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <!-- BOUTONS D'ACTION -->
                <div class="d-flex gap-2">
                    <!-- BOUTON FILTRER -->
                    <button type="submit"
                        class="btn-create-dash shadow-sm px-3 d-inline-flex align-items-center justify-content-center"
                        style="height: 38px;">
                        🔍 Filtrer
                    </button>

                    <!-- BOUTON Effacer (CORRECTION : Conservation du mois sélectionné lors de la réinitialisation des filtres) -->
                    <a href="<?= BASE_URL ?>router.php?p=echeances_mois.php&mois=<?= urlencode($mois) ?>"
                        class="btn-modifier-neon shadow-sm px-3 text-nowrap text-decoration-none d-inline-flex align-items-center justify-content-center"
                        style="height: 38px;" title="Réinitialiser tous les filtres">
                        🔄 Réinitialiser
                    </a>
                </div>

            </form>
            <!-- ========================================== -->
            <!-- VERSION PC : Tableau classique (masqué sur mobile) -->
            <!-- ========================================== -->
            <div class="d-none d-md-block">
                <div class="table-responsive shadow-lg">
                    <table class="table table-dark table-hover p-0 mb-0 bg-glass table-custom-dark">
                        <thead>
                            <tr class="text-accent-blue border-bottom border-secondary">
                                <th style="width: 5%;">Date</th>
                                <th style="width: 40%;">Libellé</th>
                                <th style="width: 15%;" class="text-end">Dépense</th>
                                <th style="width: 15%;" class="text-end">Revenu</th>
                                <th style="width: 5%;" class="text-center">Statut</th>
                                <th style="width: 20%;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($operationsFiltrees as $op):
                                $estAchat = ($op['type_operation'] === 'achat');

                                if ($estAchat) {
                                    $estEnRetard = ($op['date_operation'] < $aujourdhui && $op['statut'] !== 'payee');
                                } else {
                                    $estEnRetard = ($op['date_operation'] < $aujourdhui && $op['statut'] !== 'percu');
                                }

                                $trClasse = $estEnRetard ? 'bg-danger-soft' : '';
                            ?>
                                <tr class="<?= $trClasse ?> align-middle border-bottom border-secondary">
                                    <td class="text-white fw-bold">
                                        <?= date('d/m/Y', strtotime($op['date_operation'])) ?>
                                    </td>
                                    <td>
                                        <div class="text-white fw-bold">
                                            <?= htmlspecialchars($op['titre'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                                            <?php if ($op['type_operation'] === 'achat'): ?>
                                                <?php if (!empty($op['nom_marchand'])): ?>
                                                    <a href="router.php?p=marchands.php&marchand_filter=<?= urlencode($op['nom_marchand']) ?>" class="text-decoration-none">
                                                        <span class="badge-neon border-accent-blue text-accent-blue px-2 py-1">
                                                            🏪 <?= htmlspecialchars($op['nom_marchand'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </a>
                                                <?php endif; ?>
                                                <?php
                                                $catColor = !empty($op['couleur_categorie']) ? $op['couleur_categorie'] : '#ffffff';
                                                $aUneParente = !empty($op['nom_categorie_parente']);
                                                $nomAffichage = $aUneParente ? $op['nom_categorie_parente'] : (!empty($op['nom_categorie']) ? $op['nom_categorie'] : 'Sans catégorie');
                                                $achatId = (int) ($op['achat_id'] ?? $op['id']);
                                                ?>
                                                <a href="<?= BASE_URL ?>router.php?p=gestion_categories.php&assign_to=<?= $achatId ?>"
                                                    class="badge-dash-pill badge-cat-neon text-decoration-none" title="Changer la catégorie de cet achat"
                                                    style="border-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>66; 
          background-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>11; 
          color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>;
          box-shadow: 0 0 8px <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>33;">
                                                    📁 <?= htmlspecialchars($nomAffichage, ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                                <?php if ($aUneParente): ?>
                                                    <span class="badge-dash-pill text-decoration-none"
                                                        style="border-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>66; 
                                           background-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>22; 
                                           color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>;
                                           box-shadow: 0 0 8px <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>33;">
                                                        🏷️ <?= htmlspecialchars($op['nom_categorie'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if ($nomAffichage === 'Non classé' || $nomAffichage === 'Sans catégorie'): ?>
                                                    <a href="<?= BASE_URL ?>router.php?p=gestion_categories.php&assign_to=<?= $achatId ?>" class="text-decoration-none"
                                                        title="Cliquez pour classer cet achat">
                                                        <span class="badge badge-warning-pulse">
                                                            ⚠️ À CLASSER
                                                        </span>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php
                                                $catColor = !empty($op['couleur_categorie']) ? $op['couleur_categorie'] : '#10B981';
                                                $nomCategorie = !empty($op['nom_categorie']) ? $op['nom_categorie'] : 'Revenu';
                                                ?>
                                                <span class="badge-dash-pill badge-cat-neon" style="border-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>66; 
                                           background-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>11; 
                                           color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>;
                                           box-shadow: 0 0 8px <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>33;">
                                                    💰 <?= htmlspecialchars($nomCategorie, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-end text-danger fw-bold">
                                        <?= $op['type_operation'] === 'achat' ? number_format($op['montant'], 2, ',', ' ') . ' €' : '-' ?>
                                    </td>
                                    <td class="text-end text-success fw-bold">
                                        <?= $op['type_operation'] === 'ressource' ? number_format($op['montant'], 2, ',', ' ') . ' €' : '-' ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($op['type_operation'] === 'achat'): ?>
                                            <?php if ($op['statut'] === 'payee'): ?>
                                                <span class="badge-neon badge-solde px-2 py-1">✔ Payé</span>
                                            <?php elseif ($estEnRetard): ?>
                                                <span class="badge-neon badge-en-retard px-2 py-1">⚠ Retard</span>
                                            <?php else: ?>
                                                <span class="badge bg-glass border text-white px-2 py-1">⏳ Attente</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($op['statut'] === 'percu'): ?>
                                                <span class="badge-neon badge-solde px-2 py-1">✔ Perçu</span>
                                            <?php else: ?>
                                                <span class="badge bg-glass border text-white px-2 py-1">⏳ Attente</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap">
                                            <?php if ($op['type_operation'] === 'achat'): ?>
                                                <?php if ($op['statut'] !== 'payee'): ?>
                                                    <form method="POST" action="<?= BASE_URL ?>router.php?p=achats/payer_echeance.php" class="m-0">
                                                        <input type="hidden" name="id" value="<?= (int) ($op['operation_id'] ?? $op['id']) ?>">
                                                        <?php csrf_input() ?>
                                                        <button type="submit" class="btn btn-sm btn-payer-neon">✔ Payer</button>
                                                    </form>
                                                <?php endif; ?>
                                                <a href="<?= BASE_URL ?>router.php?p=achats/modifier_echeance.php&id=<?= (int) ($op['operation_id'] ?? $op['id']) ?>"
                                                    class="btn-modifier-neon text-decoration-none">
                                                    ✏ Modifier
                                                </a>
                                            <?php else: ?>
                                                <?php if ($op['statut'] !== 'percu'): ?>
                                                    <form method="POST" action="<?= BASE_URL ?>router.php?p=ressources/payer_versement.php" class="m-0">
                                                        <input type="hidden" name="id" value="<?= (int) ($op['operation_id'] ?? $op['id']) ?>">
                                                        <?php csrf_input() ?>
                                                        <button type="submit" class="btn btn-sm btn-payer-neon">✔ Perçu</button>
                                                    </form>
                                                <?php endif; ?>
                                                <a href="<?= BASE_URL ?>router.php?p=ressources/modifier_versement.php&id=<?= (int) ($op['operation_id'] ?? $op['id']) ?>"
                                                    class="btn-modifier-neon text-decoration-none">
                                                    ✏ Modifier
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- VERSION MOBILE : Cartes empilées (masqué sur PC) -->
            <!-- ========================================== -->
            <div class="d-block d-md-none">
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($operationsFiltrees as $op):
                        $estAchat = ($op['type_operation'] === 'achat');
                        if ($estAchat) {
                            $estEnRetard = ($op['date_operation'] < $aujourdhui && $op['statut'] !== 'payee');
                        } else {
                            $estEnRetard = ($op['date_operation'] < $aujourdhui && $op['statut'] !== 'percu');
                        }
                        $cardClasse = $estEnRetard ? 'bg-danger-soft border-danger' : 'bg-glass border-secondary';
                    ?>
                        <div class="p-3 rounded-4 shadow-sm border <?= $cardClasse ?>">
                            <!-- Ligne supérieure : Date et Statut -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-white-50 small fw-bold">📅
                                    <?= date('d/m/Y', strtotime($op['date_operation'])) ?></span>
                                <div>
                                    <?php if ($op['type_operation'] === 'achat'): ?>
                                        <?php if ($op['statut'] === 'payee'): ?>
                                            <span class="badge-neon badge-solde px-2 py-1">✔ Payé</span>
                                        <?php elseif ($estEnRetard): ?>
                                            <span class="badge-neon badge-en-retard px-2 py-1">⚠ Retard</span>
                                        <?php else: ?>
                                            <span class="badge bg-glass border text-white px-2 py-1">⏳ Attente</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ($op['statut'] === 'percu'): ?>
                                            <span class="badge-neon badge-solde px-2 py-1">✔ Perçu</span>
                                        <?php else: ?>
                                            <span class="badge bg-glass border text-white px-2 py-1">⏳ Attente</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Titre et Montant -->
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="text-white fw-bold fs-6">
                                    <?= htmlspecialchars($op['titre'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div>
                                    <?php if ($op['type_operation'] === 'achat'): ?>
                                        <span class="text-danger fw-bold fs-6"><?= number_format($op['montant'], 2, ',', ' ') ?> €</span>
                                    <?php else: ?>
                                        <span class="text-success fw-bold fs-6"><?= number_format($op['montant'], 2, ',', ' ') ?> €</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Badges (Marchand / Catégorie) -->
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                                <?php if ($op['type_operation'] === 'achat'): ?>
                                    <?php if (!empty($op['nom_marchand'])): ?>
                                        <span class="badge-neon border-accent-blue text-accent-blue px-2 py-1 small">
                                            🏪 <?= htmlspecialchars($op['nom_marchand'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php
                                    $catColor = !empty($op['couleur_categorie']) ? $op['couleur_categorie'] : '#ffffff';
                                    $nomCategorie = !empty($op['nom_categorie']) ? $op['nom_categorie'] : 'Sans catégorie';
                                    $achatId = (int) ($op['achat_id'] ?? $op['id']);
                                    ?>
                                    <span class="badge-dash-pill badge-cat-neon small"
                                        style="border-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>66; background-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>11; color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>;">
                                        📁 <?= htmlspecialchars($nomCategorie, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php else: ?>
                                    <?php
                                    $catColor = !empty($op['couleur_categorie']) ? $op['couleur_categorie'] : '#10B981';
                                    $nomCategorie = !empty($op['nom_categorie']) ? $op['nom_categorie'] : 'Revenu';
                                    ?>
                                    <span class="badge-dash-pill badge-cat-neon small"
                                        style="border-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>66; background-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>11; color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>;">
                                        💰 <?= htmlspecialchars($nomCategorie, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Actions en bas de carte -->
                            <div class="d-flex justify-content-end align-items-center gap-2 pt-2 border-top border-secondary">
                                <?php if ($op['type_operation'] === 'achat'): ?>
                                    <?php if ($op['statut'] !== 'payee'): ?>
                                        <form method="POST" action="<?= BASE_URL ?>router.php?p=achats/payer_echeance.php" class="m-0">
                                            <input type="hidden" name="id" value="<?= (int) ($op['operation_id'] ?? $op['id']) ?>">
                                            <?php csrf_input() ?>
                                            <button type="submit" class="btn btn-sm btn-payer-neon">✔ Payer</button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="<?= BASE_URL ?>router.php?p=achats/modifier_echeance.php&id=<?= (int) ($op['operation_id'] ?? $op['id']) ?>" class="btn-modifier-neon text-decoration-none small">
                                        ✏ Modifier
                                    </a>
                                <?php else: ?>
                                    <?php if ($op['statut'] !== 'percu'): ?>
                                        <form method="POST" action="<?= BASE_URL ?>router.php?p=ressources/payer_versement.php" class="m-0">
                                            <input type="hidden" name="id" value="<?= (int) ($op['operation_id'] ?? $op['id']) ?>">
                                            <?php csrf_input() ?>
                                            <button type="submit" class="btn btn-sm btn-payer-neon">✔ Perçu</button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="<?= BASE_URL ?>router.php?p=ressources/modifier_versement.php&id=<?= (int) ($op['operation_id'] ?? $op['id']) ?>" class="btn-modifier-neon text-decoration-none small">
                                        ✏ Modifier
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- Totaux communs -->
            <!-- ========================================== -->
            <div class="mt-4 d-flex justify-content-between">
                <div class="text-muted small">
                    Dépenses restantes : <?= number_format($resteAchats, 2, ',', ' ') ?> € |
                    Revenus restants : <?= number_format($resteRessources, 2, ',', ' ') ?> €
                </div>
            </div>
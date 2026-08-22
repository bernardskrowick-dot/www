<?php
/* Version: v1.19.1 (Rev #25) - 2026-08-06 */

/**
 * statistiques.php - Version Filtres Avancés & Panier Moyen Dynamique
 * - Ajout des filtres : Mois, Date de début, Date de fin (période personnalisée).
 * - Nouveaux KPI : Panier Moyen global filtré et Dépense Moyenne Mensuelle.
 * - Respect strict des tables : achats, categories, echeances.
 * - Sécurité : Requêtes préparées PDO avec :uid, protection XSS via htmlspecialchars().
 */

// Sécurité : Vérification de la session via les constantes définies dans init.php
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$aujourdhui = date('Y-m-d');

// ---------------------------------------------------------
// 1. GESTION DES FILTRES (Récupération des données GET)
// ---------------------------------------------------------
$filtreAnnee = $_GET['annee'] ?? '';
$filtreMois = $_GET['mois'] ?? '';
$filtreDateDebut = $_GET['date_debut'] ?? '';
$filtreDateFin = $_GET['date_fin'] ?? '';
$filtreMarchand = $_GET['marchand'] ?? '';
$filtreCategorie = $_GET['categorie'] ?? '';

// ---------------------------------------------------------
// Calcul du nombre de mois pour le lissage mensuel
// ---------------------------------------------------------

$nbMoisPeriode = 1;

if ($filtreDateDebut && $filtreDateFin) {
    $debut = new DateTime($filtreDateDebut);
    $fin = new DateTime($filtreDateFin);

    $interval = $debut->diff($fin);

    $nbMoisPeriode = (($interval->y * 12) + $interval->m) + 1;
} elseif ($filtreAnnee) {
    // Une année complète
    $nbMoisPeriode = 12;
} elseif ($filtreMois) {
    // Un mois précis
    $nbMoisPeriode = 1;
}

// Construction dynamique de la clause WHERE et des paramètres
$where = ' WHERE a.user_id = :uid ';
$params = [':uid' => $userId];

// Filtres temporels
if ($filtreAnnee) {
    $where .= ' AND YEAR(e.date_echeance) = :annee';
    $params[':annee'] = $filtreAnnee;
}
if ($filtreMois) {
    $where .= ' AND MONTH(e.date_echeance) = :mois';
    $params[':mois'] = (int) $filtreMois;
}
if ($filtreDateDebut) {
    $where .= ' AND e.date_echeance >= :datedebut';
    $params[':datedebut'] = $filtreDateDebut;
}
if ($filtreDateFin) {
    $where .= ' AND e.date_echeance <= :datefin';
    $params[':datefin'] = $filtreDateFin;
}

// Filtres entités
if ($filtreMarchand) {
    $where .= ' AND a.nom_marchand = :marchand';
    $params[':marchand'] = $filtreMarchand;
}
if ($filtreCategorie) {
    // Filtre catégorie : inclut la catégorie sélectionnée
    // ainsi que ses sous-catégories directes
    $where .= ' AND (
        a.categorie_id = :catid
        OR a.categorie_id IN (
            SELECT id
            FROM categories
            WHERE parent_id = :catid_parent
            AND user_id = :uid
        )
    )';

    $params[':catid'] = $filtreCategorie;
    $params[':catid_parent'] = $filtreCategorie;
}

// ---------------------------------------------------------
// 2. RÉCUPÉRATION DES PARAMÈTRES POUR LES DROPDOWNS
// ---------------------------------------------------------

// Récupération des années réelles
$sqlAnnees = 'SELECT DISTINCT YEAR(e.date_echeance) as annee FROM echeances e 
              JOIN achats a ON e.achat_id = a.id WHERE a.user_id = ? ORDER BY annee DESC';
$stmtA = $pdo->prepare($sqlAnnees);
$stmtA->execute([$userId]);
$listeAnnees = $stmtA->fetchAll(PDO::FETCH_COLUMN);

// Liste explicite des mois
$listeMois = [
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

// Récupération des marchands
$stmtM = $pdo->prepare('SELECT DISTINCT nom_marchand FROM achats WHERE user_id = ? ORDER BY nom_marchand');
$stmtM->execute([$userId]);
$listeMarchands = $stmtM->fetchAll(PDO::FETCH_COLUMN);

// Récupération des catégories principales et sous-catégories
// Les sous-catégories sont associées à leur catégorie parent pour permettre le regroupement
$stmtCats = $pdo->prepare('
    SELECT 
        c.id,
        c.nom,
        c.parent_id,
        COALESCE(parent.nom, c.nom) AS nom_categorie_parent,
        COALESCE(parent.id, c.id) AS id_categorie_parent
    FROM categories c
    LEFT JOIN categories parent 
        ON c.parent_id = parent.id
        AND parent.user_id = c.user_id
    WHERE c.user_id = ?
    ORDER BY nom_categorie_parent, c.nom
');

$stmtCats->execute([$userId]);
$listeCats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------
// 3. INDICATEURS CLÉS (KPI) DYNAMIQUES
// ---------------------------------------------------------

// KPI 1 : Coût Total des Dépenses & Ventilation Payé / Dette Restante
$sqlCoutGlobal = "SELECT 
                    SUM(e.montant) as total_global,
                    SUM(CASE WHEN e.statut = 'en_attente' THEN e.montant ELSE 0 END) as total_dette,
                    SUM(CASE WHEN e.statut = 'payee' THEN e.montant ELSE 0 END) as total_paye,
                    COUNT(DISTINCT a.id) as nb_achats_uniques,
                    COUNT(DISTINCT DATE_FORMAT(e.date_echeance, '%Y-%m')) as nb_mois_distincts
                  FROM echeances e 
                  JOIN achats a ON e.achat_id = a.id 
                  $where";
$stmtCoutGlobal = $pdo->prepare($sqlCoutGlobal);
$stmtCoutGlobal->execute($params);
$kpiCout = $stmtCoutGlobal->fetch(PDO::FETCH_ASSOC);

$coutTotalFiltre = $kpiCout['total_global'] ?: 0;
$detteRestanteFiltre = $kpiCout['total_dette'] ?: 0;
$montantPayeFiltre = $kpiCout['total_paye'] ?: 0;
$nbAchatsUniques = $kpiCout['nb_achats_uniques'] ?: 0;
$nbMoisDistincts = $kpiCout['nb_mois_distincts'] ?: 1;

// KPI Panier Moyen selon filtre
$panierMoyenGlobal = $nbAchatsUniques > 0 ? ($coutTotalFiltre / $nbAchatsUniques) : 0;

// KPI Moyenne Mensuelle selon filtre
$moyenneMensuelleFiltre = $nbMoisDistincts > 0 ? ($coutTotalFiltre / $nbMoisDistincts) : 0;

// KPI 2 : Date de fin de dette (dernière échéance en_attente)
$sqlFin = "SELECT MAX(e.date_echeance) FROM echeances e JOIN achats a ON e.achat_id = a.id $where AND e.statut = 'en_attente'";
$stmtFin = $pdo->prepare($sqlFin);
$stmtFin->execute($params);
$dateFinRaw = $stmtFin->fetchColumn();
$dateFinDette = $dateFinRaw ? date('d/m/Y', strtotime($dateFinRaw)) : 'Réglé / Aucun encours';

// KPI 3 : Plus grosse dépense
// Les sous-catégories sont affichées sous leur catégorie principale
$sqlMaxDepense = "SELECT 
                    e.montant, 
                    e.date_echeance, 
                    e.statut, 
                    a.nom_marchand, 
                    COALESCE(parent.nom, c.nom) as categorie_nom
                  FROM echeances e 
                  JOIN achats a ON e.achat_id = a.id 
                  LEFT JOIN categories c 
                        ON a.categorie_id = c.id
                  LEFT JOIN categories parent 
                        ON c.parent_id = parent.id
                        AND parent.user_id = c.user_id
                  $where 
                  ORDER BY e.montant DESC 
                  LIMIT 1";

$stmtMaxDepense = $pdo->prepare($sqlMaxDepense);
$stmtMaxDepense->execute($params);
$plusGrosseDepense = $stmtMaxDepense->fetch(PDO::FETCH_ASSOC);

// KPI 4 : Achats en plusieurs fois (Total, Nombre et Moyenne)
$sqlEchelonnes = "SELECT 
                    COUNT(*) as nb_echelonnes, 
                    SUM(t.montant_total) as total_echelonne,
                    AVG(t.montant_total) as moyenne_echelonne
                  FROM (
                    SELECT a.id, a.montant_total, COUNT(e.id) as nb_ech 
                    FROM achats a 
                    JOIN echeances e ON e.achat_id = a.id 
                    $where 
                    GROUP BY a.id 
                    HAVING nb_ech > 1
                  ) t";
$stmtEchelonnes = $pdo->prepare($sqlEchelonnes);
$stmtEchelonnes->execute($params);
$echelonnesInfo = $stmtEchelonnes->fetch(PDO::FETCH_ASSOC);
$nbEchelonnes = $echelonnesInfo['nb_echelonnes'] ?? 0;
$totalEchelonne = $echelonnesInfo['total_echelonne'] ?? 0;
$moyenneEchelonne = $echelonnesInfo['moyenne_echelonne'] ?? 0;

// KPI 5 : Échéances en retard
$sqlRetards = "SELECT COUNT(*) as nb, SUM(e.montant) as total FROM echeances e 
               JOIN achats a ON e.achat_id = a.id 
               $where AND e.statut = 'en_attente' AND e.date_echeance < CURDATE()";
$stmtRetards = $pdo->prepare($sqlRetards);
$stmtRetards->execute($params);
$retardsInfo = $stmtRetards->fetch(PDO::FETCH_ASSOC);
$nbRetards = $retardsInfo['nb'] ?? 0;
$totalRetards = $retardsInfo['total'] ?? 0;

// ---------------------------------------------------------
// 4. PRÉPARATION DES DONNÉES GRAPHIQUES (AVEC FILTRES)
// ---------------------------------------------------------

// G1: Donut Catégories
// Les sous-catégories sont regroupées dans leur catégorie principale
$dataG1 = $pdo->prepare("
    SELECT 
        COALESCE(parent.nom, c.nom) AS nom,
        COALESCE(parent.couleur, c.couleur) AS couleur,
        SUM(e.montant) AS total
    FROM echeances e
    JOIN achats a 
        ON e.achat_id = a.id
    JOIN categories c 
        ON a.categorie_id = c.id
    LEFT JOIN categories parent
        ON c.parent_id = parent.id
        AND parent.user_id = c.user_id
    $where
    GROUP BY 
        COALESCE(parent.id, c.id),
        COALESCE(parent.nom, c.nom),
        COALESCE(parent.couleur, c.couleur)
");

$dataG1->execute($params);
$resG1 = $dataG1->fetchAll(PDO::FETCH_ASSOC);

// G2: Historique Annuel
$dataG2 = $pdo->prepare("SELECT YEAR(e.date_echeance) as annee, SUM(e.montant) as total FROM echeances e 
                         JOIN achats a ON e.achat_id = a.id $where GROUP BY annee ORDER BY annee ASC");
$dataG2->execute($params);
$resG2 = $dataG2->fetchAll(PDO::FETCH_ASSOC);

// G3: Flux Mensuel
$dataG3 = $pdo->prepare("SELECT DATE_FORMAT(e.date_echeance, '%Y-%m') as mois, SUM(e.montant) as total 
                         FROM echeances e JOIN achats a ON e.achat_id = a.id $where GROUP BY mois ORDER BY mois ASC");
$dataG3->execute($params);
$resG3 = $dataG3->fetchAll(PDO::FETCH_ASSOC);

// G4: Top 5 Marchands
$dataG4 = $pdo->prepare("SELECT a.nom_marchand, SUM(e.montant) as total FROM echeances e 
                         JOIN achats a ON e.achat_id = a.id $where GROUP BY a.nom_marchand ORDER BY total DESC LIMIT 5");
$dataG4->execute($params);
$resG4 = $dataG4->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------
// 5. CALCUL DES INDICATEURS STATISTIQUES COMPLÉMENTAIRES
// ---------------------------------------------------------

// Panier moyen par marchand
$sqlPanierMarchand = "SELECT 
                        a.nom_marchand,
                        COUNT(e.id) as nb_echeances,
                        SUM(e.montant) as total_depense
                      FROM echeances e
                      JOIN achats a ON e.achat_id = a.id
                      $where
                      GROUP BY a.nom_marchand
                      ORDER BY total_depense DESC";

$stmtPanierMarchand = $pdo->prepare($sqlPanierMarchand);

$stmtPanierMarchand->execute($params);

$listePanierMarchands = $stmtPanierMarchand->fetchAll(PDO::FETCH_ASSOC);

// Totaux du tableau Panier moyen par marchand
$totalEcheancesTableau = 0;
$totalDepenseTableau = 0;

foreach ($listePanierMarchands as $pm) {
    $totalEcheancesTableau += (int) $pm['nb_echeances'];
    $totalDepenseTableau += (float) $pm['total_depense'];
}

// Calcul de la moyenne mensuelle lissée
foreach ($listePanierMarchands as &$pm) {
    $pm['depense_moyenne_mensuelle'] = $nbMoisPeriode > 0
        ? $pm['total_depense'] / $nbMoisPeriode
        : 0;
}
unset($pm);

// Dépenses mensuelles moyennes par catégorie
// Les dépenses des sous-catégories sont regroupées dans leur catégorie principale
$sqlDepensesCat = "SELECT 
                    cat_nom,
                    couleur,
                    AVG(total_mois) as moyenne_mensuelle,
                    SUM(total_mois) as total_cumule,
                    COUNT(DISTINCT mois) as nb_mois_actifs
                   FROM (
                       SELECT 
                           COALESCE(parent.nom, c.nom) AS cat_nom,
                           COALESCE(parent.couleur, c.couleur) AS couleur,
                           DATE_FORMAT(e.date_echeance, '%Y-%m') as mois,
                           SUM(e.montant) as total_mois
                       FROM echeances e
                       JOIN achats a 
                            ON e.achat_id = a.id
                       JOIN categories c 
                            ON a.categorie_id = c.id
                       LEFT JOIN categories parent
                            ON c.parent_id = parent.id
                            AND parent.user_id = c.user_id
                       $where
                       GROUP BY 
                           COALESCE(parent.id, c.id),
                           COALESCE(parent.nom, c.nom),
                           COALESCE(parent.couleur, c.couleur),
                           mois
                   ) AS sous_requete
                   GROUP BY cat_nom, couleur
                   ORDER BY moyenne_mensuelle DESC";

$stmtDepensesCat = $pdo->prepare($sqlDepensesCat);
$stmtDepensesCat->execute($params);
$listeDepensesCategories = $stmtDepensesCat->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mb-4 nav-dashboard-container">
    <div class="glass-card-nav mt-4 mb-2">

        <div class="d-flex flex-wrap align-items-center mt-3">
        </div>

        <div class="p-3 pt-0">
            <?php
            if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
                include DIR_LOGIC . 'top-bar-title-page.php';
            }
            ?>
        </div>

        <hr class="hr-glass mt-4 mb-4" style="opacity: 0.6;">

        <div class="text-left mt-2 mb-4">
            <a href="<?= BASE_URL ?>router.php?p=dashboard_achats.php" class="btn btn-secondary shadow-sm mt-0 mb-0">
                <span class="emoji fs-4">🛒</span> Retour au Tableau de bord Achats
            </a>
        </div>

        <hr class="hr-glass mt-2 mb-4" style="opacity: 0.6;">

        <!-- =====================================================
             FORMULAIRE DE FILTRES AVANCÉS
        ====================================================== -->
        <div class="glass-card-nav p-4 mb-5">
            <form method="GET" action="<?= BASE_URL ?>router.php" class="row g-3 align-items-end ">
                <input type="hidden" name="p" value="statistiques.php">

                <!-- Année -->
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-white-50"><span class="emoji fs-4">📅</span>
                        ANNÉE</label>
                    <select name="annee" class="form-select bg-dark text-white border-secondary">
                        <option value="">Toutes</option>
                        <?php foreach ($listeAnnees as $ann): ?>
                            <option value="<?= $ann ?>" <?= $filtreAnnee == $ann ? 'selected' : '' ?>><?= $ann ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Mois -->
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-white-50"><span class="emoji fs-4">🗓️</span>
                        MOIS</label>
                    <select name="mois" class="form-select bg-dark text-white border-secondary">
                        <option value="">Tous</option>
                        <?php foreach ($listeMois as $numMois => $nomMois): ?>
                            <option value="<?= $numMois ?>" <?= $filtreMois == $numMois ? 'selected' : '' ?>>
                                <?= $nomMois ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date Début -->
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-white-50"><span class="emoji fs-4">🛫</span> DEBUT
                        PÉRIODE</label>
                    <input type="date" name="date_debut" class="form-select bg-dark text-white border-secondary"
                        value="<?= htmlspecialchars($filtreDateDebut, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <!-- Date Fin -->
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-white-50"><span class="emoji fs-4">🛬</span> FIN
                        PÉRIODE</label>
                    <input type="date" name="date_fin" class="form-select bg-dark text-white border-secondary"
                        value="<?= htmlspecialchars($filtreDateFin, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <!-- Catégorie -->
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-white-50"><span class="emoji fs-4">📁</span>
                        CATÉGORIE</label>
                    <select name="categorie" class="form-select bg-dark text-white border-secondary w-100">
                        <option value="">Toutes les catégories</option>

                        <?php
                        $catStmt = $pdo->prepare('
            SELECT id, nom, parent_id
            FROM categories
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

                            <option value="<?= $c['id'] ?>" <?= ($filtreCategorie == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nomCategorie) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <!-- Marchand -->
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-white-50"><span class="emoji fs-4">🏪</span>
                        MARCHAND</label>
                    <select name="marchand" class="form-select bg-dark text-white border-secondary">
                        <option value="">Tous</option>
                        <?php foreach ($listeMarchands as $m): ?>
                            <option value="<?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?>" <?= $filtreMarchand == $m ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
<div class="col-12 d-flex gap-2 mt-3 mb-3">

    <button type="submit"
        class="btn-create-dash shadow-sm px-4 flex-fill d-inline-flex align-items-center justify-content-center"
        style="height: 38px;">
        <span class="emoji fs-4">🔍</span> Analyser la sélection
    </button>

    <a href="<?= BASE_URL ?>router.php?p=statistiques.php"
        class="btn-modifier-neon shadow-sm px-4 flex-fill text-decoration-none d-inline-flex align-items-center justify-content-center"
        style="height: 38px;"
        title="Réinitialiser tous les filtres">
        <span class="emoji fs-4">🔄</span> Réinitialiser
    </a>

</div>
            </form>
        </div>

       <!-- =====================================================
    SECTION 1 : CARTES KPI (Ligne 1 - Engagements & Panier)
====================================================== -->
    <div class="row mt-2 mb-2">

<!-- KPI 1 : Coût Total Engagé -->
        <div class="col-md-3 mb-3">
            <div class="form-label glass-card-nav p-3 card-kpi h-100">
                <div class="small text-uppercase text-white-50 fw-bold mb-2 text-nowrap">
                    <span>
                        <span class="emoji fs-4">💰</span> Coût Engagé
                    </span>
                    <span class="info-tooltip ms-1 text-info fs-4" style="white-space: normal !important; display: inline-block;">
                        ⓘ
                        <span class="info-tooltip-text">
                            Total des dépenses enregistrées sur la période sélectionnée.
                            Calcul : somme des échéances correspondant aux filtres actifs.
                        </span>
                    </span>
                </div>

                <div class="display-6 fw-900 text-white">
                <?= number_format((float) $coutTotalFiltre, 2, ',', ' ') ?> €
        </div>

        <div class="small text-white-50 mt-1">
            <div>
                Reste à payer :
                <strong class="text-info">
                    <?= number_format((float) $detteRestanteFiltre, 2, ',', ' ') ?> €
                </strong>
            </div>
            <div>
                Réglé :
                <strong class="text-success">
                    <?= number_format((float) $montantPayeFiltre, 2, ',', ' ') ?> €
                </strong>
            </div>
        </div>
    </div>
</div>

        <!-- KPI 2 : Panier Moyen Dynamique -->
        <div class="col-md-3 mb-3">
            <div class="form-label glass-card-nav p-3 card-kpi h-100">
                <div class="small text-uppercase text-white-50 fw-bold mb-2 text-nowrap">
                    <span>
                        <span class="emoji fs-4">🛒</span> Panier Moyen / Achat
                    </span>
                    <span class="info-tooltip ms-1 text-info fs-4" style="white-space: normal !important; display: inline-block;">
                        ⓘ
                        <span class="info-tooltip-text">
                            Montant moyen dépensé par achat sur la période sélectionnée.
                            Calcul : coût total engagé ÷ nombre d'achats distincts.
                            Cette valeur correspond à une moyenne : chaque achat peut avoir un montant différent.
                        </span>
                    </span>
                </div>

                <div class="display-6 fw-900 text-success">
                    <?= number_format((float) $panierMoyenGlobal, 2, ',', ' ') ?> €
                </div>

                <div class="small text-white-50 mt-1">
                    <div>
                        Basé sur
                        <strong><?= (int) $nbAchatsUniques ?></strong>
                        achat(s) distinct(s)
                    </div>
                </div>
                </div>
                </div>

        <!-- KPI 3 : Moyenne Mensuelle sur la Période -->
        <div class="col-md-3 mb-3">
            <div class="form-label glass-card-nav p-3 card-kpi h-100">
                <div class="small text-uppercase text-white-50 fw-bold mb-2 text-nowrap">
                    <span>
                        <span class="emoji fs-4">📊</span> Moyenne / Mois
                    </span>
                    <span class="info-tooltip ms-1 text-info fs-4" style="white-space: normal !important; display: inline-block;">
                        ⓘ
                        <span class="info-tooltip-text">
                            Dépense mensuelle moyenne lissée sur la période sélectionnée.
                            Calcul : coût total de la période divisé par le nombre de mois analysés.
                        </span>
                    </span>
                </div>

                <div class="display-6 fw-900 text-warning">
                    <?= number_format((float) $moyenneMensuelleFiltre, 2, ',', ' ') ?> €
                </div>

                <div class="small text-white-50 mt-1">
                    <div>
                        Sur
                        <strong><?= (int) $nbMoisDistincts ?></strong>
                        mois actif(s)
                    </div>
                </div>
                </div>
                </div>

        <!-- KPI 4 : Date de Libération Finale -->
        <div class="col-md-3 mb-3">
            <div class="form-label glass-card-nav p-3 card-kpi h-100">
                <div class="small text-uppercase text-white-50 fw-bold mb-2 text-nowrap">
                    <span>
                        <span class="emoji fs-4">🏁</span> Libération Finale
                    </span>
                    <span class="info-tooltip ms-1 text-info fs-4" style="white-space: normal !important; display: inline-block;">
                        ⓘ
                        <span class="info-tooltip-text">
                            Date estimée de fin des échéances restantes.
                            Correspond à la dernière échéance encore en attente de paiement.
                        </span>
                    </span>
                </div>

                <div class="display-6 fw-900 text-primary">
                    <?= htmlspecialchars($dateFinDette, ENT_QUOTES, 'UTF-8') ?>
                </div>

                <div class="small text-white-50 mt-1">
                    Date d'achèvement estimée
                </div>
            </div>
        </div>

    </div>


    <!-- SÉPARATEUR DE LIGNES KPI -->
    <hr class="hr-glass mt-2 mb-3" style="opacity: 0.6;">


    <!-- =====================================================
    SECTION 2 : CARTES KPI (Ligne 2 - Détails & Structure)
====================================================== -->
    <div class="row mt-4 mb-4">

        <!-- KPI 5 : Plus grosse dépense -->
        <div class="col-md-4 mb-3">
            <div class="form-label glass-card-nav p-3 card-kpi h-100">
                <div class="small text-uppercase text-white-50 fw-bold mb-2 text-nowrap">
                    <span>
                        <span class="emoji fs-4">🔥</span> Plus grosse dépense
                    </span>
                    <span class="info-tooltip ms-1 text-info fs-4" style="white-space: normal !important; display: inline-block;">
                        ⓘ
                        <span class="info-tooltip-text">
                            Affiche l'échéance ou la dépense individuelle ayant le montant le plus élevé dans la période filtrée.
                        </span>
                    </span>
                </div>

                <?php if ($plusGrosseDepense): ?>
                        <div class="display-6 fw-900 text-primary">
                        <?= number_format((float) $plusGrosseDepense['montant'], 2, ',', ' ') ?> €
                    </div>
                    <div class="small text-white-50 mt-1">
                        <div><?= htmlspecialchars($plusGrosseDepense['categorie_nom'] ?? 'Sans catégorie', ENT_QUOTES, 'UTF-8') ?></div>
                        <div><?= htmlspecialchars($plusGrosseDepense['nom_marchand'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div>Le : <?= date('d/m/Y', strtotime($plusGrosseDepense['date_echeance'])) ?></div>
                        </div>
                <?php else: ?>
                        <div class="display-6 fw-900 text-muted">0,00 €</div>
                        <small class="text-white-50">Aucune dépense trouvée</small>
                    <?php endif; ?>
            </div>
            </div>

        <!-- KPI 6 : Achats Échelonnés -->
        <div class="col-md-4 mb-3">
            <div class="form-label glass-card-nav p-3 card-kpi h-100">
                <div class="small text-uppercase text-white-50 fw-bold mb-2 text-nowrap">
                    <span>
                        <span class="emoji fs-4">💳</span> Achats Échelonnés
                    </span>
                    <span class="info-tooltip ms-1 text-info fs-4" style="white-space: normal !important; display: inline-block;">
                        ⓘ
                        <span class="info-tooltip-text">
                            Montant total des achats répartis sur plusieurs échéances, avec leur moyenne par achat.
                        </span>
                    </span>
                </div>

                <div class="display-6 fw-900 text-white">
                    <?= number_format((float) $totalEchelonne, 2, ',', ' ') ?> €
                </div>

                <div class="small text-white-50 mt-1">
                    <div><?= (int) $nbEchelonnes ?> achat(s) échelonné(s)</div>
                    <div>
                        Moyenne :
                        <strong><?= number_format((float) $moyenneEchelonne, 2, ',', ' ') ?> €</strong>
                        / achat
                        </div>
                        </div>
            </div>
        </div>

        <!-- KPI 7 : Retards -->
        <div class="col-md-4 mb-3">
            <div class="form-label glass-card-nav p-3 card-kpi h-100">
                <div class="small text-uppercase text-white-50 fw-bold mb-2 text-nowrap">
                    <span>
                        <span class="emoji fs-4">⚠️</span> Échéances en Retard
                    </span>
                    <span class="info-tooltip ms-1 text-info fs-4" style="white-space: normal !important; display: inline-block;">
                        ⓘ
                        <span class="info-tooltip-text">
                            Nombre et montant des échéances non réglées dont la date est dépassée.
                        </span>
                    </span>
                </div>

                <?php if ($nbRetards > 0): ?>
                        <div class="display-6 fw-900 text-danger">
                        <?= number_format((float) $totalRetards, 2, ',', ' ') ?> €
                    </div>
                    <small class="text-danger fw-bold"><?= (int) $nbRetards ?> échéance(s) dépassée(s)</small>
                    <?php else: ?>
                        <div class="display-6 fw-900 text-success">0,00 €</div>
                        <small class="text-white-50">À jour sur vos paiements</small>
                    <?php endif; ?>
            </div>
            </div>

    </div>
        <!-- =====================================================
             GRAPHIQUES
        ====================================================== -->
        <div class="row">

    <!-- =====================================================
         G1 : RÉPARTITION PAR CATÉGORIE
    ====================================================== -->

    <div class="col-12 mb-4">

        <div class="glass-card-nav p-4 h-100">

            <h6 class="text-white-50 text-uppercase mb-3 small fw-bold">
                Répartition par Catégorie (%)
            </h6>

            <div class="chart-container-g1">
                <canvas id="chartG1"></canvas>
            </div>

        </div>

    </div>



    <!-- =====================================================
         G2 : HISTORIQUE ANNUEL
    ====================================================== -->

    <div class="col-12 mb-4">

        <div class="glass-card-nav p-4 h-100">

            <h6 class="text-white-50 text-uppercase mb-3 small fw-bold">
                Historique Annuel (€)
            </h6>

            <div style="height:300px;">
                <canvas id="chartG2"></canvas>
            </div>

        </div>

    </div>



    <!-- =====================================================
         G3 : FLUX MENSUEL
    ====================================================== -->

    <div class="col-md-6 mb-4">

        <div class="glass-card-nav p-4 h-100">

            <h6 class="text-white-50 text-uppercase mb-3 small fw-bold">
                Flux Mensuel Tendance
            </h6>

            <div class="chart-container-g3">
    <canvas id="chartG3"></canvas>
</div>

        </div>

    </div>



    <!-- =====================================================
         G4 : TOP 5 MARCHANDS
    ====================================================== -->

    <div class="col-md-6 mb-4">

        <div class="glass-card-nav p-4 h-100">

            <h6 class="text-white-50 text-uppercase mb-3 small fw-bold">
                Top 5 Marchands (€)
            </h6>

            <div style="height:300px;">
                <canvas id="chartG4"></canvas>
            </div>

        </div>

    </div>

</div>

      <!-- =====================================================
            SECTION KPI TABULAIRES COMPLÉMENTAIRES
        ====================================================== -->
        <div class="row mt-2 mb-4">
            <!-- Table 1 : Dépense moyenne mensuelle par marchand -->
            <div class="col-md-6 mb-4">
                <div class="glass-card-nav p-4 h-100">
                    <h6 class="text-white-50 text-uppercase mb-3 small fw-bold">
                        🛍️ Dépense moyenne mensuelle par marchand
                    </h6>

                    <!-- 1. VERSION ORDINATEUR (Tableau classique inchangé) -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
                            <thead>
                                <tr class="text-white-50 small">
                                    <th>Marchand</th>
                                    <th class="text-center">Échéances</th>
                                    <th class="text-end">Total période</th>
                                    <th class="text-end">Moyenne / mois</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (!empty($listePanierMarchands)): ?>
                                    <?php foreach ($listePanierMarchands as $pm): ?>
                                        <tr>
                                            <td class="fw-bold text-white">
                                                <?= htmlspecialchars($pm['nom_marchand'], ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="text-center">
                                                <?= (int) $pm['nb_echeances'] ?>
                                            </td>
                                            <td class="text-end">
                                                <?= number_format((float) $pm['total_depense'], 2, ',', ' ') ?> €
                                            </td>
                                            <td class="text-end fw-bold text-info">
                                                <?= number_format((float) $pm['depense_moyenne_mensuelle'], 2, ',', ' ') ?> €
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <!-- TOTAL TABLEAU -->
                                    <tr class="fw-bold text-info border-top">
                                        <td>TOTAL</td>
                                        <td class="text-center"><?= (int) $totalEcheancesTableau ?></td>
                                        <td class="text-end"><?= number_format((float) $totalDepenseTableau, 2, ',', ' ') ?> €</td>
                                        <td class="text-end">
                                            <?= number_format($nbMoisPeriode > 0 ? ($totalDepenseTableau / $nbMoisPeriode) : 0, 2, ',', ' ') ?> €
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-white-50 py-3">Aucune donnée disponible</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- 2. VERSION MOBILE (Mini-cartes empilées) -->
                    <div class="d-block d-md-none">
                <?php if (!empty($listePanierMarchands)): ?>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($listePanierMarchands as $pm): ?>
                <div class="p-3 rounded border border-secondary bg-dark bg-opacity-50">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span
                            class="fw-bold text-white fs-6"><?= htmlspecialchars($pm['nom_marchand'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="badge bg-secondary text-white"><?= (int) $pm['nb_echeances'] ?> échéance(s)</span>
                    </div>
                    <div class="d-flex justify-content-between text-small text-white-50 border-top pt-2 mt-2">
                        <span>Total : <strong class="text-white"><?= number_format((float) $pm['total_depense'], 2, ',', ' ') ?>
                                €</strong></span>
                        <span>Moy. / mois : <strong
                                class="text-info"><?= number_format((float) $pm['depense_moyenne_mensuelle'], 2, ',', ' ') ?>
                                €</strong></span>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Bloc Total Mobile -->
            <div class="p-3 rounded border border-info bg-dark bg-opacity-75 text-info">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold">TOTAL PÉRIODE</span>
                    <span class="fw-bold"><?= (int) $totalEcheancesTableau ?> échéances</span>
                </div>
                <div class="d-flex justify-content-between text-small pt-1 border-top border-info opacity-75">
                    <span>Total : <strong><?= number_format((float) $totalDepenseTableau, 2, ',', ' ') ?> €</strong></span>
                    <span>Moy. / mois :
                        <strong><?= number_format($nbMoisPeriode > 0 ? ($totalDepenseTableau / $nbMoisPeriode) : 0, 2, ',', ' ') ?>
                            €</strong></span>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="text-center text-white-50 py-3">Aucune donnée disponible</div>
    <?php endif; ?>
</div>

                </div>
            </div>

            <!-- Table 2 : Dépenses mensuelles par catégorie -->
            <div class="col-md-6 mb-4">
                <div class="glass-card-nav p-4 h-100">
                    <h6 class="text-white-50 text-uppercase mb-3 small fw-bold">📊 Dépenses mensuelles par catégorie</h6>
                    
                    <!-- 1. VERSION ORDINATEUR (Tableau classique inchangé) -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
                            <thead>
                                <tr class="text-white-50 small">
                                    <th>Catégorie</th>
                                    <th class="text-center">Mois actifs</th>
                                    <th class="text-end">Moy. / mois</th>
                                    <th class="text-end">Total cumulé</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($listeDepensesCategories)): ?>
                                    <?php foreach ($listeDepensesCategories as $dc): ?>
                                        <tr>
                                            <td>
                                                <span class="badge rounded-pill me-1" style="background-color: <?= htmlspecialchars($dc['couleur'] ?? '#6c757d', ENT_QUOTES, 'UTF-8') ?>;">&nbsp;</span>
                                                <span class="fw-bold text-white"><?= htmlspecialchars($dc['cat_nom'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                            <td class="text-center"><?= (int) $dc['nb_mois_actifs'] ?></td>
                                            <td class="text-end fw-bold text-warning">
                                                <?= number_format((float) $dc['moyenne_mensuelle'], 2, ',', ' ') ?> €
                                            </td>
                                            <td class="text-end"><?= number_format((float) $dc['total_cumule'], 2, ',', ' ') ?> €</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-white-50 py-3">Aucune donnée disponible</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- 2. VERSION MOBILE (Mini-cartes empilées) -->
                    <div class="d-block d-md-none">
    <?php if (!empty($listeDepensesCategories)): ?>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($listeDepensesCategories as $dc): ?>
                <div class="p-3 rounded border border-secondary bg-dark bg-opacity-50">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span class="badge rounded-pill me-1"
                                style="background-color: <?= htmlspecialchars($dc['couleur'] ?? '#6c757d', ENT_QUOTES, 'UTF-8') ?>;">&nbsp;</span>
                            <span
                                class="fw-bold text-white fs-6"><?= htmlspecialchars($dc['cat_nom'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <span class="badge bg-secondary text-white"><?= (int) $dc['nb_mois_actifs'] ?> mois actifs</span>
                    </div>
                    <div class="d-flex justify-content-between text-small text-white-50 border-top pt-2 mt-2">
                        <span>Moy. / mois : <strong
                                class="text-warning"><?= number_format((float) $dc['moyenne_mensuelle'], 2, ',', ' ') ?>
                                €</strong></span>
                        <span>Total : <strong class="text-white"><?= number_format((float) $dc['total_cumule'], 2, ',', ' ') ?>
                                €</strong></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center text-white-50 py-3">Aucune donnée disponible</div>
    <?php endif; ?>
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


/* =====================================================
   LIGNES DE LIAISON DONUT G1
====================================================== */

const donutLabelsLine = {

    id: 'donutLabelsLine',

    afterDraw(chart) {

        if (chart.config.type !== 'doughnut') return;


        const { ctx } = chart;

        const meta = chart.getDatasetMeta(0);


        meta.data.forEach((arc) => {

            const angle = (arc.startAngle + arc.endAngle) / 2;


            const startX =
                arc.x + Math.cos(angle) * arc.outerRadius;

            const startY =
                arc.y + Math.sin(angle) * arc.outerRadius;


            const endX =
                arc.x + Math.cos(angle) * (arc.outerRadius + 25);

            const endY =
                arc.y + Math.sin(angle) * (arc.outerRadius + 25);



            ctx.save();

            ctx.beginPath();

            ctx.moveTo(startX, startY);

            ctx.lineTo(endX, endY);


            ctx.strokeStyle = '#FFFFFF';

            ctx.lineWidth = 1;


            ctx.stroke();

            ctx.restore();

        });

    }
};


Chart.register(donutLabelsLine);



/* =====================================================
   OPTIONS COMMUNES AUX GRAPHIQUES
====================================================== */

const baseOptions = {

    responsive: true,

    maintainAspectRatio: false,


    plugins: {

        legend: {

            position: 'right',

            labels: {

                boxWidth: 12,

                padding: 15,

                color: '#FFFFFF',

                font: {

                    size: 12

                },


                generateLabels: function (chart) {

                    const data = chart.data;

                    const dataset = data.datasets[0].data;


                    const total = dataset.reduce(
                        (a, b) => a + (parseFloat(b) || 0),
                        0
                    );


                    return data.labels.map((label, i) => {


                        const value = dataset[i];


                        const percentage = total > 0

                            ? ((value * 100) / total).toFixed(1)

                            : 0;



                       return {

    text: label + ' : ' + percentage + ' %',

    fillStyle: data.datasets[0].backgroundColor[i],

    strokeStyle: data.datasets[0].backgroundColor[i],

    lineWidth: 1,

    hidden: false,

    index: i,

    fontColor: '#FFFFFF'
 }; }); } } },
  


        datalabels: {

            color: '#FFFFFF',

            font: {

                weight: 'bold',

                size: 12

            },


            textStrokeColor: '#000000',

            textStrokeWidth: 2,


            formatter: (value) => {

                return value > 0

                    ? value.toLocaleString() + ' €'

                    : '';

            }

        }

    }

};



/* =====================================================
   G1 : DONUT CATÉGORIES
====================================================== */

new Chart(document.getElementById('chartG1'), {

    type: 'doughnut',


    data: {

        labels: <?= json_encode(array_column($resG1, 'nom')) ?>,


        datasets: [{

            data: <?= json_encode(array_column($resG1, 'total')) ?>,


            backgroundColor: <?= json_encode(array_column($resG1, 'couleur')) ?>,


            borderWidth: 2

        }]

    },


    options: {


        ...baseOptions,


       layout: {
    padding: {
        top: 40,
        bottom: 20,
        left: 20,
        right: 40
    }
},


        plugins: {


            ...baseOptions.plugins,


            datalabels: {

                display: true,


                anchor: 'end',

                align: 'end',

                offset: 25,


                formatter: (value, ctx) => {


                    let dataset = ctx.dataset.data;


                    let sum = dataset.reduce(

                        (a, b) => a + (parseFloat(b) || 0),

                        0

                    );


                    if (sum === 0) return "";


                    return ((value * 100) / sum).toFixed(1) + "%";

                }

            }

        }

    }

});


        /* =====================================================
           G2 : HISTORIQUE ANNUEL
        ====================================================== */

        new Chart(document.getElementById('chartG2'), {

            type: 'bar',

            data: {

                labels: <?= json_encode(array_column($resG2, 'annee')) ?>,

                datasets: [{

                    label: 'Total annuel',

                    data: <?= json_encode(array_column($resG2, 'total')) ?>,

                    backgroundColor: '#0d6efd',

                    borderRadius: 5

                }]
            },


            options: {

                ...baseOptions,

                plugins: {

                    ...baseOptions.plugins,

                    legend: {
                        display: false
                    },


                    datalabels: {

                        anchor: 'end',

                        align: 'top',

                        color: '#444',

                        textStrokeWidth: 0,


                        formatter: (value) =>
                            value.toLocaleString() + ' €'

                    }
                },


                scales: {

                    y: {

                        beginAtZero: true,

                        grid: {
                            drawBorder: false
                        }
                    }
                }
            }
        });



        /* =====================================================
           G3 : FLUX MENSUEL
        ====================================================== */

        new Chart(document.getElementById('chartG3'), {

            type: 'line',

            data: {

                labels: <?= json_encode(array_column($resG3, 'mois')) ?>,

                datasets: [{

                    label: 'Flux €',

                    data: <?= json_encode(array_column($resG3, 'total')) ?>,

                    borderColor: '#ffc107',

                    backgroundColor: 'rgba(255,193,7,0.1)',

                    fill: true,

                    tension: 0.3,

                    pointRadius: 4

                }]
            },


            options: {

                ...baseOptions,

                plugins: {

                    ...baseOptions.plugins,

                    legend: {
                        display: false
                    },


                    datalabels: {
                        display: false
                    }
                }
            }
        });



        /* =====================================================
           G4 : TOP 5 MARCHANDS
        ====================================================== */

        new Chart(document.getElementById('chartG4'), {

            type: 'bar',

            data: {

                labels: <?= json_encode(array_column($resG4, 'nom_marchand')) ?>,

                datasets: [{

                    label: 'Dépense totale',

                    data: <?= json_encode(array_column($resG4, 'total')) ?>,

                    backgroundColor: '#6c757d',

                    borderRadius: 5

                }]
            },


            options: {

                ...baseOptions,

                indexAxis: 'y',

                plugins: {

                    ...baseOptions.plugins,


                    legend: {
                        display: false
                    },


                    datalabels: {

                        anchor: 'end',

                        align: 'right',

                        color: '#444',

                        textStrokeWidth: 0,


                        formatter: (value) =>
                            value.toLocaleString() + ' €'

                    }
                },


                scales: {

                    x: {

                        beginAtZero: true,

                        grid: {
                            display: false
                        }
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
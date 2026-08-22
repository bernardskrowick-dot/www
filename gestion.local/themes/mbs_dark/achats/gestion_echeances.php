<?php
/* Version: v1.19.1 (Rev #25) - 2026-08-06 */
/**
 * gestion_echeances.php - Liste détaillée avec jauge de progression et filtres avancés
 * 
 * RÈGLES APPLIQUÉES :
 * - CSRF : Token présent sur le formulaire de paiement.
 * - Constantes : Utilisation de BASE_URL et __DIR__.
 * - Commentaires : Documentation de la logique et des sections.
 * - Sécurité : Requêtes préparées PDO pour éviter les injections SQL.
 */

// 🔒 RÈGLE : Vérification session utilisateur
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$aujourdhui = date('Y-m-d');

// -------------------------------------------------------------------------
// 1. RÉCUPÉRATION DES FILTRES ET TRIS
// -------------------------------------------------------------------------
$filtreStatut = $_GET['statut_filter'] ?? 'tous';
$filtreMarchand = $_GET['marchand_filter'] ?? '';
$triFilter = $_GET['tri_filter'] ?? 'titre';

// -------------------------------------------------------------------------
// 2. RÉCUPÉRATION DE LA LISTE DES MARCHANDS (pour le menu déroulant)
// -------------------------------------------------------------------------
$stmtM = $pdo->prepare('SELECT DISTINCT nom_marchand FROM achats WHERE user_id = :uid AND nom_marchand IS NOT NULL ORDER BY nom_marchand ASC');
$stmtM->execute([':uid' => $userId]);
$listeMarchands = $stmtM->fetchAll(PDO::FETCH_COLUMN);

// -------------------------------------------------------------------------
// 3. CALCUL DES COMPTEURS D'ÉTAT GLOBAUX
// -------------------------------------------------------------------------
$sqlStats = "
    SELECT 
        SUM(CASE WHEN statut = 'payee' THEN 1 ELSE 0 END) as soldées,
        SUM(CASE WHEN date_echeance < :aujourdhui AND statut != 'payee' THEN 1 ELSE 0 END) as en_retard,
        SUM(CASE WHEN date_echeance >= :aujourdhui AND statut != 'payee' THEN 1 ELSE 0 END) as a_venir
    FROM echeances e
    JOIN achats a ON e.achat_id = a.id
    WHERE a.user_id = :user_id
";
$stmtStats = $pdo->prepare($sqlStats);
$stmtStats->execute([':user_id' => $userId, ':aujourdhui' => $aujourdhui]);
$statsStatus = $stmtStats->fetch(PDO::FETCH_ASSOC);

// -------------------------------------------------------------------------
// 4. CONSTRUCTION DES CONDITIONS DE RECHERCHE (WHERE)
// -------------------------------------------------------------------------
$whereClauses = ['a.user_id = :user_id'];
$params = [':user_id' => $userId];

if ($filtreStatut === 'a_venir') {
    $whereClauses[] = "e.date_echeance >= :aujourdhui AND e.statut != 'payee'";
    $params[':aujourdhui'] = $aujourdhui;
} elseif ($filtreStatut === 'solde') {
    $whereClauses[] = "e.statut = 'payee'";
} elseif ($filtreStatut === 'en_retard') {
    $whereClauses[] = "e.date_echeance < :aujourdhui AND e.statut != 'payee'";
    $params[':aujourdhui'] = $aujourdhui;
}

if (!empty($filtreMarchand)) {
    $whereClauses[] = 'a.nom_marchand = :marchand';
    $params[':marchand'] = $filtreMarchand;
}

$whereSql = ' WHERE ' . implode(' AND ', $whereClauses);

// -------------------------------------------------------------------------
// 5. CALCULS GLOBAUX DES KPI (Indépendant de la pagination)
// -------------------------------------------------------------------------
$sqlTotauxGlobal = "
    SELECT 
        SUM(e.montant) AS total_gen,
        SUM(CASE WHEN e.statut = 'payee' THEN e.montant ELSE 0 END) AS total_paye_gen,
        SUM(CASE WHEN e.statut != 'payee' THEN e.montant ELSE 0 END) AS total_restant
    FROM echeances e
    JOIN achats a ON e.achat_id = a.id
    $whereSql
";
$stmtTotauxGlobal = $pdo->prepare($sqlTotauxGlobal);
$stmtTotauxGlobal->execute($params);
$totauxKpi = $stmtTotauxGlobal->fetch(PDO::FETCH_ASSOC);

$totalGen = (float) ($totauxKpi['total_gen'] ?? 0);
$totalPayeGen = (float) ($totauxKpi['total_paye_gen'] ?? 0);
$totalRestant = (float) ($totauxKpi['total_restant'] ?? 0);

$pourcentageGlobal = ($totalGen > 0) ? ($totalPayeGen / $totalGen) * 100 : 0;

// -------------------------------------------------------------------------
// 6. GESTION DE LA PAGINATION ET DES BORNES D'AFFICHAGE
// -------------------------------------------------------------------------
$parPage = 12;  // 12 cards par page
$pageCourante = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// Comptage total pour la pagination
$sqlCount = "SELECT COUNT(*) FROM echeances e JOIN achats a ON e.achat_id = a.id $whereSql";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalEcheances = $stmtCount->fetchColumn();
$totalPages = ceil($totalEcheances / $parPage);

if ($pageCourante > $totalPages && $totalPages > 0)
    $pageCourante = $totalPages;
$offset = ($pageCourante - 1) * $parPage;

// Calcul de la plage affichée sur la page courante (pour le KPI dynamique)
$debutPlage = ($totalEcheances > 0) ? $offset + 1 : 0;
$finPlage = min($offset + $parPage, $totalEcheances);

// -------------------------------------------------------------------------
// 7. REQUÊTE FINALE AVEC JOINTURES CATÉGORIES, SOUS-CATÉGORIES ET TRIS
// -------------------------------------------------------------------------
$sql = "
    SELECT e.*, a.titre, a.nom_marchand, a.id AS achat_id,
        c.nom AS nom_categorie,
        c.parent_id AS categorie_parent_id,
        parent_cat.nom AS nom_categorie_parente,
        c.couleur AS couleur_categorie,
        (SELECT COUNT(*) FROM echeances e2 WHERE e2.achat_id = a.id AND e2.date_echeance <= e.date_echeance) as rang_fixe,
        (SELECT COUNT(*) FROM echeances e3 WHERE e3.achat_id = a.id) as total_fixe,
        (SELECT SUM(m.montant) FROM echeances m WHERE m.achat_id = a.id) as total_achat,
        (SELECT SUM(m.montant) FROM echeances m WHERE m.achat_id = a.id AND m.statut = 'payee') as total_paye_achat
    FROM echeances e
    JOIN achats a ON e.achat_id = a.id
    LEFT JOIN categories c ON (a.categorie_id = c.id AND (c.user_id = a.user_id OR c.id = 1))
    LEFT JOIN categories parent_cat ON c.parent_id = parent_cat.id
    $whereSql
";

// Application du tri sélectionné
switch ($triFilter) {
    case 'marchand':
        $sql .= ' ORDER BY a.nom_marchand ASC, e.date_echeance ASC';
        break;
    case 'date_annee':
        $sql .= ' ORDER BY YEAR(e.date_echeance) ASC, e.date_echeance ASC';
        break;
    case 'date_mois':
        $sql .= ' ORDER BY MONTH(e.date_echeance) ASC, e.date_echeance ASC';
        break;
    default:
        $sql .= ' ORDER BY a.titre ASC, e.date_echeance ASC';
        break;
}

$sql .= " LIMIT $parPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$echeances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------------------------------------------------------------
// 8. RÉCUPÉRATION DES COMPTEURS DISTINCTS POUR LES KPI ACHATS ET MARCHANDS
// -------------------------------------------------------------------------
$stmtNbAchats = $pdo->prepare("SELECT COUNT(DISTINCT a.id) FROM achats a JOIN echeances e ON e.achat_id = a.id $whereSql");
$stmtNbAchats->execute($params);
$nombreAchatsDistincts = $stmtNbAchats->fetchColumn();

$stmtNbMarchands = $pdo->prepare("SELECT COUNT(DISTINCT a.nom_marchand) FROM achats a JOIN echeances e ON e.achat_id = a.id $whereSql AND a.nom_marchand IS NOT NULL");
$stmtNbMarchands->execute($params);
$nombreMarchandsDistincts = $stmtNbMarchands->fetchColumn();
?>

<div class="container mb-4 nav-dashboard-container">
    <div class="glass-card-nav mt-4 mb-0">

        <div class="d-flex flex-wrap align-items-center mt-3">
        </div>

        <div class="p-3 pt-0">
            <?php
            // Utilisation de la constante globale définie dans init.php
            if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
                include DIR_LOGIC . 'top-bar-title-page.php';
            }
            ?>
        </div>
    </div>


    <hr class="hr-glass">
<div class="row mb-2">

    <div class="d-flex flex-column flex-md-row gap-3 justify-content-center align-items-center mb-4">

        <div class="col-12 col-md-6">
            <a href="<?= BASE_URL ?>router.php?p=ajouter_achat.php"
                class="btn-payer-neon btn-neon-ac d-flex align-items-center justify-content-center w-100 text-decoration-none">
                <span class="emoji fs-4">➕</span> Ajouter un achat
            </a>
        </div>


        <div class="col-12 col-md-6">
            <a href="<?= BASE_URL ?>router.php?p=dashboard_achats.php"
                class="btn-payer-neon btn-neon-ac d-flex align-items-center justify-content-center w-100 text-decoration-none">
                <span class="emoji fs-4">🛒</span> Retour au tableau de bord Achats
            </a>
        </div>

    </div>

</div>
        <hr class="hr-glass mt-2 mb-4">
    <form method="GET" action="<?= BASE_URL ?>router.php" class="card p-3 mb-4 shadow-sm border-0 bg-dark text-white">
        <input type="hidden" name="p" value="gestion_echeances.php">

        <div class="row g-3 align-items-end">
            <!-- Statut -->
            <div class="col-md-3">
                <label class="form-label small fw-bold text-uppercase text-white-50"><span class="emoji fs-4">📌</span> Statut</label>
                <select name="statut_filter" class="form-select bg-dark text-white border-secondary">
                    <option value="tous">Tous les statuts</option>
                    <option value="a_venir" <?= ($filtreStatut == 'a_venir') ? 'selected' : '' ?>>📅 À venir</option>
                    <option value="solde" <?= ($filtreStatut == 'solde') ? 'selected' : '' ?>>✅ Soldé</option>
                    <option value="en_retard" <?= ($filtreStatut == 'en_retard') ? 'selected' : '' ?>>⚠️ En retard</option>
                </select>
            </div>

            <!-- Marchand -->
            <div class="col-md-3">
                <label class="form-label small fw-bold text-uppercase text-white-50"><span class="emoji fs-4">🏪</span> Marchand</label>
                <select name="marchand_filter" class="form-select bg-dark text-white border-secondary">
                    <option value="">Tous les marchands</option>
                    <?php foreach ($listeMarchands as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= ($filtreMarchand == $m) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Trier par -->
            <div class="col-md-3">
                <label class="form-label small fw-bold text-uppercase text-white-50"><span class="emoji fs-4">↕️</span> Trier par</label>
                <select name="tri_filter" class="form-select bg-dark text-white border-secondary">
                    <option value="titre" <?= ($triFilter == 'titre') ? 'selected' : '' ?>>Titre</option>
                    <option value="marchand" <?= ($triFilter == 'marchand') ? 'selected' : '' ?>>Marchand</option>
                    <option value="date_annee" <?= ($triFilter == 'date_annee') ? 'selected' : '' ?>>Année</option>
                    <option value="date_mois" <?= ($triFilter == 'date_mois') ? 'selected' : '' ?>>Mois</option>
                </select>
            </div>

            <!-- Boutons -->
            <div class="col-md-3 d-flex align-items-end gap-2">
                <!-- BOUTON FILTRER / APPLIQUER -->
                <button type="submit"
                    class="btn-create-dash shadow-sm px-3 text-nowrap d-inline-flex align-items-center justify-content-center w-100">
                   <span class="emoji fs-4">🔍</span> Appliquer
                </button>

                <!-- BOUTON Effacer -->
                <a href="<?= BASE_URL ?>router.php?p=gestion_echeances.php"
                    class="btn-modifier-neon shadow-sm px-3 text-nowrap text-decoration-none d-inline-flex align-items-center justify-content-center w-100"
                    style="height: 46px;" title="Réinitialiser tous les filtres">
                    <span class="emoji fs-4">🔄</span> Effacer
                </a>
            </div>
        </div>
    </form>

     <div class="card border-0 shadow-sm mb-5 p-3 bg-light" style="border-radius: 20px;">

<!-- KPI principaux -->
    <div class="row g-3 text-center mt-2 mb-3">

        <div class="col-md-4 mb-2">
            <div class="form-label card p-3 text-center shadow-sm rounded-4">
                <div class="card-compact-flex px-2">
                    <span class="echeance-label fw-bold">
                        <span class="emoji fs-4">🛒</span> Achats
                    </span>
                    <span class="echeance-valeur text-primary">
                    <?= $nombreAchatsDistincts ?>
                </span>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-2">
        <div class="form-label card p-3 text-center shadow-sm rounded-4">
            <div class="card-compact-flex px-2">
                <span class="echeance-label fw-bold">
                    <span class="emoji fs-4">🏪</span> Marchands
                </span>
                <span class="echeance-valeur text-info">
                    <?= $nombreMarchandsDistincts ?>
                </span>
            </div>
        </div>
    </div>


        <div class="col-md-4 mb-2">
    <div class="form-label card p-3 text-center shadow-sm rounded-4">
        <div class="card-compact-flex px-2">
            <span class="echeance-label fw-bold">
                <span class="emoji fs-4">📅</span> Échéances
            </span>
            <span class="echeance-valeur">
                <?= $debutPlage ?>-<?= $finPlage ?> / <?= $totalEcheances ?>
                    </span>
                </div>
            </div>
        </div>
    </div>


    <!-- KPI statuts -->
    <div class="row g-3 mb-2 justify-content-center">


        <div class="col-md-4 mb-2">
            <a href="router.php?p=gestion_echeances.php&statut_filter=solde&marchand_filter=&tri_filter=titre"
                class="text-decoration-none">

                <div class="form-label card shadow-sm glass-card-nav mt-0 p-2">

                    <div class="card-compact-flex px-2">
                        <span class="stats-global-label">
                            <span class="emoji fs-4">✅</span> Soldées
                        </span>

                        <span class="echeance-valeur text-success">
                            <?= $statsStatus['soldées'] ?? 0 ?>
                        </span>

                    </div>

                </div>

            </a>
        </div>



        <div class="col-md-4 mb-2">
            <a href="router.php?p=gestion_echeances.php&statut_filter=a_venir&marchand_filter=&tri_filter=titre"
                class="text-decoration-none">

                <div class="form-label card shadow-sm glass-card-nav mt-0 p-2">

                    <div class="card-compact-flex px-2">

                        <span class="stats-global-label">
                            <span class="emoji fs-4">⏳</span> À venir
                        </span>

                        <span class="echeance-valeur text-warning">
                            <?= $statsStatus['a_venir'] ?? 0 ?>
                        </span>

                    </div>

                </div>

            </a>
        </div>



        <div class="col-md-4 mb-2">
            <a href="router.php?p=gestion_echeances.php&statut_filter=en_retard&marchand_filter=&tri_filter=titre"
                class="text-decoration-none">

                <div class="form-label card shadow-sm glass-card-nav mt-0 p-2">

                    <div class="card-compact-flex px-2">

                        <span class="stats-global-label badge-warning-pulse h-100">
                            <span class="emoji fs-4">⚠️</span> En retard
                        </span>

                        <span class="echeance-valeur text-danger badge-warning-pulse">
                            <?= $statsStatus['en_retard'] ?? 0 ?>
                        </span>

                    </div>

                </div>

            </a>
        </div>


    </div>

</div>


    <div class="glass-card-nav border-0 shadow-sm mb-5 p-3 bg-light" style="border-radius: 20px;">
    <div class="row mt-4 mb-4 text-center">

        <div class="col-md-4">
            <div class="form-label glass-card-nav p-3 card-kpi">
                <span class="emoji fs-4">💳</span> Total général :

                <span class="info-tooltip ms-1 text-info fs-4">
                    ⓘ
                    <span class="info-tooltip-text">
                        Montant total engagé pour l'ensemble des achats pris en compte.
                        <br><br>
                        Calcul : somme de tous les montants d'achats enregistrés dans la sélection actuelle.
                    </span>
                </span>

                <?= number_format($totalGen, 2, ',', ' ') ?> €
                    </div>
        </div>


        <div class="col-md-4">
            <div class="form-label glass-card-nav p-3 card-kpi">
                <span class="emoji fs-4">✅</span> Total payé :

                <span class="info-tooltip ms-1 text-info fs-4">
                    ⓘ
                    <span class="info-tooltip-text">
                        Montant total déjà réglé sur l'ensemble des achats concernés.
                        <br><br>
                        Calcul : somme des échéances dont le statut indique qu'elles sont payées.
                    </span>
                </span>

                <?= number_format($totalPayeGen, 2, ',', ' ') ?> €
                    </div>
        </div>


        <div class="col-md-4 mb-4">

            <?php $resteGlobal = $totalGen - $totalPayeGen; ?>

            <div class="form-label glass-card-nav p-3 card-kpi <?= ($resteGlobal > 0) ? 'bg-danger' : 'bg-secondary' ?> ">

                <span class="emoji fs-4">💰</span> Reste à payer :

                <span class="info-tooltip ms-1 text-info fs-4">
                    ⓘ
                    <span class="info-tooltip-text">
                        Montant restant dû après déduction des paiements déjà effectués.
                        <br><br>
                        Calcul : Total général moins Total payé.
                    </span>
                </span>

                <?= number_format($resteGlobal, 2, ',', ' ') ?> €

            </div>

        </div>

    </div>

<div class="row">

       <div class="form-label glass-card-nav p-4">

            <div class="d-flex justify-content-between align-items-center mb-3">

                <h2 class="glass-header-small mb-0">

                    <span class="emoji">🚀</span> Niveau de remboursement global

                    <span class="info-tooltip ms-1 text-info">
                        ⓘ
                        <span class="info-tooltip-text">
                            Indique l'avancement global du remboursement des achats.
                            <br><br>
                            Calcul : montant total payé ÷ montant total dû × 100.
                        </span>
                    </span>

                </h2>


                <span class="form-label badge-neon badge-neon-amber badge-percent text-accent-amber fw-bold fs-5">
                    <?= number_format($pourcentageGlobal, 1) ?> %
                </span>

            </div>


            <div class="progress progress-custom-dark">

                <div class="progress-bar progress-bar-neon bg-neon-amber"
                    role="progressbar"
                    style="width: <?= $pourcentageGlobal ?>%"
                    aria-valuenow="<?= $pourcentageGlobal ?>"
                    aria-valuemin="0"
                    aria-valuemax="100">
                </div>

            </div>


            <div class="text-center mt-2">

                <small class="text-muted">
        Vous avez remboursé
        <strong><?= number_format($totalPayeGen, 2, ',', ' ') ?> €</strong>
                    sur un total de
                    <strong><?= number_format($totalGen, 2, ',', ' ') ?> €</strong>
                </small>

            </div>

        </div>

    </div>

</div>

    <!-- ------------------------------------------------------------------------- -->
<!-- 8. RENDU HTML / AFFICHAGE DES CARTES D'ÉCHÉANCES                         -->
<!-- ------------------------------------------------------------------------- -->
<div class="row">
    <?php
    if (empty($echeances)):
        $messageVide = match ($filtreStatut) {
            'en_retard' => '👍 Aucune échéance en retard !',
            'solde' => '💤 Aucune échéance soldée.',
            'a_venir' => '📅 Aucune échéance à venir.',
            default => '📭 Aucun résultat.'
        };
        ?>
        <div class="col-12 text-center py-5">
            <div class="alert alert-light border shadow-sm"><?= $messageVide ?></div>
        </div>
        <?php
    else:
        foreach ($echeances as $e):
            $pctAchat = ($e['total_achat'] > 0) ? ($e['total_paye_achat'] / $e['total_achat']) * 100 : 0;
            $isAchatSolde = ($pctAchat >= 100);

            // Détermination du retard et des classes de style
            $isRetard = ($e['date_echeance'] < $aujourdhui && $e['statut'] !== 'payee');
            $pulseClass = $isRetard ? 'badge-warning-pulse' : '';
            $bgClasse = ($e['statut'] === 'payee')
                ? 'border-solde'
                : (($isRetard) ? 'border-en-retard' : 'border-a-venir');
            ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="form-label card bg-glass <?= $bgClasse ?> h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title mb-1 text-white"><?= htmlspecialchars($e['titre'], ENT_QUOTES, 'UTF-8') ?></h5>
                                
                                <!-- Badge Marchand -->
                                <span class="badge bg-secondary mb-2 d-inline-block">🏪
                                    <?= htmlspecialchars($e['nom_marchand'] ?: 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                
                                <?php
                                // Logique d'affichage des catégories et sous-catégories
                                $catColor = !empty($e['couleur_categorie']) ? $e['couleur_categorie'] : '#ffffff';
                                $aUneParente = !empty($e['nom_categorie_parente']);
                                $nomAffichage = $aUneParente ? $e['nom_categorie_parente'] : (!empty($e['nom_categorie']) ? $e['nom_categorie'] : 'Sans catégorie');
                                $achatId = (int) $e['achat_id'];
                                ?>
                                
                                <!-- Conteneur des badges de catégories sous le marchand -->
                                <div class="d-flex gap-2 align-items-center flex-wrap mt-1">
                                    <!-- Badge Catégorie Principale -->
                                    <a href="<?= BASE_URL ?>router.php?p=gestion_categories.php&assign_to=<?= $achatId ?>"
                                        class="badge-dash-pill badge-cat-neon text-decoration-none" title="Changer la catégorie de cet achat"
                                        style="border-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>66; 
                                                                        background-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>11; 
                                                                        color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>;
                                                                        box-shadow: 0 0 8px <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>33;">
                                        📁 <?= htmlspecialchars($nomAffichage, ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                
                                    <!-- Badge Sous-Catégorie (si existante) -->
                                    <?php if ($aUneParente): ?>
                                        <span class="badge-dash-pill badge-cat-neon text-decoration-none"
                                            style="border-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>66; 
                                                                            background-color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>22; 
                                                                            color: <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>;
                                                                            box-shadow: 0 0 8px <?= htmlspecialchars($catColor, ENT_QUOTES, 'UTF-8') ?>33;">
                                            🏷️ <?= htmlspecialchars($e['nom_categorie'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endif; ?>
                                
                                    <!-- Alerte À Classer si non classé -->
                                    <?php if ($nomAffichage === 'Non classé' || $nomAffichage === 'Sans catégorie'): ?>
                                        <a href="<?= BASE_URL ?>router.php?p=gestion_categories.php&assign_to=<?= $achatId ?>" class="text-decoration-none"
                                            title="Cliquez pour classer cet achat">
                                            <span class="badge badge-warning-pulse">
                                                ⚠️ À CLASSER
                                            </span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                
                                </div>
                            
                            <!-- Indicateur de progression circulaire -->
                            <div class="d-flex flex-column align-items-center" style="min-width: 100px;">
                                <div class="progress-circle-custom <?= $isAchatSolde ? 'completed' : '' ?> <?= $pulseClass ?>"
                                    style="--percentage: <?= $pctAchat ?>; border-radius: 50%;">
                                    <span><?= number_format($pctAchat, 0) ?>%</span>
                                </div>
                                <div>
                                    <small class="text-muted d-block" style="font-size: 0.75rem;">Remboursement</small>
                                    <strong class="text-white"
                                        style="font-size: 0.85rem;"><?= $isAchatSolde ? '✅ Soldé' : '⏳ En cours' ?></strong>
                                    </div>
                                    </div>
                                    </div>

                        <!-- Bloc Détails Échéance -->
                        <div class="bg-glass-light p-2 rounded mb-3">
                            <p class="mb-1 text-white">📅 <strong>Échéance :</strong>
                                <?= date('d/m/Y', strtotime($e['date_echeance'])) ?>
                            </p>
                            <p class="mb-0 text-white">💰 <strong>Montant :</strong>
                                <?= number_format($e['montant'], 2, ',', ' ') ?> €
                            </p>
                        </div>

                        <!-- Rang de l'échéance -->
                        <div class="mb-3 text-center">
                            <span class="badge rounded-pill bg-glass py-2 px-3" style="font-size: 0.75rem;">
                                📌 Échéance <?= $e['rang_fixe'] ?> / <?= $e['total_fixe'] ?>
                            </span>
                        </div>

                        <!-- Formulaire de validation de paiement -->
                        <?php if ($e['statut'] !== 'payee'): ?>
                            <form method="POST" action="<?= BASE_URL ?>router.php?p=achats/payer_echeance.php">
                                <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                                <? csrf_input() ?>
                                <button class="btn btn-sm btn-success w-100 fw-bold">✔️ Marquer comme payée</button>
                            </form>
                        <?php else: ?>
                            <div class="text-center py-1">
                                <span class="badge bg-success w-100 p-2">✅ Réglée</span>
                            </div>
                        <?php endif; ?>
                        </div>
                        </div>
                        </div>
            <?php
        endforeach;
    endif;
    ?>
            </div>

    <?php
    // 1. Préparation de l'URL pour garder les filtres actifs
    $baseUrlPagination = BASE_URL . "router.php?p=gestion_echeances.php&statut_filter=$filtreStatut&marchand_filter=" . urlencode($filtreMarchand) . "&tri_filter=$triFilter";

    if ($totalPages > 1):
        ?>
        <nav class="d-flex flex-column align-items-center mt-5">
            <ul class="pagination pagination-minimal">

                <li class="page-item <?= ($pageCourante <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $baseUrlPagination ?>&page=<?= $pageCourante - 1 ?>">
                        <span style="font-size: 1.1rem;">&lsaquo;</span>
                    </a>
                </li>

                <?php
                // Affichage intelligent (Évite d'avoir 50 numéros)
                for ($i = 1; $i <= $totalPages; $i++):
                    if ($i == 1 || $i == $totalPages || ($i >= $pageCourante - 1 && $i <= $pageCourante + 1)):
                        ?>
                        <li class="page-item <?= ($i == $pageCourante ? 'active' : '') ?>">
                            <a class="page-link" href="<?= $baseUrlPagination ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php elseif ($i == $pageCourante - 2 || $i == $pageCourante + 2): ?>
                        <li class="page-item disabled"><span class="page-link border-0"
                                style="background:none !important;">...</span></li>
                    <?php endif;
                endfor; ?>

                <li class="page-item <?= ($pageCourante >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $baseUrlPagination ?>&page=<?= $pageCourante + 1 ?>">
                        <span style="font-size: 1.1rem;">&rsaquo;</span>
                    </a>
                </li>
            </ul>

            <div class="text-muted mt-3" style="font-size: 0.7rem; letter-spacing: 2px; text-transform: uppercase;">
    Page <?= $pageCourante ?> sur <?= $totalPages ?> (Total : <?= $totalEcheances ?> échéances)
            </div>
        </nav>
    <?php endif; ?>
</div>
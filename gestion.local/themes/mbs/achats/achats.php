<?php

/**
 * dashboard.php - Tableau de bord avec jauge de progression circulaire
 * VERSION : Intégration Dynamique des Classes CSS de Statut (Bordures & Badges)
 * THEME : MBS_DARK
 * RÈGLES : CSRF, Constantes, Commentaires, Hachage (Session)
 */

// Utilisation des constantes de dossier (Règle stricto sensu)
require_once __DIR__ . '/header.php';

// Récupération sécurisée des données de session
$user_role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$aujourdhui = date('Y-m-d');

// ---------------------------
// Récupération des filtres
// ---------------------------
$filtreMarchand = !empty($_GET['marchand']) ? $_GET['marchand'] : '';
$filtreStatut = !empty($_GET['statut_filter']) ? $_GET['statut_filter'] : '';
$filtreCategorie = !empty($_GET['categorie']) ? (int) $_GET['categorie'] : '';

// ---------------------------
// Achats avec totaux (Sécurité : PDO + Prepared Statements)
// ---------------------------
$sql = "
    SELECT 
        a.*,
        c.nom AS nom_categorie,
        c.couleur AS couleur_categorie,
        SUM(e.montant) AS total_echeances,
        SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS total_paye,
        COUNT(e.id) AS nb_total_echeances,
        SUM(CASE WHEN e.statut != 'payee' THEN 1 ELSE 0 END) AS nb_restantes,
        MIN(CASE WHEN e.statut != 'payee' THEN e.date_echeance END) AS prochaine_echeance
    FROM achats a
    LEFT JOIN echeances e ON a.id = e.achat_id
    /* MODIFICATION : On lie si c'est la catégorie de l'user OU si c'est la catégorie universelle (ID 1) */
    LEFT JOIN categories c ON (a.categorie_id = c.id AND (c.user_id = a.user_id OR c.id = 1))
    WHERE a.user_id = :user_id
" . ($filtreMarchand ? ' AND a.nom_marchand = :marchand' : '') . '
' . ($filtreCategorie ? ' AND a.categorie_id = :categorie' : '') . '
    GROUP BY a.id
    ORDER BY prochaine_echeance ASC
';

$params = ['user_id' => $user_id];
if ($filtreMarchand)
    $params['marchand'] = $filtreMarchand;
if ($filtreCategorie)
    $params['categorie'] = $filtreCategorie;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$achats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------
// Filtrage selon le statut (Logique PHP)
// ---------------------------
if ($filtreStatut) {
    $achats = array_filter($achats, function ($achat) use ($filtreStatut, $aujourdhui) {
        $resteAPayer = $achat['total_echeances'] - $achat['total_paye'];
        if ($filtreStatut == 'solde' && $resteAPayer <= 0)
            return true;
        if ($filtreStatut == 'en_retard' && $achat['prochaine_echeance'] && $achat['prochaine_echeance'] < $aujourdhui)
            return true;
        if ($filtreStatut == 'a_venir' && $resteAPayer > 0 && (!$achat['prochaine_echeance'] || $achat['prochaine_echeance'] >= $aujourdhui))
            return true;
        return false;
    });
}

// ---------------------------
// Totaux globaux
// ---------------------------
$stmtTotaux = $pdo->prepare("
    SELECT 
        SUM(e.montant) AS total_echeances,
        SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS total_paye,
        -- On compte ici le nombre d'échéances qui n'ont pas le statut 'payee'
        COUNT(CASE WHEN e.statut != 'payee' THEN 1 END) AS nb_reste
    FROM echeances e
    INNER JOIN achats a ON e.achat_id = a.id
    WHERE a.user_id = :user_id
");
$stmtTotaux->execute(['user_id' => $user_id]);
$totauxGlobaux = $stmtTotaux->fetch(PDO::FETCH_ASSOC);

// On récupère le nombre d'échéances restantes
$nbReste = $totauxGlobaux['nb_reste'] ?? 0;

$totalEcheances = $totauxGlobaux['total_echeances'] ?? 0;
$totalPaye = $totauxGlobaux['total_paye'] ?? 0;
$resteTotal = $totalEcheances - $totalPaye;

$pourcentageGlobal = ($totalEcheances > 0) ? ($totalPaye / $totalEcheances) * 100 : 0;

// ---------------------------
// Échéances par mois
// ---------------------------
$queryMois = "
    SELECT 
        DATE_FORMAT(e.date_echeance, '%Y-%m') AS mois,
        SUM(e.montant) AS total_mois,
        SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS paye_mois
       
       
    FROM echeances e
    INNER JOIN achats a ON e.achat_id = a.id
    WHERE a.user_id = :user_id
" . ($filtreMarchand ? ' AND a.nom_marchand = :marchand' : '') . '
    GROUP BY mois
    ORDER BY mois
';
$stmtMois = $pdo->prepare($queryMois);
$paramsMois = ['user_id' => $user_id];
if ($filtreMarchand)
    $paramsMois['marchand'] = $filtreMarchand;
$stmtMois->execute($paramsMois);
$echeancesParMois = $stmtMois->fetchAll(PDO::FETCH_ASSOC);

// --- CONFIGURATION PAGINATION CARDS ---
$parPage = 6;  // Nombre de cards par page
$pageCourante = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$totalAchatsFiltres = count($achats);
$totalPages = ceil($totalAchatsFiltres / $parPage);

// --- CONFIGURATION PAGINATION TABLEAU MOIS ---
$parPageMois = 5;  // Nombre de mois affichés par page dans le tableau
$pageCouranteMois = isset($_GET['page_m']) ? max(1, (int) $_GET['page_m']) : 1;
$totalMois = count($echeancesParMois);
$totalPagesMois = ceil($totalMois / $parPageMois);
?>
<div id="flash-container" class="container mt-3" style="position: relative; z-index: 9999;">
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success bg-success text-white border-0 shadow-lg p-3 rounded alert-dismissible fade show position-relative" role="alert">
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
    <div class="glass-card-nav mb-0">
        
       

        <div class="d-flex flex-wrap align-items-center gap-3 p-3">
    </div>

<div class="p-3 pt-0"> 
    <?php
    $titreSurcharge = 'Tableau de Bord des Achats';
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

    <div class="card bg-glass p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex gap-2 flex-wrap">
                
        <a href="<?= BASE_URL ?>router.php?p=ajouter_achat.php" class="btn btn-action-dash btn-dash-green">
            ➕ Ajouter un achat
        </a>
        
        <a href="<?= BASE_URL ?>router.php?p=historique.php" class="btn btn-action-dash btn-dash-silver">
            📜 Historique Achats
        </a>
        
        <a href="<?= BASE_URL ?>router.php?p=marchands.php" class="btn btn-action-dash btn-dash-blue">
            🏪 Mes Marchands
        </a>
        
      <a href="<?= BASE_URL ?>router.php?p=gestion_echeances.php&statut_filter=en_retard&marchand_filter=&tri_filter=titre" class="btn btn-action-dash btn-dash-orange d-inline-flex align-items-center">
    <span class="me-1">📅</span> Suivi Échéances 
    <?php if ($nbReste > 0): ?>
        <span class="badge-neon ms-2"><?= $nbReste ?></span>
    <?php endif; ?>
</a>
        
        <a href="<?= BASE_URL ?>router.php?p=statistiques.php" class="btn btn-action-dash btn-dash-blue">
            📊 Statistiques Achats
        </a>
        
        <a href="<?= BASE_URL ?>router.php?p=gestion_categories.php" class="btn btn-action-dash btn-dash-indigo">
            📁 Catégories Achats
        </a>
        <a href="<?= BASE_URL ?>router.php?p=Tableau_mensuel.php" class="btn btn-action-dash btn-dash-indigo">
            📁 Tableau mensuel
        </a>
    </div>
        </div>
        <?php
        /* */
        /** fin de Section Progressions paiements               ** */
        /* */

        /* */
        /** Section Progressions des paiements                  ** */
        /* */
        ?>

<div class="row"> 
    <div class="col-12">
        <div class="glass-card-nav p-4">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="glass-header-small mb-0">
                        <span class="emoji">🚀</span> Avancement Global des paiements
                    </h2>
                </div>
                <div>
                     <span class="badge-neon badge-neon-green badge-percent text-accent-green fw-bold fs-5">
                        <?= number_format($pourcentageGlobal, 1) ?> <span class="percent-symbol">%</span>
                    </span>
                </div>
            </div>

            <div class="progress progress-custom-dark">
        <div class="progress-bar progress-bar-neon bg-neon-green" 
                     role="progressbar" 
                     style="width: <?= $pourcentageGlobal ?>%;" 
                     aria-valuenow="<?= $pourcentageGlobal ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-2">
                <small class="text-label-muted">Début du cycle</small>
                <small class="text-label-muted">Objectif 100%</small>
            </div>

        </div>
    </div>
</div>

<?php
/* */

/** fin de Section Progressions paiements               ** */
/* */
?>
<?php
/* */
/** Section Compteurs Badges Achats                    ** */
/* */
?>

    <?php
    $totalAchats = count($achats);
    $totalRestantFiltre = array_sum(array_map(fn($a) => $a['total_echeances'] - $a['total_paye'], $achats));
    $totalEcheancesAffichees = array_sum(array_map(fn($a) => $a['nb_total_echeances'], $achats));

    $messageVide = match ($filtreStatut) {
        'en_retard' => '😊 Aucune échéance en retard !',
        'solde' => '💤 Aucun achat soldé.',
        'a_venir' => '📅 Aucun achat à venir.',
        default => '📭 Aucun achat trouvé.',
    };
    ?>

    <div class="row mb-4 text-center">
        <div class="col-md-4">
            <div class="card bg-glass p-3 card-kpi">
                <h6 class="form-label">🛒 Achats concernés</h6>
                <span class="badge-neon badge-neon-blue fs-5"><?= $totalAchats ?></span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-glass p-3 card-kpi">
                <h6 class="form-label">💰 Montant restant</h6>
                <span class="badge-neon badge-neon-blue fs-5"><?= number_format($totalRestantFiltre, 2) ?> €</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-glass p-3 card-kpi">
                <h6 class="form-label">📅 Échéances totales</h6>
                <span class="badge-neon badge-neon-blue fs-5"><?= $totalEcheancesAffichees ?></span>
            </div>
        </div>
    </div>
<?php
/* */
/** Fin Section Compteurs Badges Achats                ** */
/* */
?>
<!--******************************************* -->
<!--** Section Filtres                       ** -->
<!--******************************************* -->

        <form method="GET" action="<?= BASE_URL ?>router.php" class="d-flex gap-2">
            <input type="hidden" name="p" value="achats.php">
            <select name="marchand" class="form-select bg-dark text-white border-secondary">
                <option value="">Tous les marchands</option>
                <?php
                $marchandsStmt = $pdo->prepare('SELECT DISTINCT nom_marchand FROM achats WHERE user_id = :user_id');
                $marchandsStmt->execute(['user_id' => $user_id]);
                foreach ($marchandsStmt->fetchAll() as $m):
                    ?>
                    <option value="<?= htmlspecialchars($m['nom_marchand']) ?>" <?= ($filtreMarchand == $m['nom_marchand']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['nom_marchand']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="statut_filter" class="form-select bg-dark text-white border-secondary">
                <option value="a_venir" <?= ($filtreStatut == 'a_venir') ? 'selected' : '' ?>>📅 À venir</option>
                <option value="solde" <?= ($filtreStatut == 'solde') ? 'selected' : '' ?>>✅ Soldé</option>
                <option value="en_retard" <?= ($filtreStatut == 'en_retard') ? 'selected' : '' ?>>⚠️ En retard</option>
                <option value="" <?= empty($filtreStatut) ? 'selected' : '' ?>>Tous les statuts</option>
            </select>

            <select name="categorie" class="form-select bg-dark text-white border-secondary">
                <option value="">Toutes les catégories</option>
                <?php
                $catStmt = $pdo->query('SELECT id, nom FROM categories ORDER BY nom ASC');
                foreach ($catStmt->fetchAll() as $c):
                    ?>
                    <option value="<?= $c['id'] ?>" <?= ($filtreCategorie == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-create-dash shadow-sm flex-fill justify-content-center">
        <span class="emoji">🔍</span> Filtrer
    </button>
        </form>
    </div>

<!--******************************************* -->
<!--** Fin de Section Menu et filtres        ** -->
<!--******************************************* -->
<?php

/* */
/** Section Badges Achats                               ** */
/* */
?>
    <?php if (empty($achats)): ?>
        <div class="col-12"><div class="card bg-glass p-5 text-center text-muted fs-4"><?= $messageVide ?></div></div>
    <?php else: ?>
      <div class="row">
        <?php
        foreach ($achats as $achat):
            // 1. Détermination du statut de l'achat
            $resteAPayer = $achat['total_echeances'] - $achat['total_paye'];
            $statutAchat = ($resteAPayer <= 0) ? 'solde' : (($achat['prochaine_echeance'] && $achat['prochaine_echeance'] < $aujourdhui) ? 'en_retard' : 'a_venir');

            $pourcentageIndividuel = ($achat['total_echeances'] > 0) ? ($achat['total_paye'] / $achat['total_echeances']) * 100 : 0;
            $isCompleted = ($pourcentageIndividuel >= 100);

            // 2. Attribution des classes CSS personnalisées
            $classeBordure = match ($statutAchat) {
                'solde' => 'border-solde',
                'en_retard' => 'border-en-retard',
                default => 'border-a-venir'
            };

            $classeBadgeReste = match ($statutAchat) {
                'solde' => 'badge-solde',
                'en_retard' => 'badge-en-retard',
                default => 'badge-a-venir'
            };

            // RÈGLE : On saute l'affichage si la card n'est pas sur la page demandée
            if (!isset($compteurVisuel)) {
                $compteurVisuel = 0;
            }  // Initialisation si besoin
            $compteurVisuel++;
            if ($compteurVisuel <= (($pageCourante - 1) * $parPage) || $compteurVisuel > ($pageCourante * $parPage))
                continue;

            ?>

<?php
            /* */
            /** Section Affichage Badges Achats                     ** */
            /* */
?>

        <div class="col-md-6 mb-4">
            <div class="card bg-glass h-100 border-2 <?= $classeBordure ?> card-achat-hover">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="text-start">
                            <h5 class="text-white mb-3"><?= htmlspecialchars($achat['titre']) ?></h5>
                           <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
    
   <a href="<?= BASE_URL ?>router.php?p=marchands.php&marchand_filter=<?= urlencode($achat['nom_marchand']) ?>" 
   class="badge-dash-pill badge-marchand text-decoration-none" 
   title="Voir les achats chez <?= htmlspecialchars($achat['nom_marchand']) ?>">
     🏪 <?= htmlspecialchars($achat['nom_marchand']) ?>
</a>

<?php
            $catColor = $achat['couleur_categorie'] ?? '#ffffff';
?>
<a href="<?= BASE_URL ?>router.php?p=gestion_categories.php&assign_to=<?= (int) $achat['id'] ?>" 
   class="badge-dash-pill badge-cat-neon text-decoration-none" 
   title="Changer la catégorie de cet achat"
   style="border-color: <?= $catColor ?>66; 
          background-color: <?= $catColor ?>11; 
          color: <?= $catColor ?>;
          box-shadow: 0 0 8px <?= $catColor ?>33;">
     📁 <?= htmlspecialchars($achat['nom_categorie'] ?? 'Sans catégorie') ?>
</a>

    
    <?php if ($achat['nom_categorie'] == 'Non classé'): ?>
    <a href="<?= BASE_URL ?>router.php?p=gestion_categories.php&assign_to=<?= (int) $achat['id'] ?>" 
       class="text-decoration-none" 
       title="Cliquez pour classer cet achat">
        <span class="badge badge-warning-pulse">
            ⚠️ À CLASSER
        </span>
    </a>
<?php endif; ?>

</div>
                        </div>
                        
                        <div class="d-flex flex-column align-items-center" style="min-width: 100px;">
                            <div class="progress-circle-custom <?= $isCompleted ? 'completed' : '' ?>" 
                                 style="--percentage: <?= $pourcentageIndividuel ?>;">
                                <span><?= number_format($pourcentageIndividuel, 0) ?>%</span>
                            </div>
                            <div class="text-center mt-2">
                                <small class="text-muted d-block" style="font-size: 0.75rem;">Remboursement</small>
                                <strong class="text-white" style="font-size: 0.85rem;"><?= $isCompleted ? '✅ Soldé' : '⏳ En cours' ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="mt-2 d-flex flex-wrap gap-2">
                        <span class="badge-marchand">💰 Total : <?= number_format($achat['total_echeances'], 2) ?> €</span>
                        <span class="badge-solde">✔️ Payé : <?= number_format($achat['total_paye'], 2) ?> €</span>
                        
                        <span class="<?= $classeBadgeReste ?>">
                            <?= ($statutAchat === 'solde') ? '✅ SOLDÉ' : '💳 Reste : ' . number_format($resteAPayer, 2) . ' €' ?>
                        </span>
                    </div>

                    <div class="mt-3 d-flex flex-wrap gap-2 justify-content-start">
    <span class="badge-marchand">📅 Échéances : <?= $achat['nb_restantes'] ?> / <?= $achat['nb_total_echeances'] ?></span>
    <?php if ($achat['prochaine_echeance']): ?>
        <span class="badge-warning-pulse">⏳ Prochaine : <?= date('d/m/Y', strtotime($achat['prochaine_echeance'])) ?></span>
    <?php endif; ?>
</div>

                   <div class="mt-4 d-flex flex-column flex-md-row gap-2 w-100"> 
    <a href="<?= BASE_URL ?>router.php?p=modifier_achat.php&id=<?= $achat['id'] ?>" 
       class="btn-pill-dash w-100" 
       style="--btn-color: #ffc107; --btn-glow: rgba(255, 193, 7, 0.3);">
       <span class="emoji">✏️</span> Modifier
    </a>

    <form method="POST" action="<?= BASE_URL ?>router.php?p=supprimer_achat.php" class="w-100 m-0 p-0">
        <?php csrf_input(); ?> 
        <input type="hidden" name="id" value="<?= $achat['id'] ?>">
        
        <button type="submit" 
                class="btn btn-sm btn-danger btn-action-dash w-100" 
                onclick="return confirm('⚠️ ATTENTION...')">
            🗑️ Supprimer
        </button>
    </form>

    <a href="<?= BASE_URL ?>router.php?p=echeancier.php&id=<?= $achat['id'] ?>" 
       class="btn-pill-dash w-100" 
       style="--btn-color: #00d2ff; --btn-glow: rgba(0, 210, 255, 0.3);">
       <span class="emoji">📅</span> Échéancier
    </a>
</div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
                        </div></div>
<?php
// Pagination //

if ($totalPages > 1):
    ?>
    <nav class="d-flex flex-column align-items-center my-5">
        <ul class="pagination pagination-minimal">
            
            <li class="page-item <?= ($pageCourante <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= BASE_URL ?>router.php?p=achats.php&marchand=<?= urlencode($filtreMarchand) ?>&statut_filter=<?= $filtreStatut ?>&categorie=<?= $filtreCategorie ?>&page=<?= $pageCourante - 1 ?>">
                    <span>&lsaquo;</span>
                </a>
            </li>

            <?php
            // Affichage des numéros de page
            for ($i = 1; $i <= $totalPages; $i++):
                if ($i == 1 || $i == $totalPages || ($i >= $pageCourante - 1 && $i <= $pageCourante + 1)):
                    ?>
                <li class="page-item <?= ($i == $pageCourante ? 'active' : '') ?>">
                    <a class="page-link" href="<?= BASE_URL ?>router.php?p=achats.php&marchand=<?= urlencode($filtreMarchand) ?>&statut_filter=<?= $filtreStatut ?>&categorie=<?= $filtreCategorie ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php elseif ($i == $pageCourante - 2 || $i == $pageCourante + 2): ?>
                <li class="page-item disabled"><span class="page-link border-0" style="background:none !important;">...</span></li>
            <?php endif;
            endfor; ?>

            <li class="page-item <?= ($pageCourante >= $totalPages) ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= BASE_URL ?>router.php?p=achats.php&marchand=<?= urlencode($filtreMarchand) ?>&statut_filter=<?= $filtreStatut ?>&categorie=<?= $filtreCategorie ?>&page=<?= $pageCourante + 1 ?>">
                    <span>&rsaquo;</span>
                </a>
            </li>
        </ul>
        
        <div class="text-muted mt-2" style="font-size: 0.7rem; letter-spacing: 2px;">
            AFFICHAGE DES ACHATS <?= (($pageCourante - 1) * $parPage) + 1 ?> À <?= min($pageCourante * $parPage, $totalAchatsFiltres) ?> SUR <?= $totalAchatsFiltres ?>
        </div>
    </nav>
<?php endif; ?>
<?php
/* */

/**
 * Fin de Section Badges Achats                        **
 */
/* */
?>
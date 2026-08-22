<?php
/* Version: v1.19.1 (Rev #25) - 2026-08-06 */

/**
 * themes/mbs_dark/achats/marchands.php
 * Synthèse complète par marchand avec Graphique local et pourcentages.
 * Version conforme au Routeur et au Thème MBS_DARK avec entêtes de statut.
 */

/* 🔒 RÈGLE : Vérification session utilisateur (Sécurité supplémentaire) */
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$aujourdhui = date('Y-m-d');

/* 🔍 Récupération des filtres */
$searchMarchand = !empty($_GET['search_marchand']) ? trim($_GET['search_marchand']) : '';
$selectedMarchand = !empty($_GET['marchand_filter']) ? trim($_GET['marchand_filter']) : '';

/* 📋 Liste des marchands pour le menu déroulant */
$stmtList = $pdo->prepare('SELECT DISTINCT nom_marchand FROM achats WHERE user_id = :u ORDER BY nom_marchand ASC');
$stmtList->execute([':u' => $userId]);
$listeTousMarchands = $stmtList->fetchAll(PDO::FETCH_COLUMN);

/* 📊 Requête SQL : Analyse filtrée avec détection de retard */
$sql = "
    SELECT 
        a.nom_marchand,
        COUNT(DISTINCT a.id) as nombre_achats,
        SUM(e.montant) as montant_global,
        SUM(CASE WHEN e.statut = 'payee' THEN e.montant ELSE 0 END) as montant_paye,
        MAX(CASE WHEN e.statut != 'payee' AND e.date_echeance < :today THEN 1 ELSE 0 END) as a_du_retard
    FROM achats a
    JOIN echeances e ON a.id = e.achat_id
    WHERE a.user_id = :user_id
";

if ($selectedMarchand !== '')
    $sql .= ' AND a.nom_marchand = :selected';
if ($searchMarchand !== '')
    $sql .= ' AND a.nom_marchand LIKE :search';

$sql .= ' GROUP BY a.nom_marchand ORDER BY montant_global DESC';

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmt->bindValue(':today', $aujourdhui, PDO::PARAM_STR);
if ($selectedMarchand !== '')
    $stmt->bindValue(':selected', $selectedMarchand, PDO::PARAM_STR);
if ($searchMarchand !== '')
    $stmt->bindValue(':search', '%' . $searchMarchand . '%', PDO::PARAM_STR);
$stmt->execute();
$marchands = $stmt->fetchAll();

/* --- CONFIGURATION PAGINATION MARCHANDS --- */
$parPage = 4;  // Nombre de marchands par card
$pageCourante = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$totalMarchandsCount = count($marchands);
$totalPages = ceil($totalMarchandsCount / $parPage);
$debutCompteur = ($pageCourante - 1) * $parPage;
$finCompteur = $pageCourante * $parPage;
$indexM = 0;  // Pour suivre la position dans la boucle

/* Calcul des totaux contextuels */
$totalDepenseGlobal = 0;
$totalPayeGlobal = 0;
foreach ($marchands as $m) {
    $totalDepenseGlobal += (float) $m['montant_global'];
    $totalPayeGlobal += (float) $m['montant_paye'];
}
$resteGlobal = $totalDepenseGlobal - $totalPayeGlobal;
?>
<div class="container mb-4 nav-dashboard-container">
    <?php
    /** ==========================================================================
     *  Section Menu Administrateur et Titre de la page
     *  ========================================================================== */
    require_once DIR_INCLUDES . 'components/admin_header_nav.php';

    /** ==========================================================================
     *  Fin de Section Admin titre
     *  ========================================================================== */

    /** ==========================================================================
     *  Début d'affichage de la page
     *  ========================================================================== */
    ?>
    <hr class="hr-glass">

    <div class="row g-3 mb-4 text-center">

        <div class="col-lg-3 col-6">
            <div class="form-label glass-card-nav card-kpi h-100">
                <div class="text-uppercase small fw-bold mb-1" style="color: var(--accent-blue);">
                    <span class="emoji fs-4">💳</span> Engagé

                    <span class="info-tooltip ms-1 text-info fs-4">
                        ⓘ
                        <span class="info-tooltip-text">
                            Montant total des dépenses engagées liées aux achats enregistrés.
                            <br><br>
                            Calcul : somme des montants totaux des achats pris en compte dans les critères actuels.
                        </span>
                    </span>

                </div>

                <div class="fs-4 fw-bold text-white">
                    <?= number_format($totalDepenseGlobal, 2, ',', ' ') ?> €
                </div>
            </div>
        </div>


        <div class="col-lg-3 col-6">
            <div class="form-label glass-card-nav card-kpi h-100">
                <div class="text-uppercase small fw-bold mb-1" style="color: #10b981;">
                    <span class="emoji fs-4">✅</span> Payé

                    <span class="info-tooltip ms-1 text-info fs-4">
                        ⓘ
                        <span class="info-tooltip-text">
                            Montant total des dépenses déjà réglées parmi les achats enregistrés.
                            <br><br>
                            Calcul : somme des échéances dont le statut indique qu'elles sont payées.
                        </span>
                    </span>

                </div>

                <div class="fs-4 fw-bold text-white">
                    <?= number_format($totalPayeGlobal, 2, ',', ' ') ?> €
                </div>
            </div>
        </div>


        <div class="col-lg-3 col-6">
            <div class="form-label glass-card-nav card-kpi h-100">
                <div class="text-uppercase small fw-bold mb-1"
                    style="color: <?= ($resteGlobal > 0 ? '#ef4444' : 'var(--text-muted)') ?>;">

                    <span class="emoji fs-4">💰</span> Reste

                    <span class="info-tooltip ms-1 text-info fs-4">
                        ⓘ
                        <span class="info-tooltip-text">
                            Montant restant à payer sur l'ensemble des dépenses engagées.
                            <br><br>
                            Calcul : total engagé moins total déjà payé.
                        </span>
                    </span>

                </div>

                <div class="fs-4 fw-bold text-white">
                    <?= number_format($resteGlobal, 2, ',', ' ') ?> €
                </div>
            </div>
        </div>


        <div class="col-lg-3 col-6">
            <div class="form-label glass-card-nav card-kpi h-100">
                <div class="text-uppercase small fw-bold mb-1" style="color: var(--accent-blue);">

                    <span class="emoji fs-4">🏪</span> Marchands

                    <span class="info-tooltip ms-1 text-info fs-4">
                        ⓘ
                        <span class="info-tooltip-text">
                            Nombre de marchands associés aux achats actuellement pris en compte.
                            <br><br>
                            Calcul : comptage des marchands distincts présents dans la sélection.
                        </span>
                    </span>

                </div>

                <div class="fs-4 fw-bold text-white">
                    <?= count($marchands) ?>
                    <span class="small fw-normal text-muted" style="font-size: 0.7rem;">
                        <?= count($marchands) > 1 ? 'ACTIFS' : 'ACTIF' ?>
                    </span>
                </div>
            </div>
        </div>

        <hr class="hr-glass mt-4 mb-4">
        <a href="<?= BASE_URL ?>router.php?p=dashboard_achats.php" class="btn btn-secondary shadow-sm">
            <span class="emoji fs-4">🛒</span> Retour au Tableau de bord Achats
        </a>
    </div>

    <hr class="hr-glass mt-0 mb-4">

    <div class="row g-4 mb-5">
        <div class="col-lg-4">
            <div class="card h-100 p-4 shadow-sm border-0">
                <h5 class="fw-bold mb-4" style="color: var(--accent-blue);"><span class="emoji fs-4">🔍</span> 🔍 Filtres</h5>
                <form method="GET" action="router.php">
                    <input type="hidden" name="p" value="marchands">
                    <div class="mb-4">
                        <label class="form-label">Sélectionner un marchand :</label>
                        <select name="marchand_filter" class="form-select shadow-none">
                            <option value="">-- Tous les marchands --</option>
                            <?php foreach ($listeTousMarchands as $nom): ?>
                                <option value="<?= htmlspecialchars($nom) ?>" <?= ($selectedMarchand === $nom) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($nom) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Recherche libre :</label>
                        <input type="text" name="search_marchand" class="form-control shadow-none"
                            placeholder="Taper un nom..." value="<?= htmlspecialchars($searchMarchand) ?>">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn-create-dash shadow-sm fw-bold">Appliquer</button>
                        <a href="router.php?p=marchands"
                            class="btn btn-secondary shadow-sm text-decoration-none fw-bold">
                            <span class="emoji fs-4">🧹</span> Réinitialiser
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card h-100 p-4 shadow-sm border-0">
                <h5 class="fw-bold mb-4" style="color: var(--accent-purple);"><span class="emoji fs-4">📊</span> Répartition des dépenses</h5>
                <div style="max-height: 250px; position: relative;">
                    <canvas id="marchandChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <?php
        foreach ($marchands as $m):
            // RÈGLE : Pagination - on saute les éléments hors page
            $indexM++;
            if ($indexM <= $debutCompteur || $indexM > $finCompteur)
                continue;
            $resteM = $m['montant_global'] - $m['montant_paye'];
            $pctM = ($m['montant_global'] > 0) ? ($m['montant_paye'] / $m['montant_global']) * 100 : 0;

            // Logique de couleur réimportée du thème original
            if ($m['a_du_retard'] == 1) {
                $statusLabel = '<span class="emoji fs-4">⚠️</span> RETARD';
                $accentColor = '#ef4444';
                $headerBg = 'rgba(239, 68, 68, 0.62)';
            } elseif ($resteM <= 0) {
                $statusLabel = '<span class="emoji fs-4">✅</span> SOLDÉ';
                $accentColor = '#10b981';
                $headerBg = 'rgba(16, 185, 129, 0.15)';
            } else {
                $statusLabel = '<span class="emoji fs-4">📅</span> EN COURS';
                $accentColor = '#fbbf24';
                $headerBg = 'rgba(251, 190, 36, 0.69)';
            }
        ?>
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <div class="card h-100 bg-glass"
                    style="padding: 0 !important; overflow: hidden; border: 1px solid <?= $accentColor ?>55 !important;">
                    <div class="px-3 py-2 small fw-bold text-center"
                        style="background: <?= $headerBg ?>; color: <?= $accentColor ?>; border-bottom: 1px solid <?= $accentColor ?>33;">
                        <?= $statusLabel ?>
                    </div>

                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-1 text-white"><?= htmlspecialchars($m['nom_marchand']) ?></h5>
                        <span class="text-muted small d-block mb-3"><?= $m['nombre_achats'] ?> achat(s)</span>

                        <div class="progress mb-3">
                            <div class="progress-bar"
                                style="width: <?= $pctM ?>%; background: <?= $accentColor ?>; box-shadow: 0 0 10px <?= $accentColor ?>66;">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-4 small">
                            <span class="text-muted">Reste :</span>
                            <span class="fw-bold" style="color: <?= ($resteM > 0 ? '#ffffff' : '#10b981') ?>;">
                                <?= number_format($resteM, 2, ',', ' ') ?> €
                            </span>
                        </div>

                        <div class="d-grid">
                            <a href="router.php?p=historique&marchand_filter=<?= urlencode($m['nom_marchand']) ?>&search_marchand="
                                class="btn-payer-neon text-decoration-none text-center py-2"
                                style="border-color: <?= $accentColor ?> !important; color: <?= $accentColor ?> !important;">
                                <span class="emoji fs-4">🔎</span> Détails
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="text-center mt-5 mb-5">
        <?php if ($totalPages > 1): ?>
            <nav class="d-flex justify-content-center mb-4">
                <ul class="pagination pagination-minimal">
                    <?php
                    for ($i = 1; $i <= $totalPages; $i++):
                        // On garde les filtres actifs dans le lien
                        $urlParams = "router.php?p=marchands&page=$i";
                        if (!empty($selectedMarchand))
                            $urlParams .= '&marchand_filter=' . urlencode($selectedMarchand);
                        if (!empty($searchMarchand))
                            $urlParams .= '&search_marchand=' . urlencode($searchMarchand);
                    ?>
                        <li class="page-item <?= ($i == $pageCourante) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $urlParams ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>


    </div>
</div>

<script src="<?= BASE_URL ?>js/chart.umd.min.js?v=1.2"></script>
<script src="<?= BASE_URL ?>js/chartjs-plugin-datalabels.min.js?v=1.2"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (typeof ChartDataLabels !== 'undefined') {
            Chart.register(ChartDataLabels);
        }

        const ctx = document.getElementById('marchandChart').getContext('2d');
        const labels = <?= json_encode(array_column($marchands, 'nom_marchand')) ?>;
        const rawValues = <?= json_encode(array_column($marchands, 'montant_global')) ?>;
        const values = rawValues.map(v => parseFloat(v) || 0);

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: [
                        '#38bdf8', '#818cf8', '#fbbf24', '#ef4444', '#6610f2', '#fd7e14', '#10b981', '#0dcaf0'
                    ],
                    borderWidth: 0,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            color: '#cbd5e1',
                            font: {
                                size: 11
                            }
                        }
                    },
                    datalabels: {
                        display: true,
                        color: '#fff',
                        font: {
                            weight: 'bold',
                            size: 12
                        },
                        formatter: (value, context) => {
                            const dataPoints = context.chart.data.datasets[0].data;
                            const totalSum = dataPoints.reduce((a, b) => a + b, 0);
                            if (totalSum === 0) return null;
                            const percentage = (value * 100 / totalSum).toFixed(1) + "%";
                            return (value * 100 / totalSum) > 5 ? percentage : null;
                        }
                    }
                }
            }
        });
    });
</script>
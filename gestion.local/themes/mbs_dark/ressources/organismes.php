<?php
/* Version: v1.0.2 (Rev #3) - 2026-08-18 */

/**
 * themes/mbs_dark/achats/organismes.php
 * Synthèse complète par organisme basée sur les tables ressources et versements.
 */

/* 🔒 RÈGLE : Vérification session utilisateur (Sécurité supplémentaire) */
if (empty($_SESSION['user_id'])) {
  header('Location: ' . BASE_URL . 'login.php');
  exit;
}

$userId = $_SESSION['user_id'];
$aujourdhui = date('Y-m-d');

/* 🔍 Récupération des filtres */
$searchOrganisme = !empty($_GET['search_organisme']) ? trim($_GET['search_organisme']) : '';
$selectedOrganisme = !empty($_GET['organisme_filter']) ? trim($_GET['organisme_filter']) : '';

/* 📋 Liste des organismes pour le menu déroulant */
$stmtList = $pdo->prepare('SELECT DISTINCT organisme FROM ressources WHERE user_id = :u AND deleted_at IS NULL ORDER BY organisme ASC');
$stmtList->execute([':u' => $userId]);
$listeTousOrganismes = $stmtList->fetchAll(PDO::FETCH_COLUMN);

/* 📊 Requête SQL : Analyse par organisme avec liaison sur les versements */
$sql = "
    SELECT 
        r.organisme as nom_organisme,
        COUNT(DISTINCT r.id) as nombre_ressources,
        COALESCE(SUM(v.montant_prevu), 0) as montant_global,
        COALESCE(SUM(CASE WHEN v.statut = 'percu' THEN COALESCE(v.montant_reel, v.montant_prevu) ELSE 0 END), 0) as montant_percu
    FROM ressources r
    LEFT JOIN versements v ON r.id = v.ressource_id AND v.deleted_at IS NULL
    WHERE r.user_id = :user_id AND r.deleted_at IS NULL
";

if ($selectedOrganisme !== '')
  $sql .= ' AND r.organisme = :selected';
if ($searchOrganisme !== '')
  $sql .= ' AND r.organisme LIKE :search';

$sql .= ' GROUP BY r.organisme ORDER BY montant_global DESC';

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
if ($selectedOrganisme !== '')
  $stmt->bindValue(':selected', $selectedOrganisme, PDO::PARAM_STR);
if ($searchOrganisme !== '')
  $stmt->bindValue(':search', '%' . $searchOrganisme . '%', PDO::PARAM_STR);
$stmt->execute();
$organismes = $stmt->fetchAll();

/* --- CONFIGURATION PAGINATION ORGANISMES --- */
$parPage = 4;  // Nombre d'organismes par card
$pageCourante = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$totalOrganismesCount = count($organismes);
$totalPages = ceil($totalOrganismesCount / $parPage);
$debutCompteur = ($pageCourante - 1) * $parPage;
$finCompteur = $pageCourante * $parPage;
$indexO = 0;  // Pour suivre la position dans la boucle

/* Calcul des totaux contextuels globaux (KPI) */
$totalPrevuGlobal = 0;   // À percevoir (Total global)
$totalPercuGlobal = 0;   // Total déjà perçu
foreach ($organismes as $o) {
  $totalPrevuGlobal += (float) $o['montant_global'];
  $totalPercuGlobal += (float) $o['montant_percu'];
}
$resteAPercevoirGlobal = $totalPrevuGlobal - $totalPercuGlobal;
?>
<div class="container mb-4 nav-dashboard-container">
  <?php
  /** ==========================================================================
   *  Section Menu Administrateur et Titre de la page
   *  ========================================================================== */
  require_once DIR_INCLUDES . 'components/admin_header_nav.php';
  ?>
  <hr class="hr-glass">

  <div class="row g-3 mb-4 text-center">

    <div class="col-lg-3 col-6">
      <div class="form-label glass-card-nav card-kpi h-100">
        <div class="text-uppercase small fw-bold mb-1" style="color: #10b981;">
          <span class="emoji fs-4">✅</span> Total Perçu

          <span class="info-tooltip ms-1 text-info fs-4">
            ⓘ
            <span class="info-tooltip-text">
              Montant total des versements effectivement perçus.
            </span>
          </span>
        </div>

        <div class="fs-4 fw-bold text-white">
          <?= number_format($totalPercuGlobal, 2, ',', ' ') ?> €
        </div>
      </div>
    </div>

    <div class="col-lg-3 col-6">
      <div class="form-label glass-card-nav card-kpi h-100">
        <div class="text-uppercase small fw-bold mb-1" style="color: var(--accent-blue);">
          <span class="emoji fs-4">📋</span> À Percevoir

          <span class="info-tooltip ms-1 text-info fs-4">
            ⓘ
            <span class="info-tooltip-text">
              Montant total global prévu (tous versements confondus).
            </span>
          </span>
        </div>

        <div class="fs-4 fw-bold text-white">
          <?= number_format($totalPrevuGlobal, 2, ',', ' ') ?> €
        </div>
      </div>
    </div>

    <div class="col-lg-3 col-6">
      <div class="form-label glass-card-nav card-kpi h-100">
        <div class="text-uppercase small fw-bold mb-1"
          style="color: <?= ($resteAPercevoirGlobal > 0 ? '#fbbf24' : 'var(--text-muted)') ?>;">

          <span class="emoji fs-4">⏳</span> Reste à Percevoir

          <span class="info-tooltip ms-1 text-info fs-4">
            ⓘ
            <span class="info-tooltip-text">
              Montant restant à recevoir (Total prévu moins total déjà perçu).
            </span>
          </span>
        </div>

        <div class="fs-4 fw-bold text-white">
          <?= number_format($resteAPercevoirGlobal, 2, ',', ' ') ?> €
        </div>
      </div>
    </div>

    <div class="col-lg-3 col-6">
      <div class="form-label glass-card-nav card-kpi h-100">
        <div class="text-uppercase small fw-bold mb-1" style="color: var(--accent-blue);">

          <span class="emoji fs-4">🏛️</span> Organismes

          <span class="info-tooltip ms-1 text-info fs-4">
            ⓘ
            <span class="info-tooltip-text">
              Nombre d'organismes associés aux ressources actuellement prises en compte.
            </span>
          </span>
        </div>

        <div class="fs-4 fw-bold text-white">
          <?= count($organismes) ?>
          <span class="small fw-normal text-muted" style="font-size: 0.7rem;">
            <?= count($organismes) > 1 ? 'ACTIFS' : 'ACTIF' ?>
          </span>
        </div>
      </div>
    </div>

  </div>

  <hr class="hr-glass mt-4 mb-4">
  <a href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php" class="btn btn-secondary shadow-sm mb-4">
    <span class="emoji fs-4">💰</span> Retour au Tableau de bord Ressources
  </a>

  <hr class="hr-glass mt-0 mb-4">

  <div class="row g-4 mb-5">
    <div class="col-lg-4">
      <div class="card h-100 p-4 shadow-sm border-0">
        <h5 class="fw-bold mb-4" style="color: var(--accent-blue);"><span class="emoji fs-4">🔍</span> Filtres</h5>
        <form method="GET" action="router.php">
          <input type="hidden" name="p" value="organismes">
          <div class="mb-4">
            <label class="form-label">Sélectionner un organisme :</label>
            <select name="organisme_filter" class="form-select shadow-none">
              <option value="">-- Tous les organismes --</option>
              <?php foreach ($listeTousOrganismes as $nom): ?>
                <option value="<?= htmlspecialchars($nom) ?>" <?= ($selectedOrganisme === $nom) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($nom) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-4">
            <label class="form-label">Recherche libre :</label>
            <input type="text" name="search_organisme" class="form-control shadow-none"
              placeholder="Taper un nom..." value="<?= htmlspecialchars($searchOrganisme) ?>">
          </div>
          <div class="d-grid gap-2">
            <button type="submit" class="btn-create-dash shadow-sm fw-bold">Appliquer</button>
            <a href="router.php?p=organismes"
              class="btn btn-secondary shadow-sm text-decoration-none fw-bold">
              <span class="emoji fs-4">🧹</span> Réinitialiser
            </a>
          </div>
        </form>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card h-100 p-4 shadow-sm border-0">
        <h5 class="fw-bold mb-4" style="color: var(--accent-purple);"><span class="emoji fs-4">📊</span> Répartition par organisme</h5>
        <div style="max-height: 250px; position: relative;">
          <canvas id="organismeChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <?php
    foreach ($organismes as $o):
      $indexO++;
      if ($indexO <= $debutCompteur || $indexO > $finCompteur)
        continue;
      $montantGlobalO = (float) $o['montant_global'];
      $montantPercuO = (float) $o['montant_percu'];
      $pctO = ($montantGlobalO > 0) ? ($montantPercuO / $montantGlobalO) * 100 : 0;

      if ($pctO >= 100) {
        $statusLabel = '<span class="emoji fs-4">✅</span> SOLDÉ / PERÇU';
        $accentColor = '#10b981';
        $headerBg = 'rgba(16, 185, 129, 0.15)';
      } else {
        $statusLabel = '<span class="emoji fs-4">⚠️</span> EN COURS';
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
            <h5 class="fw-bold mb-1 text-white"><?= htmlspecialchars($o['nom_organisme']) ?></h5>
            <span class="text-muted small d-block mb-3"><?= $o['nombre_ressources'] ?> ressource(s)</span>

            <div class="progress mb-3">
              <div class="progress-bar"
                style="width: <?= min(100, $pctO) ?>%; background: <?= $accentColor ?>; box-shadow: 0 0 10px <?= $accentColor ?>66;">
              </div>
            </div>

            <div class="d-flex justify-content-between mb-2 small">
              <span class="text-muted">Perçu :</span>
              <span class="fw-bold text-success">
                <?= number_format($montantPercuO, 2, ',', ' ') ?> €
              </span>
            </div>
            <div class="d-flex justify-content-between mb-4 small">
              <span class="text-muted">Global :</span>
              <span class="fw-bold text-white">
                <?= number_format($montantGlobalO, 2, ',', ' ') ?> €
              </span>
            </div>

            <div class="d-grid">
              <a href="router.php?p=ressources&organisme_filter=<?= urlencode($o['nom_organisme']) ?>&search_organisme="
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
            $urlParams = "router.php?p=organismes&page=$i";
            if (!empty($selectedOrganisme))
              $urlParams .= '&organisme_filter=' . urlencode($selectedOrganisme);
            if (!empty($searchOrganisme))
              $urlParams .= '&search_organisme=' . urlencode($searchOrganisme);
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

    const ctx = document.getElementById('organismeChart').getContext('2d');
    const labels = <?= json_encode(array_column($organismes, 'nom_organisme')) ?>;
    const rawValues = <?= json_encode(array_column($organismes, 'montant_global')) ?>;
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
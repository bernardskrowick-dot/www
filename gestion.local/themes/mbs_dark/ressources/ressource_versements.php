<?php
/* Version: v1.21.0 (Rev #13) - 2026-08-08 */

/**
 * ressource_versements.php
 * Version 3.1 - Vue détaillée des versements d'une ressource
 * + Filtrage mois/année via selects
 * Module : Gestion des ressources financières
 */

// 🔒 Sécurité
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// 📅 Date du jour
$aujourdhui = new DateTime(date('Y-m-d'));

// 🔹 ID ressource
$ressourceId = isset($_GET['id']) && ctype_digit($_GET['id'])
    ? (int) $_GET['id']
    : null;

if (!$ressourceId) {
    header('Location: ' . BASE_URL . 'router.php?p=dashboard.php');
    exit;
}

// 🔹 Ressource
$stmtR = $pdo->prepare('SELECT * FROM ressources WHERE id = ? AND user_id = ?');
$stmtR->execute([$ressourceId, $userId]);
$ressource = $stmtR->fetch(PDO::FETCH_ASSOC);

if (!$ressource) {
    echo "<div class='alert alert-danger'>Ressource introuvable.</div>";
    exit;
}

/* =========================================================
   📅 FILTRE MOIS / ANNÉE (SELECT)
   - vide = tout afficher
========================================================= */

$moisSelected = $_GET['mois'] ?? '';
$anneeSelected = $_GET['annee'] ?? '';

/* =========================================================
   📅 ANNÉES DISPONIBLES POUR LES VERSEMENTS
   - uniquement les années existantes en base
========================================================= */

$stmtAnnees = $pdo->prepare("
    SELECT DISTINCT YEAR(COALESCE(date_versement_prevue, date_perception)) AS annee
    FROM versements
    WHERE ressource_id = ?
      AND (date_versement_prevue IS NOT NULL OR date_perception IS NOT NULL)
    ORDER BY annee DESC
");

$stmtAnnees->execute([$ressourceId]);

$anneesDisponibles = $stmtAnnees->fetchAll(PDO::FETCH_COLUMN);

/* =========================================================
   🔹 VERSEMENTS
========================================================= */

$sql = 'SELECT * FROM versements WHERE ressource_id = ?';
$params = [$ressourceId];

if (!empty($moisSelected) && !empty($anneeSelected)) {
    $debutMois = (new DateTime("$anneeSelected-$moisSelected-01"))->format('Y-m-d');

    $finMois = (new DateTime("$anneeSelected-$moisSelected-01"))
        ->modify('last day of this month')
        ->format('Y-m-d');

    $sql .= ' AND date_versement_prevue BETWEEN ? AND ?';

    $params[] = $debutMois;
    $params[] = $finMois;
}

$sql .= ' ORDER BY date_versement_prevue ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$versements = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   📊 TOTALS
========================================================= */

$totalPrevu = 0;
$totalPercu = 0;

foreach ($versements as $v) {
    $totalPrevu += $v['montant_prevu'];

    if ($v['statut'] === 'percu') {
        $totalPercu += $v['montant_reel'] ?? $v['montant_prevu'];
    }
}

$reste = $totalPrevu - $totalPercu;
$pourcentage = ($totalPrevu > 0) ? ($totalPercu / $totalPrevu) * 100 : 0;

?>

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

    <hr class="hr-glass">
<div class="row mb-4 justify-content-center">

    <div class="col-12 col-lg-9">

        <div class="glass-card-nav p-4">

            <!-- 🔎 FILTRE -->
            <form method="GET" action="<?= BASE_URL ?>router.php" class="row g-3 align-items-end justify-content-center">

                <input type="hidden" name="p" value="ressource_versements.php">
                <input type="hidden" name="id" value="<?= $ressourceId ?>">

    <!-- MOIS -->
     <div class="col-12 col-md-4">
     <label class="form-label text-white fw-bold">
                        Mois <span class="emoji fs-4">🗓️</span>
                    </label>
        <select name="mois" class="form-select">
            <option value="">Mois</option>

            <?php
            $moisNoms = [
                '01' => 'Janvier',
                '02' => 'Février',
                '03' => 'Mars',
                '04' => 'Avril',
                '05' => 'Mai',
                '06' => 'Juin',
                '07' => 'Juillet',
                '08' => 'Août',
                '09' => 'Septembre',
                '10' => 'Octobre',
                '11' => 'Novembre',
                '12' => 'Décembre'
            ];

            foreach ($moisNoms as $num => $nom):
                ?>
                <option value="<?= $num ?>" <?= ($num == $moisSelected) ? 'selected' : '' ?>>
                    <?= $nom ?>
                </option>
            <?php endforeach; ?>
        </select>
</div>

                <!-- ANNÉE -->
                <div class="col-12 col-md-4">

                    <label class="form-label text-white fw-bold">
                        Année <span class="emoji fs-4">🗓️</span>
                    </label>

                    <select name="annee" class="form-select">

                        <option value="">Toutes les années</option>

                        <?php foreach ($anneesDisponibles as $annee): ?>

                            <option value="<?= $annee ?>" <?= ($annee == $anneeSelected) ? 'selected' : '' ?>>
                                <?= $annee ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- BOUTONS -->
                <div class="col-12 col-md-4 d-flex justify-content-center gap-2">

                    <button class="btn-create-dash shadow-sm flex-fill justify-content-center">
                        <span class="emoji fs-4">🔎</span> Filtrer
                    </button>

                    <a href="<?= BASE_URL ?>router.php?p=ressource_versements.php&id=<?= $ressourceId ?>"
                       class="btn btn-secondary shadow-sm flex-fill d-flex align-items-center justify-content-center text-decoration-none">
                        <span class="emoji fs-4">🧹</span> Effacer
                    </a>

                </div>


            </form>

        </div>

    </div>

</div>

    <!-- 📊 BLOC -->
    <div class="row mb-4">

        <div class="col-md-4">
            <div class="card bg-glass p-3 text-center">
                <span class="text-white fw-bold">Prévu</span>
                <div class="fs-5 text-white"><?= number_format($totalPrevu, 2, ',', ' ') ?> €</div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-glass p-3 text-center">
                <span class="text-success fw-bold">Perçu</span>
                <div class="fs-5 text-white"><?= number_format($totalPercu, 2, ',', ' ') ?> €</div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-glass p-3 text-center">
                <span class="text-warning fw-bold">Reste</span>
                <div class="fs-5 text-white"><?= number_format($reste, 2, ',', ' ') ?> €</div>
            </div>
        </div>

    </div>

    <!-- PROGRESSION -->
    
<div class="card bg-glass p-3 mb-4 border-0" style="border-radius: 30px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="glass-header-small mb-0 fs-4 fs-md-5">
                <span class="emoji fs-4">🚀</span> Progression du paiement
            </h2>
            <div class="text-end">
                <span class="text-white uppercase-tracking me-2 d-none d-md-inline fs-4">Payé</span>
                <span
                    class="badge-neon badge-neon-blue badge-percent fw-bold text-accent-blue fs-5"><?= $pourcentage ?>%</span>
            </div>
        </div>

        <div class="progress progress-custom-dark" style="height: 12px;">
            <div class="progress-bar progress-bar-neon bg-neon-blue" role="progressbar"
                style="width: <?= round($pourcentage, 1) ?>%" aria-valuenow="<?= round($pourcentage, 1) ?>" aria-valuemin="0"
                aria-valuemax="100">
            </div>
        </div>
    </div>
    <!-- ========================================== -->
<!-- VERSION PC : Tableau classique (masqué sur mobile) -->
<!-- ========================================== -->
<div class="d-none d-md-block">
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 bg-glass">
            <thead>
                <tr>
                    <th>Date prévue</th>
                    <th>Montant prévu</th>
                    <th>Montant perçu</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($versements)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            Aucun versement.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($versements as $v): ?>
                        <?php
                        $dateObj = !empty($v['date_versement_prevue'])
                            ? new DateTime($v['date_versement_prevue'])
                            : null;

                        $dateAffichee = $dateObj ? $dateObj->format('d-m-Y') : '—';
                        $estPercu = ($v['statut'] === 'percu');

                        if ($estPercu) {
                            $bgClasse = 'border-solde';
                        } elseif ($dateObj && $dateObj < $aujourdhui) {
                            $bgClasse = 'border-en-retard';
                        } else {
                            $bgClasse = 'border-a-venir';
                        }
                        ?>
                                <tr class="<?= $bgClasse ?>">
                            <td><?= $dateAffichee ?></td>
                            <td><?= number_format($v['montant_prevu'], 2, ',', ' ') ?> €</td>
                            <td><?= $v['montant_reel'] ? number_format($v['montant_reel'], 2, ',', ' ') . ' €' : '-' ?></td>
                            <td><?= $estPercu ? '✅ Perçu' : '⏳ Attendu' ?></td>
                            <td class="d-flex gap-2">
                                <?php if (!$estPercu): ?>
                                    <form method="POST" action="<?= BASE_URL ?>router.php?p=ressources/payer_versement.php">
                                        <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                        <?php csrf_input() ?>
                                        <button class="btn btn-success btn-sm">✔️ Perçu</button>
                                    </form>
                                <?php endif; ?>
                                        <a href="<?= BASE_URL ?>router.php?p=ressources/modifier_versement.php&id=<?= (int) $v['id'] ?>"
                                    class="btn-modifier-neon">
                                    ✏️ Modifier
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================== -->
<!-- VERSION MOBILE : Cartes empilées (masqué sur PC) -->
<!-- ========================================== -->
<div class="d-block d-md-none">
    <div class="d-flex flex-column gap-3">
        <?php if (empty($versements)): ?>
            <div class="text-center py-4 text-muted bg-glass rounded-4 p-3 border border-secondary">
                Aucun versement.
            </div>
        <?php else: ?>
            <?php foreach ($versements as $v): ?>
                <?php
                $dateObj = !empty($v['date_versement_prevue'])
                    ? new DateTime($v['date_versement_prevue'])
                    : null;

                $dateAffichee = $dateObj ? $dateObj->format('d-m-Y') : '—';
                $estPercu = ($v['statut'] === 'percu');

                if ($estPercu) {
                    $cardClasse = 'border-solde';
                } elseif ($dateObj && $dateObj < $aujourdhui) {
                    $cardClasse = 'border-en-retard';
                } else {
                    $cardClasse = 'border-a-venir';
                }
                ?>
                <div class="p-3 rounded-4 shadow-sm border bg-glass <?= $cardClasse ?>">
                    <!-- Ligne supérieure : Date et Statut -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-white-50 small fw-bold">📅 <?= $dateAffichee ?></span>
                        <div>
                            <span><?= $estPercu ? '✅ Perçu' : '⏳ Attendu' ?></span>
                        </div>
                    </div>

                    <!-- Montants -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="small text-muted">Prévu : <span
                                    class="text-white fw-bold"><?= number_format($v['montant_prevu'], 2, ',', ' ') ?> €</span>
                            </div>
                        </div>
                        <div>
                            <div class="small text-muted">Perçu : <span
                                    class="text-success fw-bold"><?= $v['montant_reel'] ? number_format($v['montant_reel'], 2, ',', ' ') . ' €' : '-' ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions en bas de carte -->
                    <div class="d-flex justify-content-end align-items-center gap-2 pt-2 border-top border-secondary">
                        <?php if (!$estPercu): ?>
                            <form method="POST" action="<?= BASE_URL ?>router.php?p=ressources/payer_versement.php">
                                <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                <?php csrf_input() ?>
                                <button class="btn btn-success btn-sm">✔️ Perçu</button>
                            </form>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>router.php?p=ressources/modifier_versement.php&id=<?= (int) $v['id'] ?>"
                            class="btn-modifier-neon small">
                            ✏️ Modifier
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

    <!-- RETOUR -->
    <div class="mt-4">
        <a href="<?= BASE_URL ?>router.php?p=historique_ressources.php" class="btn btn-secondary">
            💰 Retour à l'historique
        </a>
    </div>

</div>
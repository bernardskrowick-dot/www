<?php
/* Version: v1.21.0 (Rev #13) - 2026-08-08 */

/**
 * echeancier.php - Version MBS_DARK
 * Correction des badges Néon et respect strict de la structure originale.
 */

// 🔒 SÉCURITÉ
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// 🔢 VALIDATION ID
$achatId = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int) $_GET['id'] : null;

if (!$achatId) {
    header('Location: router.php?p=historique.php');
    exit;
}

/* 1. RÉCUPÉRATION DES DONNÉES */
$stmt = $pdo->prepare('SELECT * FROM achats WHERE id = ? AND user_id = ?');
$stmt->execute([$achatId, $_SESSION['user_id']]);
$achat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$achat) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Achat introuvable ou accès refusé.</div></div>";
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM echeances WHERE achat_id = ? ORDER BY date_echeance ASC');
$stmt->execute([$achatId]);
$echeances = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* 2. CALCULS LOGIQUES */
$totalCommande = array_sum(array_column($echeances, 'montant'));
$totalPaye = array_sum(array_map(fn($e) => $e['statut'] == 'payee' ? $e['montant'] : 0, $echeances));
$resteAPayer = $totalCommande - $totalPaye;
$dateDepart = (new DateTime($achat['date_depart']))->format('d/m/Y');
$nombreEcheances = count($echeances);
$pourcentagePaye = $totalCommande > 0 ? round(($totalPaye / $totalCommande) * 100) : 0;
$nombreRestant = count(array_filter($echeances, fn($e) => $e['statut'] !== 'payee'));

// --- Correction de la classe du compteur ---
if ($nombreRestant === 0) {
    $neonClass = 'badge-solde';
} elseif ($nombreRestant <= 2) {
    $neonClass = 'badge-a-venir';
} else {
    $neonClass = 'badge-en-retard';  // Rouge Néon
}
?>

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
    <hr class="hr-glass">

    <div class="row g-2 g-md-3 mb-3">
        <div class="col-md-4">
            <div class="card bg-glass p-2 p-md-3 d-flex align-items-center justify-content-center border-0 h-100">
                <h4 class="m-0 text-white fs-5 fs-md-4 text-center">
                    <span class="text-accent-blue">🏪 Marchand :</span>
                    <?= htmlspecialchars($achat['nom_marchand']) ?>
                </h4>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-glass p-2 p-md-3 d-flex align-items-center justify-content-center border-0 h-100">
                <h4 class="m-0 text-white fs-5 fs-md-4 text-center">
                    <span style="color: #ffca28;">🏷️ Achat :</span>
                    <?= htmlspecialchars($achat['titre']) ?>
                </h4>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-glass p-2 p-md-3 d-flex align-items-center justify-content-center border-0 h-100">
                <h4 class="m-0 text-white fs-5 fs-md-4 text-center">
                    <span class="text-accent-purple">📅 Date :</span>
                    <?= $dateDepart ?>
                </h4>
            </div>
        </div>
    </div>

    <div class="row g-2 g-md-3 mb-0">
        <div class="col-6 col-md">
            <div class="card bg-glass border-accent-blue text-center p-2 p-md-3 h-100">
                <span class="uppercase-tracking text-accent-blue fw-bold d-block mb-1 fs-5">Montant Total</span>
                <div class="fs-5 fs-md-4 fw-bold text-white"><?= number_format($totalCommande, 2, ',', ' ') ?> €</div>
            </div>
        </div>

        <div class="col-6 col-md">
            <div class="card bg-glass border-accent-purple text-center p-2 p-md-3 h-100">
                <span class="uppercase-tracking text-accent-purple fw-bold d-block mb-1 fs-5">Déjà réglé</span>
                <div class="fs-5 fs-md-4 fw-bold text-white"><?= number_format($totalPaye, 2, ',', ' ') ?> €</div>
            </div>
        </div>

        <div class="col-6 col-md">
            <div class="card bg-glass border-danger text-center p-2 p-md-3 h-100">
                <span class="uppercase-tracking text-danger fw-bold d-block mb-1 fs-5">Reste à
                    payer</span>
                <div class="fs-5 fs-md-4 fw-bold text-white text-danger-glow">
                    <?= number_format($resteAPayer, 2, ',', ' ') ?> €
                </div>
            </div>
        </div>

        <div class="col-6 col-md">
            <div class="card bg-glass border-accent-blue text-center p-2 p-md-3 h-100">
                <span class="uppercase-tracking text-accent-blue fw-bold d-block mb-1 fs-5">Échéances</span>
                <div class="fs-5 fs-md-4 fw-bold text-white"><?= $nombreEcheances ?></div>
            </div>
        </div>

        <div class="col-12 col-md">
            <div class="card bg-glass border-accent-blue text-center p-2 p-md-3 h-100">
                <span class="uppercase-tracking text-accent-blue fw-bold d-block mb-1 fs-5">Restantes</span>
                <div class="d-flex justify-content-center align-items-center">
                    <span class="compteur-kpi <?= $neonClass ?> " id="compteur-restant">
                        0
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="py-3 py-md-4"></div>

    <div class="card bg-glass p-3 mb-4 border-0" style="border-radius: 30px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="glass-header-small mb-0 fs-6 fs-md-5">
                <span class="emoji me-2">🚀</span> Progression du paiement
            </h2>
            <div class="text-end">
                <span class="text-white-50 small uppercase-tracking me-2 d-none d-md-inline">Réglé</span>
                <span
                    class="badge-neon badge-neon-blue badge-percent fw-bold text-accent-blue fs-5"><?= $pourcentagePaye ?>%</span>
            </div>
        </div>

        <div class="progress progress-custom-dark" style="height: 12px;">
            <div class="progress-bar progress-bar-neon bg-neon-blue" role="progressbar"
                style="width: <?= $pourcentagePaye ?>%" aria-valuenow="<?= $pourcentagePaye ?>" aria-valuemin="0"
                aria-valuemax="100">
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-custom-dark align-middle">
            <thead>
                <tr>
                    <th class="th-title text-center" style="min-width: 100px !important; width: 5%;">
                        <span>ORIGINE</span>
                    </th>
                    <th class="th-date" style="width: 20%;"><span>DATE PRÉVUE</span></th>
                    <th class="th-amount" style="width: 25%;"><span>MONTANT</span></th>
                    <th class="th-state" style="width: 25%;"><span>STATUT</span></th>
                    <th class="th-action" style="width: 25%;"><span>ACTIONS</span></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rowspan = count($echeances);
                foreach ($echeances as $index => $e):
                    $estEnRetard = ($e['statut'] == 'en_attente' && $e['date_echeance'] < date('Y-m-d'));
                    $classeBordure = $e['statut'] == 'payee' ? 'border-solde' : ($estEnRetard ? 'border-en-retard' : 'border-a-venir');

                    // Initialisation propre de la variable pour éviter le warning PHP
                    $classeRetard = $estEnRetard ? 'tr-en-retard' : '';

                    $libelleAjuste = '';
                    if ($e['modifie_montant'] && $e['modifie_date'])
                        $libelleAjuste = '✏️ Ajustée (M+D)';
                    elseif ($e['modifie_montant'])
                        $libelleAjuste = '✏️ Ajustée (M)';
                    elseif ($e['modifie_date'])
                        $libelleAjuste = '✏️ Ajustée (D)';
                    ?>
                    <tr class="hover-glow <?= $classeRetard ?>">
                        <?php if ($index === 0): ?>
                            <td rowspan="<?= $rowspan ?>" class="text-center bg-glass-light border-end align-middle td-origine"
                                data-label="Origine">
                                <div class="fw-bold text-white"><?= $dateDepart ?></div>
                                <small class="text-muted">Date de départ</small>
                            </td>
                        <?php endif; ?>

                        <td class="text-center bg-glass <?= $classeBordure ?>" data-label="Date prévue">
                            <span
                                class="fw-bold text-white"><?= (new DateTime($e['date_echeance']))->format('d/m/Y') ?></span>
                        </td>

                        <td class="text-end fw-bold text-white bg-glass <?= $classeBordure ?>" data-label="Montant">
                            <?= number_format($e['montant'], 2, ',', ' ') ?> €
                        </td>

                        <td class="text-center bg-glass <?= $classeBordure ?>" data-label="Statut">
                            <?php if ($e['statut'] == 'payee'): ?>
                                <span class="badge-neon badge-solde">✅ PAYÉE</span>
                            <?php else: ?>
                                <span
                                    class="badge-neon <?= $estEnRetard ? 'badge-en-retard badge-warning-pulse' : 'badge-a-venir' ?>">
                                    <?= $estEnRetard ? '⚠️ EN RETARD' : '⏳ EN ATTENTE' ?>
                                </span>
                                <?php if ($libelleAjuste): ?>
                                    <div style="font-size: 0.65rem;" class="text-accent-purple mt-1 fw-bold"><?= $libelleAjuste ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <td class="text-end bg-glass <?= $classeBordure ?> td-actions" style="white-space: nowrap;"
                            data-label="Actions">
                            <div class="d-flex justify-content-end gap-2">
                                <?php if ($e['statut'] == 'en_attente'): ?>
                                    <form method="POST" action="<?= BASE_URL ?>router.php?p=achats/payer_echeance.php"
                                        class="d-inline m-0">
                                        <?php csrf_input(); ?>
                                        <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                                        <input type="hidden" name="achat_id" value="<?= $achatId ?>">
                                        <button type="submit" class="btn-payer-neon">Payer</button>
                                    </form>
                                <?php endif; ?>
                                <a href="<?= BASE_URL ?>router.php?p=achats/modifier_echeance.php&id=<?= (int) $e['id'] ?>"
                                    class="btn-modifier-neon">
                                    <span>✏️</span> Modifier
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        <a href="<?= BASE_URL ?>router.php?p=historique.php" class="btn btn-secondary shadow-sm">
            <span class="emoji">🛒</span> Retour à l'historique
        </a>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const compteur = document.getElementById('compteur-restant');
        const valeurFinale = <?= (int) $nombreRestant ?>;
        let current = 0;

        if (valeurFinale > 0) {
            const interval = setInterval(() => {
                if (current < valeurFinale) {
                    current++;
                    compteur.textContent = current;
                } else {
                    clearInterval(interval);
                }
            }, 100);
        } else {
            compteur.textContent = "0";
        }
    });
</script>
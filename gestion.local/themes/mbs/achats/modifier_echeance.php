<?php
/* Version: v1.21.0 (Rev #7) - 2026-08-08 */

/**
 * modifier_echeance.php - Version MBS_DARK (2026)
 * Application des règles : Sécurité CSRF, Constantes, et Multi-thème.
 */

/* ----------------------------- 1. SÉCURITÉ & ACCÈS ----------------------------- */
// Vérification de la session utilisateur
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Validation de l'ID passé en paramètre GET
$idEcheance = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int) $_GET['id'] : null;
if (!$idEcheance) {
    header('Location: router.php?p=index.php');
    exit;
}

/* ----------------------------- 2. RÉCUPÉRATION DATA ---------------------------- */
// Récupération de l'échéance et des infos de l'achat lié
$stmt = $pdo->prepare('
    SELECT e.*, a.titre, a.montant_total 
    FROM echeances e 
    JOIN achats a ON e.achat_id = a.id 
    WHERE e.id = ? AND a.user_id = ?
');
$stmt->execute([$idEcheance, $_SESSION['user_id']]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    echo "<div class='alert alert-danger'>Échéance introuvable.</div>";
    exit;
}

$achatId = $data['achat_id'];
$montantTotalAchat = $data['montant_total'];

/* ----------------------------- 3. TRAITEMENT POST ------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du jeton CSRF (Règle de sécurité 2026)
    verify_csrf_token();

    $nouveauMontant = filter_var($_POST['montant'] ?? '', FILTER_VALIDATE_FLOAT);
    $nouvelleDate = $_POST['date_echeance'] ?? '';

    if ($nouveauMontant !== false && $nouveauMontant >= 0 && !empty($nouvelleDate)) {
        // Détection des changements pour les flags
        $modifMontant = ($nouveauMontant != $data['montant']) ? 1 : 0;
        $modifDate = ($nouvelleDate != $data['date_echeance']) ? 1 : 0;

        // Mise à jour de l'échéance cible
        $stmt = $pdo->prepare('
            UPDATE echeances 
            SET montant = :montant, 
                date_echeance = :date_echeance, 
                modifie_manuellement = 1,
                modifie_montant = :m_m,
                modifie_date = :m_d
            WHERE id = :id
        ');
        $stmt->execute([
            'montant' => $nouveauMontant,
            'date_echeance' => $nouvelleDate,
            'm_m' => $modifMontant,
            'm_d' => $modifDate,
            'id' => $idEcheance
        ]);

        /* --- LOGIQUE DE RÉÉQUILIBRAGE --- */
        // On récupère toutes les échéances pour ajuster la dernière si nécessaire
        $stmtAll = $pdo->prepare('SELECT id, montant FROM echeances WHERE achat_id = ? ORDER BY date_echeance ASC');
        $stmtAll->execute([$achatId]);
        $all = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        if (count($all) > 1) {
            $sommeSaufDerniere = 0;
            for ($i = 0; $i < count($all) - 1; $i++) {
                $sommeSaufDerniere += $all[$i]['montant'];
            }
            $idDerniere = $all[count($all) - 1]['id'];
            $nouveauReste = round($montantTotalAchat - $sommeSaufDerniere, 2);

            $upd = $pdo->prepare('UPDATE echeances SET montant = ? WHERE id = ?');
            $upd->execute([$nouveauReste, $idDerniere]);
        }

        $_SESSION['flash_success'] = '✅ Échéance mise à jour avec succès.';
        header('Location: ' . BASE_URL . 'router.php?p=achats/echeancier.php&id=' . $achatId);
        exit;
    }
}
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

    <hr class="hr-glass">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">

            <div class="card bg-glass p-3 mb-4 border-0">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center px-3">
                        <div class="icon-box-neon me-3 d-flex align-items-center justify-content-center"
                            style="width: 50px; height: 50px; background: rgba(0, 242, 254, 0.1); border: 1px solid var(--accent-blue); border-radius: 12px;">
                            <span class="fs-4">🛒</span>
                        </div>

                        <div class="border-start border-glass-light ps-3">
                            <small class="uppercase-tracking text-accent-blue fw-bold label-style"
                                style="letter-spacing: 1px;">
                                ACHAT
                            </small>
                            <div class="fw-bold text-white text-uppercase title-style"
                                style="text-shadow: 0 0 10px rgba(255,255,255,0.2);">
                                <?= htmlspecialchars($data['titre']) ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-end px-3 border-start border-glass-light">
                        <small class="text-accent-purple fw-bold">TOTAL À RESPECTER</small>
                        <div class="fs-4 text-white fw-bold"><?= number_format($montantTotalAchat, 2, ',', ' ') ?> €
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-glass p-4 border-0 shadow-lg">
                <form method="POST">
                    <?php csrf_input(); ?>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label text-white fw-bold">Nouveau Montant (€)</label>
                            <input type="number" step="0.01" min="0" name="montant"
                                class="form-control bg-glass-light text-white border-0 py-3"
                                value="<?= (float) $data['montant'] ?>" required>
                            <div class="form-text text-muted">Valeur actuelle :
                                <?= number_format($data['montant'], 2, ',', ' ') ?> €</div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <label class="form-label text-white fw-bold">Date de l'échéance</label>
                            <input type="date" name="date_echeance"
                                class="form-control bg-glass-light text-white border-0 py-3"
                                value="<?= htmlspecialchars($data['date_echeance']) ?>" required>
                        </div>
                    </div>

                    <div class="p-3 bg-glass-light border-start border-accent-purple border-4 rounded mb-4">
                        <div class="d-flex align-items-center">
                            <span class="fs-3 me-3">🔄</span>
                            <div class="text-white">
                                <strong>Rééquilibrage automatique :</strong> En modifiant cette échéance, la dernière du
                                tableau sera ajustée pour garantir un total strict de
                                <strong><?= number_format($montantTotalAchat, 2, ',', ' ') ?> €</strong>.
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mt-2">
                        <div class="col-md-6">
                            <button type="submit" class="btn-payer-neon w-100 py-3 fw-bold uppercase-tracking">
                                💾 ENREGISTRER LES MODIFICATIONS
                            </button>
                        </div>
                        <div class="col-md-6">
                            <a href="<?= BASE_URL ?>router.php?p=achats/echeancier.php&id=<?= $achatId ?>"
                                class="btn-modifier-neon w-100 py-3 text-center d-block text-decoration-none fw-bold uppercase-tracking">
                                ❌ ANNULER ET RETOURNER
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
/* Version: v1.21.0 (Rev #10) - 2026-08-16 */

/**
 * Fichier : supprimer_ressource.php
 * ACTION : Double vérification visuelle + Suppression atomique
 * RÈGLES : CSRF, Transactions, Zéro Style Inline, Constantes, Commentaires
 */

// 🔒 1. Vérification de session via la fonction globale
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

/**
 * 🔢 2. Récupération de l'ID
 */
$ressourceId = 0;
if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $ressourceId = (int) $_GET['id'];
} elseif (isset($_POST['id']) && ctype_digit($_POST['id'])) {
    $ressourceId = (int) $_POST['id'];
}

if ($ressourceId <= 0) {
    header('Location: ' . BASE_URL . 'router.php?p=dashboard_revenus.php');
    exit;
}

// Initialisation des états pour l'affichage
$show_confirmation = true;
$success = false;
$error_msg = '';

/**
 * 🔍 RÉCUPÉRATION DES INFORMATIONS AVANT SUPPRESSION
 */
$stmtInfo = $pdo->prepare('SELECT id, titre, organisme, montant_prevu FROM ressources WHERE id = ? AND user_id = ?');
$stmtInfo->execute([$ressourceId, $_SESSION['user_id']]);
$detailsRessource = $stmtInfo->fetch();

if (!$detailsRessource) {
    $error_msg = 'Accès refusé ou ressource introuvable.';
    $show_confirmation = false;
}

// 🛠️ 3. TRAITEMENT DE LA SUPPRESSION (Seulement si le bouton "Confirmer" est pressé)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_action'])) {
    // 🛡️ Vérification obligatoire du jeton CSRF
    verify_csrf_token();

    try {
        $stmtCheck = $pdo->prepare('SELECT id, titre, organisme, montant_prevu FROM ressources WHERE id = ? AND user_id = ?');
        $stmtCheck->execute([$ressourceId, $_SESSION['user_id']]);
        $data = $stmtCheck->fetch();

        if ($data) {
            $pdo->beginTransaction();

            // 1️⃣ Récupérer les IDs des versements liés avant suppression
            $stmtVerifIds = $pdo->prepare('SELECT id FROM versements WHERE ressource_id = ?');
            $stmtVerifIds->execute([$ressourceId]);
            $versementsIds = $stmtVerifIds->fetchAll(PDO::FETCH_COLUMN);

            // 2️⃣ Suppression des versements (Enfants)
            $stmtDelVer = $pdo->prepare('DELETE FROM versements WHERE ressource_id = ?');
            $stmtDelVer->execute([$ressourceId]);

            // 3️⃣ Logguer la suppression des versements pour la synchronisation
            $stmtLogVer = $pdo->prepare('INSERT INTO sync_deletions (table_name, record_id, user_id, nom) VALUES (?, ?, ?, ?)');
            foreach ($versementsIds as $vId) {
                $stmtLogVer->execute(['versements', $vId, $_SESSION['user_id'], 'Versement #' . $vId]);
            }

            // 4️⃣ Suppression de la ressource (Parent)
            $stmtDelRes = $pdo->prepare('DELETE FROM ressources WHERE id = ? AND user_id = ?');
            $stmtDelRes->execute([$ressourceId, $_SESSION['user_id']]);

            // 5️⃣ Logguer la suppression de la ressource pour la synchronisation
            $stmtLogRes = $pdo->prepare('INSERT INTO sync_deletions (table_name, record_id, user_id, nom) VALUES (?, ?, ?, ?)');
            $stmtLogRes->execute(['ressources', $ressourceId, $_SESSION['user_id'], $data['titre']]);

            // ✅ Validation de la transaction
            $pdo->commit();

            $_SESSION['flash_success'] = "La ressource « <strong>" . htmlspecialchars($data['titre']) . '</strong> » (' . htmlspecialchars($data['organisme']) . ") d'un montant de " . number_format($data['montant_prevu'], 2) . ' € a été supprimée avec succès.';

            $success = true;
            $show_confirmation = false;

            header('Location: ' . BASE_URL . 'router.php?p=dashboard_revenus.php');
            exit;
        } else {
            $error_msg = "Accès refusé : vous n'êtes pas le propriétaire de cette ressource.";
            $show_confirmation = false;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_msg = 'Erreur technique : la suppression a échoué.';
        $show_confirmation = false;
    }
}

/** 🎨 4. INTERFACE VISUELLE (Thème MBS_DARK) */
?>

<div class="container py-5 text-center">
    <div class="card bg-glass border-accent-blue p-5 shadow-lg card-confirm-delete mx-auto">

        <?php if ($show_confirmation): ?>
            <div class="display-1 mb-4">❓</div>
            <h2 class="text-white mb-3">Confirmer la suppression</h2>

            <div class="text-start mb-4 p-3 bg-dark border border-secondary">
                <p class="text-info mb-1"><strong>Ressource :</strong> <?= htmlspecialchars($detailsRessource['titre'] ?? '') ?></p>
                <p class="text-white mb-1"><strong>Organisme :</strong> <?= htmlspecialchars($detailsRessource['organisme'] ?? '') ?></p>
                <p class="text-white mb-0"><strong>Montant :</strong> <?= number_format($detailsRessource['montant_prevu'] ?? 0, 2) ?> €</p>
            </div>

            <p class="text-muted mb-4">
                Êtes-vous sûr de vouloir supprimer cette ressource ?<br>
                <span class="text-danger">⚠️ Cette action supprimera également tous les versements liés.</span>
            </p>

            <form method="POST" action="<?= BASE_URL ?>router.php?p=supprimer_ressource.php&id=<?= $ressourceId ?>">
                <?php csrf_input(); ?>
                <input type="hidden" name="id" value="<?= $ressourceId ?>">

                <div class="d-flex justify-content-center gap-3">
                    <a href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php" class="btn btn-outline-light px-4">Annuler</a>
                    <button type="submit" name="confirm_action" class="btn btn-danger px-4">Confirmer la suppression</button>
                </div>
            </form>

        <?php elseif ($success): ?>
            <div class="display-1 mb-4">🗑️</div>
            <h2 class="text-white mb-3">Suppression réussie</h2>
            <p class="text-muted mb-4">L'enregistrement a été définitivement effacé.</p>
            <a href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php" class="btn btn-payer-neon px-4">🏠 Retour au Dashboard</a>

        <?php else: ?>
            <div class="display-1 mb-4">⚠️</div>
            <h2 class="text-danger mb-3">Erreur</h2>
            <p class="text-muted mb-4"><?= htmlspecialchars($error_msg) ?></p>
            <a href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php" class="btn btn-secondary shadow-sm flex-fill d-flex align-items-center justify-content-center">
                <span class="emoji fs-4 me-2">💰</span>
                Retour au Tableau de bord Revenus
            </a>
        <?php endif; ?>

    </div>
</div>
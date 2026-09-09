<?php
/* Version: v1.21.0 (Rev #10) - 2026-08-08 */

/**
 * Fichier : supprimer_achat.php
 * ACTION : Double vérification visuelle + Suppression atomique
 * RÈGLES : CSRF, Transactions, Zéro Style Inline, Constantes, Commentaires
 */

// 🔒 1. Vérification de session via ta fonction globale
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

/**
 * 🔢 2. Récupération de l'ID
 * Correction : On vérifie $_GET et $_POST séparément pour forcer la lecture
 */
$idAchat = 0;
if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $idAchat = (int) $_GET['id'];
} elseif (isset($_POST['id']) && ctype_digit($_POST['id'])) {
    $idAchat = (int) $_POST['id'];
}

// Si pas d'ID valide, on dégage vers le dashboard
if ($idAchat <= 0) {
    // Si tu es redirigé ici, c'est que le bouton du dashboard n'envoie pas l'ID correctement
    header('Location: ' . BASE_URL . 'router.php?p=dashboard.php');
    exit;
}

// Initialisation des états pour l'affichage
$show_confirmation = true;
$success = false;
$error_msg = '';

/**
 * 🔍 RÉCUPÉRATION DES INFORMATIONS AVANT SUPPRESSION
 * On récupère les détails pour l'affichage visuel
 */
$stmtInfo = $pdo->prepare('SELECT titre, nom_marchand, montant_total FROM achats WHERE id = ? AND user_id = ?');
$stmtInfo->execute([$idAchat, $_SESSION['user_id']]);
$detailsAchat = $stmtInfo->fetch();

if (!$detailsAchat) {
    $error_msg = 'Accès refusé ou achat introuvable.';
    $show_confirmation = false;
}

// 🛠️ 3. TRAITEMENT DE LA SUPPRESSION (Seulement si le bouton "Confirmer" est pressé)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_action'])) {
    // 🛡️ Vérification obligatoire du jeton CSRF (Action destructrice)
    verify_csrf_token();

    try {
        // A. Vérification de la propriété (Double check serveur)
        $stmtCheck = $pdo->prepare('SELECT titre, nom_marchand, montant_total FROM achats WHERE id = ? AND user_id = ?');
        $stmtCheck->execute([$idAchat, $_SESSION['user_id']]);
        $data = $stmtCheck->fetch();

        if ($data) {
            // B. Début de la transaction
            $pdo->beginTransaction();

            // 1️⃣ Récupérer les IDs des échéances liées avant leur suppression pour les logger
            $stmtEchIds = $pdo->prepare('SELECT id FROM echeances WHERE achat_id = ?');
            $stmtEchIds->execute([$idAchat]);
            $echeancesIds = $stmtEchIds->fetchAll(PDO::FETCH_COLUMN);

            // 2️⃣ Suppression des échéances (Enfants)
            $stmtDelEch = $pdo->prepare('DELETE FROM echeances WHERE achat_id = ?');
            $stmtDelEch->execute([$idAchat]);

            // 3️⃣ Logguer la suppression de chaque échéance pour la synchronisation
            $stmtLogEch = $pdo->prepare('INSERT INTO sync_deletions (table_name, record_id, user_id, nom) VALUES (?, ?, ?, ?)');
            foreach ($echeancesIds as $echId) {
                // Pour une échéance, on peut stocker un libellé descriptif ou "Échéance #ID" dans la colonne nom
                $stmtLogEch->execute(['echeances', $echId, $_SESSION['user_id'], 'Échéance #' . $echId]);
            }

            // 4️⃣ Suppression de l'achat (Parent)
            $stmtDelAch = $pdo->prepare('DELETE FROM achats WHERE id = ? AND user_id = ?');
            $stmtDelAch->execute([$idAchat, $_SESSION['user_id']]);

            // 5️⃣ Logguer la suppression de l'achat pour la synchronisation
            $stmtLogAch = $pdo->prepare('INSERT INTO sync_deletions (table_name, record_id, user_id, nom) VALUES (?, ?, ?, ?)');
            $stmtLogAch->execute(['achats', $idAchat, $_SESSION['user_id'], $data['titre']]);

            // ✅ Validation
            $pdo->commit();

            // 📢 Stockage des informations pour le Dashboard avant redirection
            $_SESSION['flash_success'] = "L'achat « <strong>" . htmlspecialchars($data['titre']) . '</strong> » (' . htmlspecialchars($data['nom_marchand']) . ") d'un montant de " . number_format($data['montant_total'], 2) . ' € a été supprimé avec succès.';

            $success = true;
            $show_confirmation = false;

            // Redirection immédiate pour afficher le message sur le dashboard
            header('Location: ' . BASE_URL . 'router.php?p=dashboard_revenus.php');
            exit;
        
        } else {
            $error_msg = "Accès refusé : vous n'êtes pas le propriétaire de cet achat.";
            $show_confirmation = false;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
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
                <p class="text-info mb-1"><strong>Achat :</strong> <?= htmlspecialchars($detailsAchat['titre'] ?? '') ?></p>
                <p class="text-white mb-1"><strong>Marchand :</strong>
                    <?= htmlspecialchars($detailsAchat['nom_marchand'] ?? '') ?>
                </p>
                <p class="text-white mb-0"><strong>Montant :</strong>
                    <?= number_format($detailsAchat['montant_total'] ?? 0, 2) ?> €
                </p>
            </div>

            <p class="text-muted mb-4">
                Êtes-vous sûr de vouloir supprimer cet achat ?<br>
                <span class="text-danger">⚠️ Cette action supprimera également toutes les échéances liées.</span>
            </p>

            <form method="POST" action="<?= BASE_URL ?>router.php?p=supprimer_achat.php&id=<?= $idAchat ?>">
                <?php csrf_input(); ?>
                <input type="hidden" name="id" value="<?= $idAchat ?>">

                <div class="d-flex justify-content-center gap-3">
                    <a href="<?= BASE_URL ?>router.php?p=dashboard_achats.php"
                        class="btn btn-outline-light px-4">Annuler</a>
                    <button type="submit" name="confirm_action" class="btn btn-danger px-4">Confirmer la
                        suppression</button>
                </div>
            </form>

        <?php elseif ($success): ?>
            <div class="display-1 mb-4">🗑️</div>
            <h2 class="text-white mb-3">Suppression réussie</h2>
            <p class="text-muted mb-4">L'enregistrement a été définitivement effacé.</p>
            <a href="<?= BASE_URL ?>router.php?p=dashboard_achats.php" class="btn btn-payer-neon px-4">🏠 Retour au
                Dashboard</a>

        <?php else: ?>
            <div class="display-1 mb-4">⚠️</div>
            <h2 class="text-danger mb-3">Erreur</h2>
            <p class="text-muted mb-4"><?= htmlspecialchars($error_msg) ?></p>
            <a href="<?= BASE_URL ?>router.php?p=dashboard_achats.php"
                class="btn btn-secondary shadow-sm flex-fill d-flex align-items-center justify-content-center">
            
                <span class="emoji fs-4 me-2">🛒</span>
                Retour au Tableau de bord Achats
            
            </a>
        <?php endif; ?>

    </div>
</div>
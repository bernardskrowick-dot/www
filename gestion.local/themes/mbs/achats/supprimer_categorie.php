<?php
/* Version: v1.19.1 (Rev #25) - 2026-08-06 */

/**
 * supprimer_categorie.php - Suppression sécurisée avec page de sortie
 * RÈGLES : PDO Transactions, Sécurité, Page de confirmation, Constantes
 */

// 🔒 1. Sécurité : Vérification de la session
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// 🔢 2. Récupération et validation de l'ID
$idCat = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$success = false;
$error_msg = '';

// On ne permet pas de supprimer la catégorie par défaut (ID 1)
if ($idCat && $idCat > 1) {
    try {
        $pdo->beginTransaction();

        // 1️⃣ On bascule tous les achats de cette catégorie vers "Non classé" (ID 1)
        $stmtUpdate = $pdo->prepare('UPDATE achats SET categorie_id = 1 WHERE categorie_id = ? AND user_id = ?');
        $stmtUpdate->execute([$idCat, $_SESSION['user_id']]);

        // 2️⃣ Les sous-catégories deviennent des catégories principales indépendantes
        $stmtSousCat = $pdo->prepare('UPDATE categories SET parent_id = NULL WHERE parent_id = ? AND user_id = ?');
        $stmtSousCat->execute([$idCat, $_SESSION['user_id']]);

        // 3️⃣ Suppression de la catégorie
        $stmtDel = $pdo->prepare('DELETE FROM categories WHERE id = ? AND user_id = ?');
        $stmtDel->execute([$idCat, $_SESSION['user_id']]);

        if ($stmtDel->rowCount() > 0) {
            // 4️⃣ Ajout dans la table de log pour la synchronisation
            $stmtLog = $pdo->prepare('INSERT INTO sync_deletions (table_name, record_id) VALUES (?, ?)');
            $stmtLog->execute(['categories', $idCat]);

            $pdo->commit();
            $success = true;
        } else {
            $pdo->rollBack();
            $error_msg = "Vous n'avez pas l'autorisation de supprimer cette catégorie.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error_msg = 'Erreur technique : ' . $e->getMessage();
    }
} else {
    $error_msg = ($idCat === 1)
        ? 'La catégorie par défaut ne peut pas être supprimée.'
        : 'ID de catégorie invalide.';
}

?>

<div class="container py-5 text-center">
    <div class="card bg-glass border-accent-blue p-5 shadow-lg mx-auto" style="max-width: 600px; border-radius: 20px;">
        
        <?php if ($success): ?>

            <div class="display-1 mb-4">📂✅</div>

            <h2 class="text-white mb-3">Catégorie supprimée</h2>

            <p class="text-white-50 mb-4">
                La catégorie a été retirée avec succès.<br>
                <small class="text-info">
                    Les achats associés ont été déplacés vers <strong>'Non classé'</strong>.<br>
                    Les sous-catégories sont devenues des catégories principales.
                </small>
            </p>

            <div class="d-grid">
                <a href="<?= BASE_URL ?>router.php?p=gestion_categories.php" class="btn btn-neon-cyan px-4">
                   <span class="emoji fs-4">🛒</span> Retour à la gestion des catégories Achats
                </a>
            </div>

        <?php else: ?>

            <div class="display-1 mb-4">⚠️</div>

            <h2 class="text-danger mb-3">Action impossible</h2>

            <p class="text-white-50 mb-4">
                <?= htmlspecialchars($error_msg) ?>
            </p>

            <div class="d-grid">
                <a href="<?= BASE_URL ?>router.php?p=gestion_categories.php" class="btn btn-outline-light px-4">
                    <span class="emoji fs-4">🏷️</span>  Retourner à la liste
                </a>
            </div>

        <?php endif; ?>

    </div>
</div>
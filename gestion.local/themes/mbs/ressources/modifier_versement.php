<?php
/* Version: v1.21.0 (Rev #3) - 2026-08-08 */

/**
 * modifier_versement.php
 * Modification d'un versement individuel
 * Version MBS_DARK V2
 */

// 🔒 Sécurité : Vérification de l'authentification de l'utilisateur
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// On définit explicitement $user_id pour le reste du script et pour Intelephense
$user_id = $_SESSION['user_id'];

// -----------------------------
// Validation et récupération de l'ID du versement
// -----------------------------
$idVersement = isset($_GET['id']) && ctype_digit($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if (!$idVersement) {
    header('Location: ' . BASE_URL . 'router.php?p=historique_ressources.php');
    exit;
}

// -----------------------------
// Récupération du versement et vérification de la propriété via la ressource
// -----------------------------
$stmt = $pdo->prepare('
    SELECT v.*, r.titre
    FROM versements v
    JOIN ressources r ON r.id = v.ressource_id
    WHERE v.id = :id
      AND r.user_id = :user_id
    LIMIT 1
');

$stmt->execute([
    'id' => $idVersement,
    'user_id' => $_SESSION['user_id']
]);

$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    echo "<div class='alert alert-danger'>Versement introuvable.</div>";
    exit;
}

// -----------------------------
// Traitement de la soumission du formulaire (POST)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du jeton CSRF si la fonction existe
    if (function_exists('verify_csrf_token')) {
        verify_csrf_token();
    }

    $montant = filter_var($_POST['montant_prevu'] ?? 0, FILTER_VALIDATE_FLOAT);
    $date = $_POST['date_versement_prevue'] ?? '';

    if ($montant !== false && !empty($date)) {
        // Mise à jour du versement avec marquage des modifications manuelles
        $stmt = $pdo->prepare('
            UPDATE versements
            SET montant_prevu = :montant,
                date_versement_prevue = :datev,
                modifie_manuellement = 1,
                modifie_montant = 1,
                modifie_date = 1
            WHERE id = :id
        ');

        $stmt->execute([
            'montant' => $montant,
            'datev' => $date,
            'id' => $idVersement
        ]);

        // Message flash de succès
        $_SESSION['flash_success'] = '✅ Versement modifié avec succès.';

        header('Location: ' . BASE_URL . 'router.php?p=ressource_versements.php&id=' . $data['ressource_id']);
        exit;
    }
}
?>

<div class="container py-4">

    <div class="card bg-glass p-4">

        <h3 class="text-white mb-4">
            ✏️ Modifier un versement
        </h3>

        <p class="text-muted">
            <?= htmlspecialchars($data['titre']) ?>
        </p>

        <form method="POST">

            <?php csrf_input() ?>

            <div class="mb-3">
                <label class="form-label text-white">
                    Montant prévu (€)
                </label>

                <input type="number" step="0.01" name="montant_prevu" class="form-control bg-dark text-white border-secondary"
                    value="<?= $data['montant_prevu'] ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label text-white">
                    Date de versement
                </label>

                <input type="date" name="date_versement_prevue" class="form-control bg-dark text-white border-secondary"
                    value="<?= $data['date_versement_prevue'] ?>" required>
            </div>

            <div class="row g-3 mt-4">

                <div class="col-md-6">
                    <button type="submit" class="btn-payer-neon w-100">
                        💾 Enregistrer
                    </button>
                </div>

                <div class="col-md-6">
                    <a href="<?= BASE_URL ?>router.php?p=ressource_versements.php&id=<?= $data['ressource_id'] ?>"
                        class="btn btn-secondary shadow-sm flex-fill d-flex align-items-center justify-content-center w-100">
                        ❌ Annuler
                    </a>
                </div>

            </div>

        </form>

    </div>

</div>
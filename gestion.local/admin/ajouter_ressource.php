<?php
/* Version: v1.3.0 (Rev #13) - 2026-07-30 */
// 🔒 Sécurité : Vérification de la session
if (!isset($user_id)) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}
?>

<div class="container py-4">

    <!-- HEADER -->
    <div class="glass-card-nav p-4 mb-4">
        <h2 class="glass-header-small mb-1">
            💰 Ajouter une ressource
        </h2>
        <small class="text-muted">
            Salaire, CAF, France Travail, autres revenus
        </small>
    </div>

    <!-- FORM -->
    <div class="card bg-glass p-4">

        <form method="POST" action="<?= BASE_URL ?>router.php?p=traiter_ressource.php">

            <!-- TITRE -->
            <div class="mb-3">
                <label class="form-label text-white">Titre</label>
                <input type="text" name="titre" class="form-control bg-dark text-white border-secondary"
                    placeholder="Ex : Salaire, CAF, Aide logement..." required>
            </div>

            <!-- ORGANISME -->
            <div class="mb-3">
                <label class="form-label text-white">Organisme</label>
                <input type="text" name="organisme" class="form-control bg-dark text-white border-secondary"
                    placeholder="Ex : Employeur, CAF, France Travail" required>
            </div>

            <!-- MONTANT -->
            <div class="mb-3">
                <label class="form-label text-white">Montant prévu (€)</label>
                <input type="number" step="0.01" name="montant_prevu"
                    class="form-control bg-dark text-white border-secondary" placeholder="Ex : 1800" required>
            </div>

            <!-- DATE DE DÉBUT -->
            <div class="mb-3">
                <label class="form-label text-white">Date de départ</label>
                <input type="date" name="date_depart" class="form-control bg-dark text-white border-secondary"
                    value="<?= date('d-m-Y') ?>" required>
            </div>

            <!-- DATE DE FIN -->
            <div class="mb-3">
                <label class="form-label text-white">Date de fin (optionnelle)</label>
                <input type="date" name="date_fin" class="form-control bg-dark text-white border-secondary"
                    placeholder="Ex : <?= date('d-m-Y', strtotime('+1 year')) ?>">
                <small class="text-muted">Laisser vide si ressource illimitée ou unique</small>
            </div>

            <!-- RECURRENCE -->
            <div class="mb-3">
                <label class="form-label text-white">Récurrence</label>
                <select name="recurrence" class="form-select bg-dark text-white border-secondary" required>

                    <option value="unique">⚡ Unique</option>
                    <option value="mensuelle" selected>📅 Mensuelle</option>
                    <option value="trimestrielle">📆 Trimestrielle</option>
                    <option value="semestrielle">🗓️ Semestrielle</option>
                    <option value="annuelle">📊 Annuelle</option>

                </select>
            </div>

            <!-- CATEGORIE -->
            <div class="mb-3">
                <label class="form-label text-white">Catégorie</label>
                <select name="categorie_id" class="form-select bg-dark text-white border-secondary">

                    <option value="">-- Sans catégorie --</option>

                    <?php
                    $stmt = $pdo->prepare('
                        SELECT id, nom 
                        FROM categories_ressources 
                        WHERE user_id = :user_id
                        ORDER BY nom ASC
                    ');
                    $stmt->execute(['user_id' => $user_id]);

                    foreach ($stmt->fetchAll() as $cat):
                        ?>
                        <option value="<?= $cat['id'] ?>">
                            <?= htmlspecialchars($cat['nom']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <!-- BUTTONS -->
            <div class="d-flex justify-content-between mt-5">
                <button type="submit"
                    onclick="if(this.form.checkValidity()) { this.disabled=true; this.form.submit(); }"
                    class="btn-create-dash shadow-sm">

                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>


                    <span class="btn-text">

                        💾 Enregistrer l'achat

                    </span>


                </button>

                <a href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php" class="btn btn-secondary shadow-sm mt-0">
                <span class="emoji">💰</span> Retour au Tableau de bord Revenus
            </a>
            </div>

        </form>
    </div>
</div>
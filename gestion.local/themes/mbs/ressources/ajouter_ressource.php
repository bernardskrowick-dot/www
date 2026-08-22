<?php
/* Version: v1.16.2 (Rev #24) - 2026-08-05 */
/**
 * ajouter_ressource.php
 * Ajout d'une ressource/revenu
 * Version MBS_DARK V2
 */

// 🔒 Sécurité : Vérification de la session
if (!isset($user_id)) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// -----------------------------
// Traitement AJAX : Création rapide de catégorie (sans quitter la page)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_ajax']) && $_POST['action_ajax'] === 'creer_categorie') {
    if (ob_get_length())
        ob_clean();
    header('Content-Type: application/json');

    if (empty($user_id)) {
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit;
    }

    if (function_exists('verify_csrf_token')) {
        try {
            verify_csrf_token();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur de sécurité CSRF']);
            exit;
        }
    }

    $nomCat = trim($_POST['nom_cat'] ?? '');

    if (!empty($nomCat)) {
        $stmtCatAdd = $pdo->prepare('INSERT INTO categories_ressources (user_id, nom) VALUES (?, ?)');
        $stmtCatAdd->execute([$user_id, $nomCat]);
        $newCatId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'id' => (int) $newCatId,
            'nom' => '📁 ' . $nomCat
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Nom de catégorie vide']);
    exit;
}

?>

<div class="container mb-4 nav-dashboard-container">
    <div class="glass-card-nav mt-4 mb-0">

        <div class="d-flex flex-wrap align-items-center mt-3">
        </div>

        <div class="p-3 pt-0">
            <?php
            if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
                include DIR_LOGIC . 'top-bar-title-page.php';
            }
            ?>
        </div>
        <small class="text-muted d-block text-center">
            Salaire, CAF, France Travail, autres revenus
        </small>
    </div>

    <!-- FORM -->
    <div class="card bg-glass p-4">

        <form method="POST" action="<?= BASE_URL ?>router.php?p=traiter_ressource.php">

            <?= function_exists('csrf_input') ? csrf_input() : '' ?>

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
                    value="<?= date('Y-m-d') ?>" required>
            </div>

            <!-- DATE DE FIN -->
            <div class="mb-3">
                <label class="form-label text-white">Date de fin (optionnelle)</label>
                <input type="date" name="date_fin" class="form-control bg-dark text-white border-secondary"
                    placeholder="Ex : <?= date('Y-m-d', strtotime('+1 year')) ?>">
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
                <label class="form-label small fw-bold text-white-50"><span class="emoji fs-4">📁</span> CATÉGORIE</label>
                <div class="input-group">
                    <select name="categorie_id" id="select_categorie_id" class="form-select bg-dark text-white border-secondary flex-grow-1">
                        <option value="">-- Sans catégorie --</option>
                        <?php
                        $catStmt = $pdo->prepare('
                            SELECT id, nom, parent_id
                            FROM categories_ressources
                            WHERE user_id = ?
                            ORDER BY 
                                COALESCE(parent_id, id),
                                parent_id IS NOT NULL,
                                nom ASC
                        ');
                        $catStmt->execute([$user_id]);

                        foreach ($catStmt->fetchAll() as $c):
                            $nomCategorie = !empty($c['parent_id'])
                                ? '└── 🏷️ ' . $c['nom']
                                : '📁 ' . $c['nom'];
                            ?>
                                <option value="<?= $c['id'] ?>">
                                <?= htmlspecialchars($nomCategorie) ?>
                            </option>
                            <?php endforeach; ?>
                    </select>
                    <button type="button" id="btnOuvrirModal" class="btn btn-outline-warning px-3" title="Créer une nouvelle catégorie">
                        ➕
                    </button>
                </div>
            </div>

            <!-- BUTTONS -->
            <div class="d-flex gap-3 mt-5 w-100">

                <button type="submit"
                    onclick="if(this.form.checkValidity()) { this.disabled=true; this.form.submit(); }"
                    class="btn-create-dash shadow-sm flex-fill">

                    <span class="spinner-border spinner-border-sm d-none"
                        role="status"
                        aria-hidden="true"></span>

                    <span class="btn-text">
                        <span class="emoji fs-4">💾</span> Enregistrer le revenu
                    </span>

                </button>


                <a href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php"
                    class="btn btn-secondary shadow-sm mt-0 flex-fill d-flex align-items-center justify-content-center">

                    <span class="emoji fs-4">💰</span>
                    Retour au Tableau de bord Revenus

                </a>

            </div>

        </form>
    </div>
</div>

<!-- Modal de création rapide de catégorie -->
<div class="modal fade" id="modalNouvelleCategorie" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="modalLabel">📁 Créer une nouvelle catégorie</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="nomNouvelleCategorie" class="form-label text-white">Nom de la catégorie</label>
                    <input type="text" id="nomNouvelleCategorie"
                        class="form-control bg-dark text-white border-secondary"
                        placeholder="Ex: Salaires, Divers...">
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" id="btnSauvegarderCategorie" class="btn btn-success">💾 Enregistrer et sélectionner</button>
            </div>
        </div>
    </div>
</div>

<!-- Script JavaScript pour gérer l'ajout AJAX sans perdre le formulaire -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('modalNouvelleCategorie');
    
    let myModal = null;
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        myModal = new bootstrap.Modal(modalElement);
    }

    document.getElementById('btnOuvrirModal').addEventListener('click', function() {
        if (myModal) {
            myModal.show();
        } else {
            modalElement.classList.add('show');
            modalElement.style.display = 'block';
            document.body.classList.add('modal-open');
        }
    });

    modalElement.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (myModal) {
                myModal.hide();
            } else {
                modalElement.classList.remove('show');
                modalElement.style.display = 'none';
                document.body.classList.remove('modal-open');
            }
        });
    });

    document.getElementById('btnSauvegarderCategorie').addEventListener('click', function() {
        const nomCat = document.getElementById('nomNouvelleCategorie').value.trim();

        if (!nomCat) {
            alert('Veuillez entrer un nom de catégorie.');
            return;
        }

        const csrfInput = document.querySelector('input[name="csrf_token"]');
        const csrfToken = csrfInput ? csrfInput.value : '';

        const formData = new URLSearchParams();
        formData.append('action_ajax', 'creer_categorie');
        formData.append('nom_cat', nomCat);
        if (csrfToken) {
            formData.append('csrf_token', csrfToken);
        }

        fetch('<?= BASE_URL ?>router.php?p=ajouter_ressource.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString()
            })
                .then(response => {
                    const contentType = response.headers.get("content-type");
                    if (!contentType || !contentType.includes("application/json")) {
                        return response.text().then(text => {
                            console.error("Réponse non-JSON du serveur :", text);
                            throw new Error("Le serveur n'a pas renvoyé du JSON (voir console).");
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('select_categorie_id');

                        const newOption = document.createElement('option');
                        newOption.value = data.id;
                        newOption.text = data.nom;
                        newOption.selected = true;
                        select.appendChild(newOption);

                        document.getElementById('nomNouvelleCategorie').value = '';

                        if (myModal) {
                            myModal.hide();
                        } else {
                            modalElement.classList.remove('show');
                            modalElement.style.display = 'none';
                            document.body.classList.remove('modal-open');
                        }
                    } else {
                        alert('Erreur : ' + (data.message || 'Impossible de créer la catégorie'));
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue lors de l\'enregistrement (vérifiez la console F12).');
                });
        });
    });
</script>
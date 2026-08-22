<?php

/**
 * modifier_ressource.php
 * Modification d'une ressource
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
// Traitement AJAX : Création rapide de catégorie (sans quitter la page)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_ajax']) && $_POST['action_ajax'] === 'creer_categorie') {
    // Nettoyage de tout buffer de sortie pour éviter le HTML parasite
    if (ob_get_length())
        ob_clean();
    header('Content-Type: application/json');

    // Vérification de sécurité
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit;
    }

    // Vérification CSRF sécurisée pour l'AJAX
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
        // Insertion de la nouvelle catégorie
        $stmtCatAdd = $pdo->prepare('INSERT INTO categories_ressources (user_id, nom) VALUES (?, ?)');
        $stmtCatAdd->execute([$_SESSION['user_id'], $nomCat]);
        $newCatId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'id' => (int) $newCatId,
            'nom' => $nomCat
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Nom de catégorie vide']);
    exit;
}

// -----------------------------
// Validation ID
// -----------------------------
$idRessource = isset($_GET['id']) && ctype_digit($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if (!$idRessource) {
    header('Location: ' . BASE_URL . 'router.php?p=historique_ressources.php');
    exit;
}

// -----------------------------
// Récupération ressource
// -----------------------------
$stmt = $pdo->prepare('
    SELECT *
    FROM ressources
    WHERE id = :id
      AND user_id = :user_id
    LIMIT 1
');
$stmt->execute([
    ':id' => $idRessource,
    ':user_id' => $_SESSION['user_id']
]);
$ressource = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ressource) {
    echo "<div class='alert alert-danger'>Ressource introuvable.</div>";
    exit;
}

// -----------------------------
// Catégories
// -----------------------------
$stmtCat = $pdo->prepare('
    SELECT id, nom
    FROM categories_ressources
    WHERE user_id = :user_id
    ORDER BY nom ASC
');
$stmtCat->execute([':user_id' => $_SESSION['user_id']]);
$categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------
// Traitement POST (Mise à jour de la ressource)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action_ajax'])) {
    if (function_exists('verify_csrf_token')) {
        verify_csrf_token();
    }

    $titre = trim($_POST['titre'] ?? '');
    $organisme = trim($_POST['organisme'] ?? '');
    $montant = (float) ($_POST['montant_prevu'] ?? 0);
    $dateDepart = $_POST['date_depart'] ?? '';
    $dateFin = $_POST['date_fin'] ?? null;
    $recurrence = $_POST['recurrence'] ?? 'mensuelle';
    $categorieId = !empty($_POST['categorie_id']) ? (int) $_POST['categorie_id'] : null;

    // Vérification catégorie (évite erreur FK)
    if ($categorieId !== null) {
        $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM categories_ressources WHERE id = ? AND user_id = ?');
        $stmtCheck->execute([$categorieId, $_SESSION['user_id']]);
        if ($stmtCheck->fetchColumn() == 0) {
            $categorieId = null;  // catégorie invalide
        }
    }

    // -----------------------------
    // Mise à jour ressource
    // -----------------------------
    $stmtUpdate = $pdo->prepare('
        UPDATE ressources
        SET
            titre = :titre,
            organisme = :organisme,
            montant_prevu = :montant,
            date_depart = :date_depart,
            date_fin = :date_fin,
            recurrence = :recurrence,
            categorie_id = :categorie_id
        WHERE id = :id
          AND user_id = :user_id
    ');

    $stmtUpdate->execute([
        ':titre' => $titre,
        ':organisme' => $organisme,
        ':montant' => $montant,
        ':date_depart' => $dateDepart,
        ':date_fin' => $dateFin,
        ':recurrence' => $recurrence,
        ':categorie_id' => $categorieId,
        ':id' => $idRessource,
        ':user_id' => $_SESSION['user_id']
    ]);

    // -----------------------------
    // Anti-doublon : supprimer les anciens versements
    // -----------------------------
    $pdo
        ->prepare('DELETE FROM versements WHERE ressource_id = ?')
        ->execute([$idRessource]);

    // -----------------------------
    // Regénération des versements
    // -----------------------------
    $start = new DateTime($dateDepart);
    $end = $dateFin ? new DateTime($dateFin) : null;

    switch ($recurrence) {
        case 'unique':
            $step = null;
            break;
        case 'mensuelle':
            $step = '+1 month';
            break;
        case 'trimestrielle':
            $step = '+3 month';
            break;
        case 'semestrielle':
            $step = '+6 month';
            break;
        case 'annuelle':
            $step = '+1 year';
            break;
        default:
            $step = '+1 month';
            break;
    }

    if ($recurrence === 'unique') {
        $stmtVersement = $pdo->prepare("
            INSERT INTO versements (
                ressource_id,
                date_versement_prevue,
                montant_prevu,
                statut
            )
            VALUES (?, ?, ?, 'attendu')
        ");
        $stmtVersement->execute([$idRessource, $start->format('Y-m-d'), $montant]);
    } else {
        $current = clone $start;
        while (true) {
            if ($end && $current > $end)
                break;

            $stmtVersement = $pdo->prepare("
                INSERT INTO versements (
                    ressource_id,
                    date_versement_prevue,
                    montant_prevu,
                    statut
                )
                VALUES (?, ?, ?, 'attendu')
            ");
            $stmtVersement->execute([$idRessource, $current->format('Y-m-d'), $montant]);

            $current->modify($step);
        }
    }

    $_SESSION['flash_success'] = '✅ Ressource modifiée.';

    header('Location: ' . BASE_URL . 'router.php?p=ressource_versements.php&id=' . $idRessource);
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

        <hr class="hr-glass">

        <div class="row justify-content-center">
            <div class="col-lg-10">

                <div class="card bg-glass p-4 border-0">

                    <h2 class="glass-header-small mb-4">
                        ✏️ Modifier la ressource
                    </h2>

                    <form method="POST">

                        <?php csrf_input(); ?>

                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white">Titre</label>
                                <input type="text" name="titre" class="form-control bg-dark text-white border-secondary"
                                    value="<?= htmlspecialchars($ressource['titre']) ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white">Organisme</label>
                                <input type="text" name="organisme"
                                    class="form-control bg-dark text-white border-secondary"
                                    value="<?= htmlspecialchars($ressource['organisme']) ?>" required>
                                </div>

                        </div>

                        <div class="row">

                            <div class="col-md-4 mb-3">
                                <label class="form-label text-white">Montant prévu (€)</label>
                                <input type="number" step="0.01" name="montant_prevu"
                                    class="form-control bg-dark text-white border-secondary"
                                    value="<?= $ressource['montant_prevu'] ?>" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label text-white">Date de départ</label>
                                <input type="date" name="date_depart"
                                    class="form-control bg-dark text-white border-secondary"
                                    value="<?= $ressource['date_depart'] ?>" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label text-white">Date de fin (optionnelle)</label>
                                <input type="date" name="date_fin"
                                    class="form-control bg-dark text-white border-secondary"
                                    value="<?= $ressource['date_fin'] ?? '' ?>">
                                </div>

                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white">Récurrence</label>
                                <select name="recurrence" class="form-select bg-dark text-white border-secondary"
                                    required>

                                    <option value="unique" <?= $ressource['recurrence'] === 'unique' ? 'selected' : '' ?>>⚡
                                        Unique</option>
                                    <option value="mensuelle" <?= $ressource['recurrence'] === 'mensuelle' ? 'selected' : '' ?>>📅 Mensuelle</option>
                                    <option value="trimestrielle" <?= $ressource['recurrence'] === 'trimestrielle' ? 'selected' : '' ?>>📆 Trimestrielle
                                    </option>
                                    <option value="semestrielle" <?= $ressource['recurrence'] === 'semestrielle' ? 'selected' : '' ?>>🗓️ Semestrielle</option>
                                    <option value="annuelle" <?= $ressource['recurrence'] === 'annuelle' ? 'selected' : '' ?>>
                                        📊 Annuelle</option>

                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white">Catégorie</label>
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
                                    <button type="button" id="btnOuvrirModal" class="btn btn-outline-warning"
                                        title="Créer une nouvelle catégorie">
                                        ➕
                                    </button>
                                </div>
                            </div>

                        </div>

                        <div class="row g-3 mt-4">

                            <div class="col-md-6">
                                <button type="submit" class="btn-payer-neon w-100">💾 Enregistrer</button>
                            </div>

                            <div class="col-md-6">
                                <a href="<?= BASE_URL ?>router.php?p=ressource_versements.php&id=<?= $idRessource ?>"
                                    class="btn btn-secondary shadow-sm flex-fill d-flex align-items-center justify-content-center w-100 w-md-auto">
                                    ❌ Annuler
                                </a>
                            
                            </div>

                        </div>

                    </form>

                </div>

            </div>
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
                    <button type="button" id="btnSauvegarderCategorie" class="btn btn-success">💾 Enregistrer et
                        sélectionner</button>
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

        // Récupération automatique du token CSRF présent dans le formulaire principal de la page
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        const csrfToken = csrfInput ? csrfInput.value : '';

        const formData = new URLSearchParams();
        formData.append('action_ajax', 'creer_categorie');
        formData.append('nom_cat', nomCat);
        if (csrfToken) {
            formData.append('csrf_token', csrfToken);
        }

        fetch('<?= BASE_URL ?>router.php?p=modifier_ressource.php&id=<?= $idRessource ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString()
            })
                .then(response => {
                    // Sécurité : si le serveur renvoie autre chose que du JSON (ex: page HTML d'erreur 500)
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
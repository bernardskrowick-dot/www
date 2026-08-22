<?php
/* Version: v1.19.1 (Rev #13) - 2026-08-06 */

/**
 * =====================================================
 * GESTION DES UTILISATEURS - MBS_DARK
 * =====================================================
 * RÈGLES : CSRF, Constantes dossiers, Mots de passe hachés, Commentaires.
 */
// Si la constante DIR_ROOT n'est pas définie, cela signifie qu'on ne passe pas par le router
// On doit donc charger l'initialisation manuellement

/** GESTION DES UTILISATEURS */

// Si on n'est pas passé par le routeur (DIR_ROOT non défini)
if (!defined('DIR_ROOT')) {
    // On redirige vers le router avec le bon paramètre de page
    // On utilise un chemin relatif simple car on sait qu'on est dans /admin/
    header('Location: ../router.php?p=admin/gestion_utilisateurs');
    exit;
}

/*
 * Désormais, le code ci-dessous ne s'exécutera QUE si le routeur a déj�
 * inclus le header, les constantes et la connexion PDO.
 */

// 🔒 Vérification Admin (Sécurisé car le router a déjà lancé la session via init.php)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// ... suite de votre code (CSRF, Traitement, etc.)

// 🔹 Génération CSRF token si inexistant
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 🔹 Messages flash via session
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// 🔹 Récupération des utilisateurs (Note : les mots de passe sont stockés hachés via password_hash)

/*
 * =====================================================
 * SECTION : RÉCUPÉRATION DES UTILISATEURS
 * RÔLE :
 * - Chargement de la liste administrateur
 * - Récupération du niveau de privilège
 * - Permet la protection Super Admin
 * =====================================================
 */

$stmt = $pdo->query('
    SELECT 
        id,
        username,
        email,
        role,
        is_super_admin,
        statut,
        created_at,
        last_activity
    FROM users
    ORDER BY id ASC
');

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// KPI ADMINISTRATION UTILISATEURS
// =====================================================

$totalUsers = count($users);

$totalActifs = 0;
$totalSuspendus = 0;
$totalAdmins = 0;

foreach ($users as $u) {
    if ($u['statut'] === 'actif') {
        $totalActifs++;
    }

    if ($u['statut'] === 'suspendu') {
        $totalSuspendus++;
    }

    if ($u['role'] === 'admin') {
        $totalAdmins++;
    }
}

?>

<div class="container">

    <div>
        <?php
        // Utilisation de la constante globale définie dans init.php
        if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
            include DIR_LOGIC . 'top-bar-title-page.php';
        }
        ?>
    </div>

    <hr class="hr-glass">
    <div class="d-flex flex-column flex-md-row gap-3 mt-4 mb-3">
        <a href="<?= BASE_URL ?>router.php?p=creer_utilisateur.php"
            class="btn-create-dash shadow-sm w-100 w-md-auto d-flex align-items-center justify-content-center text-decoration-none">
            <span class="emoji me-2">👤 ➕</span> Ajouter un utilisateur
        </a>

        <a href="<?= BASE_URL ?>router.php?p=dashboard.php"
            class="btn btn-secondary shadow-sm w-100 w-md-auto ms-md-auto d-flex align-items-center justify-content-center">
            <span class="emoji me-2">🏠</span> Retour au Tableau de Bord
        </a>
    </div>

    <hr class="hr-glass">

    <!-- =====================================================
        KPI ADMINISTRATION UTILISATEURS (DISPOSÉS EN CONTAINER)
    ===================================================== -->
    <div class="row g-3 mb-3 justify-content-center">

        <!-- TOTAL UTILISATEURS -->
        <div class="col-6 col-md-3 col-lg-2">
            <div class="glass-card-nav shadow-lg border-0 mt-2">
                <div class="card-body text-center py-1 px-2">
                    <div class="fs-5 mb-0">👥</div>
                    <h6 class="text-white-50 mb-0 fs-7">Utilisateurs</h6>
                    <div class="fs-4 fw-bold text-white"><?= $totalUsers ?></div>
                </div>
            </div>
        </div>

        <!-- ACTIFS -->
        <div class="col-6 col-md-3 col-lg-2">
            <div class="glass-card-nav shadow-lg border-0 mt-2">
                <div class="card-body text-center py-1 px-2">
                    <div class="fs-5 mb-0">🟢</div>
                    <h6 class="text-white-50 mb-0 fs-7">Actifs</h6>
                    <div class="fs-4 fw-bold text-success"><?= $totalActifs ?></div>
                </div>
            </div>
        </div>

        <!-- SUSPENDUS -->
        <div class="col-6 col-md-3 col-lg-2">
            <div class="glass-card-nav shadow-lg border-0 mt-2">
                <div class="card-body text-center py-1 px-2">
                    <div class="fs-5 mb-0">🔴</div>
                    <h6 class="text-white-50 mb-0 fs-7">Suspendus</h6>
                    <div class="fs-4 fw-bold text-danger"><?= $totalSuspendus ?></div>
                </div>
            </div>
        </div>

        <!-- ADMINS -->
        <div class="col-6 col-md-3 col-lg-2">
            <div class="glass-card-nav shadow-lg border-0 mt-2">
                <div class="card-body text-center py-1 px-2">
                    <div class="fs-5 mb-0">🛡️</div>
                    <h6 class="text-white-50 mb-0 fs-7">Admins</h6>
                    <div class="fs-4 fw-bold text-accent-blue"><?= $totalAdmins ?></div>
                </div>
            </div>
        </div>

    </div>

    <hr class="hr-glass mt-4 mb-3">

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- =====================================================
        TABLEAU UTILISATEURS (RESTE DANS LE CONTAINER)
    ===================================================== -->
    <div class="table-responsive shadow-lg w-100">
        <table class="table table-dark table-hover mb-4 bg-glass table-custom-dark w-100" style="table-layout: fixed;">
            <thead>
                <tr class="text-accent-blue border-bottom border-secondary">
                    <th class="th-title text-center text-white py-2" style="width: 5%;">ID</th>
                    <th class="th-title text-center text-white py-2" style="width: 15%;">Nom</th>
                    <th class="th-title text-center text-white py-2" style="width: 20%;">Email</th>
                    <th class="text-center text-white py-2" style="width: 12%;">Rôle</th>
                    <th class="text-center text-white py-2" style="width: 10%;">Statut</th>
                    <th class="th-date text-center text-white py-2" style="width: 11%;">Création</th>
                    <th class="th-date text-center text-white py-2" style="width: 12%;">Activité</th>
                    <th class="text-center text-white py-2" style="width: 15%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr class="hover-glow border-bottom border-secondary-soft align-middle">
                        <td class="text-center text-white fw-bold"><?= $user['id'] ?></td>

                        <td class="text-center text-truncate">
                            <span class="text-white fw-bold" title="<?= htmlspecialchars($user['username']) ?>">
                                <?= htmlspecialchars($user['username']) ?>
                            </span>
                        </td>

                        <td class="text-center text-muted user-email text-truncate"
                            title="<?= htmlspecialchars($user['email']) ?>">
                            <?= htmlspecialchars($user['email']) ?>
                        </td>

                        <td class="text-center">
                            <?php if ($user['is_super_admin'] == 1): ?>
                                <span class="badge-neon border-warning text-warning px-1 py-1" style="font-size:0.75rem;">
                                    👑 S.ADMIN
                                </span>
                            <?php elseif ($user['role'] === 'admin'): ?>
                                <span class="badge-neon border-accent-blue text-accent-blue px-1 py-1" style="font-size:0.75rem;">
                                    🔷 ADMIN
                                </span>
                            <?php else: ?>
                                <span class="badge-neon border-secondary text-white px-1 py-1" style="font-size:0.75rem;">
                                    👤 USER
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <?php if ($user['statut'] === 'actif'): ?>
                                <span class="badge-neon border-success text-success px-1 py-1" style="font-size:0.75rem;">
                                    🟢 ACTIF
                                </span>
                            <?php else: ?>
                                <span class="badge-neon border-danger text-danger px-1 py-1" style="font-size:0.75rem;">
                                    🔴 SUSP.
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center text-white-50" style="font-size: 0.85rem;">
                            <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                        </td>

                        <td class="text-center text-white-50" style="font-size: 0.85rem;">
                            <?php if (!empty($user['last_activity'])): ?>
                                <?= date('d/m/Y H:i', strtotime($user['last_activity'])) ?>
                            <?php else: ?>
                                <span class="text-muted">Jamais</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center px-1 py-2">
                            <?php
                            $sessionSuperAdmin = $_SESSION['is_super_admin'] ?? 0;
                            $userSuperAdmin = $user['is_super_admin'] ?? 0;

                            $peutGererUtilisateur = ($sessionSuperAdmin == 1 || $userSuperAdmin == 0);
                            $peutChangerStatut = ($peutGererUtilisateur && $user['id'] != $_SESSION['user_id']);
                            $peutSupprimer = ($user['id'] != $_SESSION['user_id'] && ($sessionSuperAdmin == 1 || $userSuperAdmin == 0));
                            ?>

                            <div class="d-flex flex-column gap-1 align-items-center">

                                <?php if ($peutGererUtilisateur): ?>
                                    <a href="<?= BASE_URL ?>router.php?p=modifier_utilisateur.php&id=<?= $user['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                                        class="btn-modifier-neon w-100 text-center py-1 text-decoration-none" title="Modifier">
                                        ✏️ <span class="d-none d-xl-inline">Modifier</span>
                                    </a>

                                    <a href="<?= BASE_URL ?>router.php?p=reset_mdp.php&id=<?= $user['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                                        class="btn-neon-cyan w-100 text-center py-1 text-decoration-none"
                                        onclick="return confirm('Réinitialiser le mot de passe ?')">
                                        🔑 <span class="d-none d-xl-inline">Reset MDP</span>
                                    </a>
                                <?php endif; ?>

                                <?php if ($peutChangerStatut): ?>
                                    <?php if ($user['statut'] === 'actif'): ?>
                                        <a href="<?= BASE_URL ?>router.php?p=changer_statut_utilisateur.php&id=<?= $user['id'] ?>"
                                            class="btn-suspendre-neon w-100 text-center py-1 text-decoration-none"
                                            onclick="return confirm('Suspendre cet utilisateur ?')">
                                            ⏸️ <span class="d-none d-xl-inline">Suspendre</span>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>router.php?p=changer_statut_utilisateur.php&id=<?= $user['id'] ?>"
                                            class="btn-reactiver-neon w-100 text-center py-1 text-decoration-none"
                                            onclick="return confirm('Réactiver cet utilisateur ?')">
                                            ▶️ <span class="d-none d-xl-inline">Réactiver</span>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($peutSupprimer): ?>
                                    <a href="<?= BASE_URL ?>router.php?p=supprimer_utilisateur.php&id=<?= $user['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                                        class="btn-neon-purple w-100 text-center py-1 text-decoration-none"
                                        onclick="return confirm('Supprimer définitivement cet utilisateur ?')">
                                        🗑️ <span class="d-none d-xl-inline">Supprimer</span>
                                    </a>
                                <?php endif; ?>

                                <?php if (!$peutGererUtilisateur): ?>
                                    <span class="text-muted small">🔒 Protégé</span>
                                <?php endif; ?>

                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div> <!-- FERMETURE UNIQUE DU CONTAINER -->
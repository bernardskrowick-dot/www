<?php
/* Version: v1.21.0 (Rev #12) - 2026-08-08 */
require_once __DIR__ . '/../includes/header.php';

/* ----------------------------- Sécurité ----------------------------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

/* ----------------------------- Initialisation ----------------------------- */
$error = '';
$success = '';

// 🎨 Récupérer les thèmes disponibles via la constante DIR_THEMES
$themes_disponibles = array_filter(glob(DIR_THEMES . '*'), 'is_dir');

/* ----------------------------- Traitement POST ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
    // 🎨 Nouveau : Récupération du thème (défaut 'mbs')
    $theme = $_POST['theme'] ?? 'mbs';

    if (!$username || !$email || !$password) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
        $error = 'Le mot de passe doit contenir au minimum 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.';
    } else {
        $stmtCheck = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email');
        $stmtCheck->execute(['username' => $username, 'email' => $email]);

        if ($stmtCheck->fetch()) {
            $error = "Nom d'utilisateur ou email déjà utilisé.";
        } else {
            // 🛡️ RÈGLE : Application du hachage du mot de passe
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // 📝 Insertion sécurisée incluant le thème
            $stmt = $pdo->prepare('
                INSERT INTO users (username, email, password_hash, role, theme)
                VALUES (:username, :email, :password_hash, :role, :theme)
            ');
            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'password_hash' => $password_hash,
                'role' => $role,
                'theme' => $theme
            ]);

            // 📢 RÈGLE : Syntaxe propre, sécurisée et redirection
            $_SESSION['flash_success'] = '✅ Utilisateur ' . htmlspecialchars($username) . ' créé avec succès.';
            header('Location: ' . BASE_URL . 'router.php?p=gestion_utilisateurs.php');
            exit;
        }
    }
}
?>

<div class="container pb-5">

    <div>
        <?php
        // Utilisation de la constante globale définie dans init.php
        if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
            include DIR_LOGIC . 'top-bar-title-page.php';
        }
        ?>

    </div>

    <hr class="hr-glass">


    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST">
        <?php csrf_input(); ?>

        <div class="mb-3">
            <label class="form-label">Nom d'utilisateur *</label>
            <input type="text" name="username" class="form-control" required
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control" required
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Mot de passe *</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Rôle</label>
                <select name="role" class="form-select">
                    <option value="user" <?= (($_POST['role'] ?? '') === 'user') ? 'selected' : '' ?>>Utilisateur</option>
                    <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Thème par défaut</label>
                <select name="theme" class="form-select">
                    <?php foreach ($themes_disponibles as $tPath):
                        $tName = basename($tPath); ?>
                        <option value="<?= $tName ?>" <?= (($_POST['theme'] ?? 'mbs') === $tName) ? 'selected' : '' ?>>
                            <?= ucfirst($tName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="d-flex flex-column flex-md-row gap-3 mt-4 mb-3">
            <button type="submit" class="btn-create-dash shadow-sm w-100 w-md-auto">
                <span class="emoji me-2">💾</span> Enregistrer
            </button>

            <button type="Effacer" class="btn btn-secondary shadow-sm w-100 w-md-auto">
                <span class="emoji me-2">🧹</span> Effacer
            </button>

            <a href="<?= BASE_URL ?>router.php?p=gestion_utilisateurs.php"
                class="btn btn-secondary shadow-sm w-100 w-md-auto ms-md-auto d-flex align-items-center justify-content-center">
                <span class="emoji me-2">👥</span> Retour gestion utilisateur
            </a>
        </div>
    </form>
</div>
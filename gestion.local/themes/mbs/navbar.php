<?php
/* Version: v1.16.2 (Rev #24) - 2026-08-05 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Récupération sécurisée des données de session
$user_role = $_SESSION['role'] ?? '';
$user_id = (int) ($_SESSION['user_id'] ?? 0);
?><?php

$stickyPosition = $_SESSION['user_sticky_pos'] ?? 'right';

/*
 * La navbar utilisateur prend la position opposée
 * au menu sticky principal
 */
$navbarPosition = ($stickyPosition === 'left') ? 'right' : 'left';

$navbarClass = ($navbarPosition === 'left')
    ? 'navbar-user-left'
    : 'navbar-user-right';

/* Détection de la page courante */
$currentPage = $_GET['p'] ?? '';
?>

<div class="navbar-user-sticky <?= $navbarClass ?>">
    
    <div class="navbar-user-container">

        <?php if ($user_role === 'admin'): ?>

            <a href="<?= BASE_URL ?>router.php?p=creer_utilisateur.php"
               class="navbar-user-item <?= ($currentPage === 'creer_utilisateur.php') ? 'active' : '' ?>">
                <span class="navbar-icon">👤</span> 
                <span class="navbar-badge">Créer</span>
            </a>

            <a href="<?= BASE_URL ?>router.php?p=gestion_utilisateurs.php"
               class="navbar-user-item <?= ($currentPage === 'gestion_utilisateurs.php') ? 'active' : '' ?>">
                <span class="navbar-icon">👥</span> 
                <span class="navbar-badge">Utilisateurs</span>
            </a>

            <a href="<?= BASE_URL ?>router.php?p=structure_projet.php"
               class="navbar-user-item <?= ($currentPage === 'structure_projet.php') ? 'active' : '' ?>">
                <span class="navbar-icon">📂</span> 
                <span class="navbar-badge">Structure</span>
            </a>

        <?php endif; ?>

        <a href="<?= BASE_URL ?>router.php?p=profil/profil_utilisateur.php"
           class="navbar-user-item <?= ($currentPage === 'profil/profil_utilisateur.php') ? 'active' : '' ?>">
            <span class="navbar-icon">⚙️</span> 
            <span class="navbar-badge">Profil</span>
        </a>

        <a href="<?= BASE_URL ?>logout.php"
           class="navbar-user-item navbar-user-danger">
            <span class="navbar-icon">🔒</span> 
            <span class="navbar-badge">Quitter</span>
        </a>

    </div>

</div>
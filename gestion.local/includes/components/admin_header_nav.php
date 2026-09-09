<?php

/**
 * Composant : En-tête de navigation et titre de page (Menu Administrateur)
 * Logique : Vérifie et inclut dynamiquement le titre de la page courante.
 * Section Menu Administrateur et Titre de la page  
 */
?>

<div class="glass-card-nav mt-4 mb-0">

  <div class="d-flex flex-wrap align-items-center mt-3">
  </div>

  <div class="p-3 pt-0">
    <?php
    // Utilisation de la constante globale définie dans init.php
    if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
      include DIR_LOGIC . 'top-bar-title-page.php';
    }
    ?>
  </div>
</div>
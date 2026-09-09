<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * Section Menu Revenus Appel page  
 */
?>
<div class="d-flex justify-content-between align-items-center mt-2 mb-2">
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= BASE_URL ?>router.php?p=ajouter_ressource.php" class="btn btn-action-dash btn-dash-green px-3 text-nowrap">
      <span class="emoji fs-4">💰</span> Ajouter un revenu
    </a>
    <a href="<?= BASE_URL ?>router.php?p=historique_ressources.php"
      class="btn btn-action-dash btn-dash-blue px-3 text-nowrap">
      <span class="emoji fs-4">📜</span> Historique revenu
    </a>
    <a href="<?= BASE_URL ?>router.php?p=organismes.php"
      class="btn btn-action-dash btn-dash-indigo px-3 ">
      <span class="emoji fs-4">🏛️</span> Organismes
    </a>
    <a href="<?= BASE_URL ?>router.php?p=categories_ressources.php"
      class="btn btn-action-dash btn-dash-orange px-3 ">
      <span class="emoji fs-4">🏷️</span> Catégories ressources
    </a>
    <a href="<?= BASE_URL ?>router.php?p=statistiques_revenus.php"
      class="btn btn-action-dash btn-dash-silver px-3">
      <span class="emoji fs-4">📊</span> Statistiques ressources
    </a>
    <a href="<?= BASE_URL ?>index.php" class="btn btn-action-dash px-3" title="Retour au tableau de bord"
      aria-label="Retour au tableau de bord">
      <span class="emoji fs-4">🏠</span>
      Retour Tableau de bord
    </a>
  </div>
</div>
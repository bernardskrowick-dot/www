<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * Section Menu Appel page  
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div class="d-flex gap-2 flex-wrap">

    <a href="<?= BASE_URL ?>router.php?p=ajouter_achat.php" class="btn btn-action-dash btn-dash-green">
      <span class="emoji fs-4">➕</span> Ajouter un achat
    </a>

    <a href="<?= BASE_URL ?>router.php?p=historique.php" class="btn btn-action-dash btn-dash-silver">
      <span class="emoji fs-4">📜</span> Historique Achats
    </a>

    <a href="<?= BASE_URL ?>router.php?p=marchands.php" class="btn btn-action-dash btn-dash-blue">
      <span class="emoji fs-4">🏪</span> Mes Marchands
    </a>

    <a href="<?= BASE_URL ?>router.php?p=gestion_echeances.php&statut_filter=a_venir&marchand_filter=&tri_filter=titre"
      class="btn btn-action-dash btn-dash-orange d-inline-flex align-items-center">
      <span class="emoji fs-4">📅</span> Suivi Échéances
      <?php if ($nbReste > 0): ?>
        <span class="badge-neon ms-2"><?= $nbReste ?></span>
      <?php endif; ?>
    </a>

    <a href="<?= BASE_URL ?>router.php?p=statistiques.php" class="btn btn-action-dash btn-dash-purple">
      <span class="emoji fs-4">📊</span> Statistiques Achats
    </a>

    <a href="<?= BASE_URL ?>router.php?p=gestion_categories.php"
      class="btn btn-action-dash btn-dash-indigo">
      <span class="emoji fs-4">📁</span> Catégories Achats
    </a>
    <a href="<?= BASE_URL ?>index.php" class="btn btn-action-dash px-3" title="Retour au tableau de bord"
      aria-label="Retour au tableau de bord">
      <span class="emoji fs-4">🏠</span>
      Retour Tableau de bord
    </a>
  </div>
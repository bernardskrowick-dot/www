<?php

/**
 * Fichier : components/tableau_mensuel_menu_actions.php
 * RÔLE : Affiche le bouton de retour, le bouton bascule d'historique et l'en-tête de section "Échéances par mois".
 */

// Variables attendues : $filtreMarchand, $afficherTout, $echeancesParMois
?>

<div class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
  <a href="<?= BASE_URL ?>index.php<?= $filtreMarchand ? '?marchand=' . urlencode($filtreMarchand) : '' ?>"
    class="btn btn-secondary shadow-sm" title="Retour au tableau de bord"
    aria-label="Retour au tableau de bord">
    <span class="emoji">🏠</span> Retour au Tableau de bord
  </a>

  <div>
    <?php if ($afficherTout): ?>
      <a href="<?= BASE_URL ?>router.php?p=Tableau_mensuel.php<?= $filtreMarchand ? '&marchand=' . urlencode($filtreMarchand) : '' ?>"
        class="btn-modifier-neon shadow-sm px-3 text-nowrap text-decoration-none d-inline-flex align-items-center justify-content-center"
        style="height: 38px;">
        👁️ Masquer l'historique ancien (Vue courant/futur)
      </a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>router.php?p=Tableau_mensuel.php&afficher_tout=1<?= $filtreMarchand ? '&marchand=' . urlencode($filtreMarchand) : '' ?>"
        class="btn-create-dash shadow-sm px-3 text-nowrap text-decoration-none d-inline-flex align-items-center justify-content-center"
        style="height: 38px;">
        📜 Afficher tout l'historique
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="card glass-card-nav mb-4" style="border: none;">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center">
      <h2 class="glass-header-small mb-0">
        <span class="emoji">📅</span> Échéances par mois
      </h2>
      <span class="small fw-bold text-uppercase" style="letter-spacing: 1px;">
        <?= count($echeancesParMois) ?> Mois affichés
      </span>
    </div>
  </div>
</div>
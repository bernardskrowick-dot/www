<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * Revenus à percevoir 
 */
?>
<div class="col-md-4">

  <div class="glass-card-nav  p-3 card-kpi h-100 d-flex flex-column justify-content-between">

    <h6 class="form-label">
      <span class="emoji fs-4">💰</span> <?= $titreKpiCategorie ?>

      <!-- Infobulle Catégorie dynamique -->
      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Affichage contextuel selon le filtre de catégorie sélectionné.
          <br><br>
          Calcul : somme des montants perçus pour la catégorie active ou indication générale si toutes
          catégories.
        </span>
      </span>
    </h6>

    <?php if ($categorieFilter > 0): ?>

      <small class="text-muted d-block mt-2">
        Total encaissé pour cette catégorie
      </small>
      <span class="badge-neon badge-neon-green fs-5">
        <?= number_format($totalRevenusRecu, 2) ?> €
      </span>
    <?php else: ?>

      <small class="text-muted d-block mt-2">
        Toutes catégories confondues
      </small>
      <span class="badge-neon badge-neon-green fs-5">
        <?= number_format($totalRevenusRecu, 2) ?> €
      </span>

    <?php endif; ?>

  </div>

</div>
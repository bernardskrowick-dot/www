<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * Montant prévu 
 */
?>
<div class="col-md-4 mb-4 mb-md-0">
  <div class="glass-card-nav  p-3 card-kpi h-100 d-flex flex-column justify-content-between">
    <div>
      <h6 class="form-label">
        <span class="emoji fs-4">💶</span> Montant prévu
        <?php
        /** ==========================================================================
         *  Infobulle Montant prévu 
         *  ========================================================================== */
        ?>

        <span class="info-tooltip ms-1 text-info fs-4">
          ⓘ
          <span class="info-tooltip-text">
            Montant total de l'ensemble des revenus enregistrés ou estimés.
            <br><br>
            Calcul : somme globale des montants théoriques de toutes les ressources de revenus.
          </span>
        </span>
      </h6>
    </div>
    <div class="my-auto">
      <span class="badge-neon badge-neon-green fs-5">
        <?= number_format($totalRevenusPrevu, 2) ?> €
      </span>
    </div>
    <div>

    </div>
  </div>

<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * Montant prévu 
 */
?>
<div class="col-md-4">
  <div class="glass-card-nav  p-3 card-kpi h-100 d-flex flex-column justify-content-between">

    <h6 class="form-label">
      <span class="emoji fs-4">💼</span> Sources de revenus

      <!-- Infobulle Sources de revenus -->
      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Nombre total de ressources de revenus enregistrées.
          <br><br>
          Calcul : décompte de l'ensemble des sources de revenus configurées.
        </span>
      </span>
    </h6>

    <span class="badge-neon badge-neon-blue fs-5">
      <?= $totalRessources ?>
    </span>

  </div>
</div>
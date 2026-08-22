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
      <span class="emoji fs-4">📅</span> Versements

      <!-- Infobulle Versements -->
      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Nombre total d'échéances ou versements de revenus prévus.
          <br><br>
          Calcul : somme cumulée du nombre de versements de toutes les ressources.
        </span>
      </span>
    </h6>

    <span class="badge-neon badge-neon-blue fs-5">
      <?= $totalVersementsRevenus ?>
    </span>

  </div>

</div>
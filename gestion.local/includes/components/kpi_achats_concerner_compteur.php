<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * KPI 1 : ACHATS CONCERNÉS  
 */
?>
<div class="col-md-3 mb-3 mb-md-0">
  <div class="form-label bg-dark bg-opacity-75 border border-secondary border-opacity-50 rounded-4 p-3 shadow-sm h-100 d-flex flex-column justify-content-between">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="text-uppercase small fw-bold text-white-50">🛒 Achats concernés</span>
      <span class="info-tooltip text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Nombre total de dossiers d'achats correspondant aux critères et filtres actuellement sélectionnés.
        </span>
      </span>
    </div>
    <div>
      <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-3 px-3 py-2 w-100 d-block">
        <?= $totalAchats ?>
      </span>
    </div>
  </div>
</div>
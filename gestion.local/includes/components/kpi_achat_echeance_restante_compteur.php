<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * KPI 4 : ÉCHÉANCES RESTANTES 
 */
?>
<div class="col-md-3 mb-3 mb-md-0">
  <div
    class="form-label bg-dark bg-opacity-75 border border-secondary border-opacity-50 rounded-4 p-3 shadow-sm h-100 d-flex flex-column justify-content-between">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="text-uppercase small fw-bold text-white-50">⏳ Échéances restantes</span>
      <span class="info-tooltip text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Nombre d'échéances qui n'ont pas encore le statut 'payée' parmi les achats filtrés.
        </span>
      </span>
    </div>
    <div>
      <span
        class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fs-3 px-3 py-2 w-100 d-block">
        <?= $totalEcheancesRestantesFiltre ?>
      </span>
    </div>
  </div>
</div>
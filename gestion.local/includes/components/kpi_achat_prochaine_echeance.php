<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * KPI 7 : PROCHAINE ÉCHÉANCE IMMINENTE   
 */
?>
<div class="col-md-4 mb-3 mb-md-0">
  <div class="form-label bg-dark bg-opacity-75 border border-secondary border-opacity-50 rounded-4 p-3 shadow-sm h-100 d-flex flex-column justify-content-between">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="text-uppercase small fw-bold text-white-50">⏰ Prochaine échéance</span>
      <span class="info-tooltip text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Date et montant de la toute prochaine échéance non payée parmi les achats filtrés.
        </span>
      </span>
    </div>
    <div>
      <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 fs-4 px-2 py-3 w-100 d-block text-truncate">
        <?= $prochaineEcheanceGlobale ? date('d/m/Y', strtotime($prochaineEcheanceGlobale)) . ' (' . number_format($prochainMontantGlobal, 2, ',', ' ') . ' €)' : 'Aucune' ?>
      </span>
    </div>
  </div>
</div>
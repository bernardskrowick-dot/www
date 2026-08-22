<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 1. TOTAL REVENUS
 */
?>

<div class="col-12 col-md-4">
  <div class="form-label glass-card-nav p-3 card-kpi h-100">
    <h5 class="form-label fs-5">
      <span class="emoji fs-4">💰</span> Revenus cumulés
      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Montant total des revenus déjà encaissés sur la période sélectionnée.
          <br><br>
          Calcul : somme des versements effectivement reçus.
        </span>
      </span>
    </h5>
    <span class="badge-neon badge-neon-success fs-5">
      <?= htmlspecialchars(number_format($totalRevenusRecu ?? 0, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €
    </span>
    <small class="text-muted mt-2 d-block">
      Prévu :
      <?= htmlspecialchars(number_format($totalRevenusPrevu ?? 0, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €
    </small>
  </div>
</div>
<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 1. AVANCEMENT GLOBAL DES REVENUS
 */
?>
<div class="col-12 col-md-6">
  <div class="glass-card-nav">
    <div class="form-label d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="glass-header-small mb-0 fs-4">
          <span class="emoji fs-4">💰</span> Avancement global des revenus

          <span class="info-tooltip ms-1 text-info">
            ⓘ
            <span class="info-tooltip-text">
              Indique le pourcentage des revenus prévus ayant déjà été encaissés.
              <br><br>
              Calcul : Revenus encaissés ÷ Revenus prévus × 100.
            </span>
          </span>

        </h2>
      </div>
      <div>
        <span class="badge-neon badge-neon-blue badge-percent text-accent-blue fw-bold fs-5">
          <?= htmlspecialchars(number_format($pourcentageRevenus, 1, ',', ' ')) ?>
          <span class="percent-symbol">%</span>
        </span>

      </div>
    </div>

    <div class="progress progress-custom-dark">
      <div class="progress-bar progress-bar-neon bg-neon-blue" role="progressbar"
        style="width: <?= floatval($pourcentageRevenus) ?>%;"
        aria-valuenow="<?= floatval($pourcentageRevenus) ?>" aria-valuemin="0" aria-valuemax="100">
      </div>
    </div>

    <div class="d-flex justify-content-between mt-2">
      <small class="text-label-muted">Revenus prévus</small>
      <small class="text-label-muted">100% encaissé</small>
    </div>
  </div>
</div>
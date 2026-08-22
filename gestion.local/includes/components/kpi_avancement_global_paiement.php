<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 2. AVANCEMENT GLOBAL DES PAIEMENTS 
 */
?>
<div class="col-12 col-md-6">
  <div class="glass-card-nav">
    <div class="form-label d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="glass-header-small mb-0 fs-4">
          <span class="emoji fs-4">🚀</span> Avancement global des paiements

          <span class="info-tooltip ms-1 text-info">
            ⓘ
            <span class="info-tooltip-text">
              Indique le pourcentage des dépenses engagées qui ont déjà été réglées.
              <br><br>
              Calcul : Montant des paiements effectués ÷ Montant total des dépenses engagées ×
              100.
            </span>
          </span>

        </h2>
      </div>
      <div>
        <span class="badge-neon badge-neon-green badge-percent text-accent-green fw-bold fs-5">
          <?= htmlspecialchars(number_format($pourcentageGlobal, 1, ',', ' ')) ?>
          <span class="percent-symbol">%</span>
        </span>
      </div>
    </div>

    <div class="progress progress-custom-dark">
      <div class="progress-bar progress-bar-neon bg-neon-green" role="progressbar"
        style="width: <?= floatval($pourcentageGlobal) ?>%;"
        aria-valuenow="<?= floatval($pourcentageGlobal) ?>" aria-valuemin="0" aria-valuemax="100">
      </div>
    </div>

    <div class="d-flex justify-content-between mt-2">
      <small class="text-label-muted">Début du cycle</small>
      <small class="text-label-muted">Objectif 100%</small>
    </div>
  </div>
</div>
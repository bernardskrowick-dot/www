<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * Revenus à percevoir 
 */
?>
<div class="col-12">
  <div class="form-label glass-card-nav p-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="glass-header-small mb-0">
          <span class="emoji fs-4">💰</span> Avancement global des revenus

          <!-- Infobulle Avancement global des revenus -->
          <span class="info-tooltip ms-1 text-info fs-4">
            ⓘ
            <span class="info-tooltip-text">
              Pourcentage de réalisation du montant total des revenus prévus.
              <br><br>
              Calcul : (Total revenus perçus / Montant prévu) × 100.
            </span>
          </span>
        </h2>
      </div>

      <div>
        <span class="badge-neon badge-neon-blue badge-percent text-accent-blue fw-bold fs-5">
          <?= number_format($pourcentageRevenus, 1) ?> %
        </span>
      </div>
    </div>

    <div class="progress progress-custom-dark">
      <div class="progress-bar progress-bar-neon bg-neon-blue" role="progressbar"
        style="width: <?= $pourcentageRevenus ?>%;" aria-valuenow="<?= $pourcentageRevenus ?>"
        aria-valuemin="0" aria-valuemax="100">
      </div>
    </div>

    <div class="d-flex justify-content-between mt-2">
      <small class="text-label-muted">Revenus prévus</small>
      <small class="text-label-muted">100% encaissé</small>
    </div>

  </div>
</div>
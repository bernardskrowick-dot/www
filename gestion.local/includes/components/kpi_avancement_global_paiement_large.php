<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 2. AVANCEMENT GLOBAL DES PAIEMENTS FORMAT LARGE
 */
?>
<div class="col-12">
  <div class="form-label glass-card-nav p-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="glass-header-small mb-0">
          <span class="emoji">🚀</span> Avancement Global des paiements
          <?php
          /** ==========================================================================
           *  Tooltip d'explication de l'avancement global
           *  ========================================================================== */
          ?>
          <span class="info-tooltip ms-1 text-info">
            ⓘ
            <span class="info-tooltip-text">
              Pourcentage global du montant total déjà réglé sur l'ensemble des achats
              filtrés.
              Calcul : (Montant total déjà payé / Montant total engagé) × 100.
            </span>
          </span>
        </h2>
      </div>
      <div>
        <span class="badge-neon badge-neon-green badge-percent text-accent-green fw-bold fs-5">
          <?= number_format($pourcentageGlobal, 1, ',', ' ') ?> <span
            class="percent-symbol">%</span>
        </span>
      </div>
    </div>

    <?php
    /** ==========================================================================
     *  Barre de progression de l'avancement
     *  ========================================================================== */
    ?>
    <div class="progress progress-custom-dark">
      <div class="progress-bar progress-bar-neon bg-neon-green" role="progressbar"
        style="width: <?= $pourcentageGlobal ?>%;" aria-valuenow="<?= $pourcentageGlobal ?>"
        aria-valuemin="0" aria-valuemax="100">
      </div>
    </div>

    <div class="d-flex justify-content-between mt-2">
      <small class="text-label-muted">Début du cycle</small>
      <small class="text-label-muted">Objectif 100%</small>
    </div>

  </div>
</div>
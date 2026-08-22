<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 12. TENDANCE DU SOLDE 
 */
?>
<div class="col-12 col-md-4">
  <div class="form-label glass-card-nav p-3 card-kpi h-100">
    <h5 class="form-label fs-5">
      <span class="emoji fs-4">📊</span> Tendance

      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Analyse l'évolution prévue du solde en fonction des flux financiers connus.
          <br><br>
          Calcul : Revenus prévus - dépenses prévues sur la période analysée.
        </span>
      </span>

    </h5>
    <span
      class="badge-neon badge-neon-<?= htmlspecialchars($statutTendance, ENT_QUOTES, 'UTF-8'); ?> fs-5">
      <?= htmlspecialchars($signeTendance . number_format($tendanceSolde, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?>
      €
    </span>
    <div class="text-muted mt-2 small">
      <strong class="d-block text-white">
        <?= htmlspecialchars($badgeTendance, ENT_QUOTES, 'UTF-8'); ?>
      </strong>
      <span
        class="d-block text-muted"><?= htmlspecialchars($texteTendance, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
  </div>
</div>
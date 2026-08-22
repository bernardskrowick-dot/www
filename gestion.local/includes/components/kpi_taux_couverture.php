<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 8. TAUX DE COUVERTURE
 */
?>
<div class="col-12 col-md-4">
  <div class="form-label glass-card-nav p-3 card-kpi h-100">
    <h5 class="form-label fs-5">
      <span class="emoji fs-4">📊</span> Taux de couverture

      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Mesure la capacité des revenus à couvrir les charges prévues sur la période.
          <br><br>
          Calcul : Revenus du mois ÷ Charges du mois × 100.
        </span>
      </span>

    </h5>
    <span
      class="badge-neon badge-neon-<?= htmlspecialchars($statutCouverture, ENT_QUOTES, 'UTF-8'); ?> fs-5">
      <?= htmlspecialchars(number_format($tauxCouverture, 1, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> %
    </span>
    <div class="text-muted mt-2 small">
      <strong class="d-block text-white">
        Statut : <?= htmlspecialchars($badgeCouverture, ENT_QUOTES, 'UTF-8'); ?>
      </strong>
      <span class="d-block text-muted">Revenus / Charges du mois</span>
      <span class="d-block text-muted">Objectif : &gt; 100 %</span>
    </div>
  </div>
</div>
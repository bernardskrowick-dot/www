<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 15. ALERTE DÉCOUVERT DATE 
 */
?>

<div class="col-12 col-md-4">
  <div class="form-label glass-card-nav p-3 card-kpi h-100">
    <h5 class="form-label fs-5">
      <span class="emoji fs-4">⚠️</span> Alerte découverts

      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Prévoit un risque de solde négatif avant la prochaine échéance de relevé.
          <br><br>
          Calcul : Solde prévisionnel minimum observé sur la période si les dépenses prévues sont
          maintenues.
        </span>
      </span>

    </h5>
    <?php if ($alerteDecouvertDate): ?>
      <span class="badge-neon badge-neon-danger fs-5">
        - <?= htmlspecialchars(number_format($montantDecouvertAlerte, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?>
        €
      </span>
      <div class="text-muted mt-2 small">
        <strong class="d-block text-danger">
          🚨 Risque de découvert
        </strong>
        <span class="d-block text-muted">
          Attention, le <?= htmlspecialchars($dateDecouvertFormat, ENT_QUOTES, 'UTF-8'); ?>, vous serez à
          découvert de
          <?= htmlspecialchars(number_format($montantDecouvertAlerte, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?>
          €.
        </span>
      </div>
    <?php else: ?>
      <span class="badge-neon badge-neon-success fs-5">
        0,00 €
      </span>
      <div class="text-muted mt-2 small">
        <strong class="d-block text-white">
          ✅ Aucun découvert prévu
        </strong>
        <span class="d-block text-muted">Le solde reste positif sur toute la période</span>
      </div>
    <?php endif; ?>
  </div>
  
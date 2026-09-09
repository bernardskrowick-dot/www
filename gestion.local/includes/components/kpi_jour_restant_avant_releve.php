<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 11. JOURS RESTANTS AVANT RELEVÉ
 */
?>
<div class="col-12 col-md-4">
  <div class="form-label glass-card-nav p-3 card-kpi h-100">
    <h5 class="form-label fs-5">
      <span class="emoji fs-4">📅</span> Jours restants

      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Indique le nombre de jours disponibles avant la prochaine date de relevé bancaire.
          <br><br>
          Calcul : Date du prochain relevé - date du jour.
        </span>
      </span>

    </h5>
    <span
      class="badge-neon badge-neon-<?= htmlspecialchars($statutJoursRestants, ENT_QUOTES, 'UTF-8'); ?> fs-5">
      <?= htmlspecialchars((string) $joursRestantsReleve, ENT_QUOTES, 'UTF-8'); ?>
      jour<?= $joursRestantsReleve > 1 ? 's' : ''; ?>
    </span>
    <div class="text-muted mt-2 small">
      <strong class="d-block text-white">
        <?= htmlspecialchars($badgeJoursRestants, ENT_QUOTES, 'UTF-8'); ?>
      </strong>
      <span class="d-block text-muted">
        avant le prochain relevé bancaire
        (<?= htmlspecialchars($dateProchainReleve->format('d/m/Y'), ENT_QUOTES, 'UTF-8'); ?>)
      </span>
    </div>
  </div>
</div>
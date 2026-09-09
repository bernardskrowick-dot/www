<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 4. TOTAL DÉPENSES 
 */
?>
<div class="col-12 col-md-4">
  <div class="form-label glass-card-nav p-3 card-kpi h-100">
    <h5 class="form-label fs-5">
      <span class="emoji fs-4">🛡️</span> Reste à vivre

      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Indique le montant réellement disponible après prise en compte du solde actuel, des
          revenus attendus et des dépenses restantes.
          <br><br>
          Calcul : Solde du jour + entrées attendues - échéances restantes.
        </span>
      </span>

    </h5>
    <span
      class="badge-neon badge-neon-<?= htmlspecialchars($statutResteAVivre, ENT_QUOTES, 'UTF-8'); ?> fs-5">
      <?= htmlspecialchars(number_format($resteAVivre, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €
    </span>
    <div class="text-muted mt-2 small">
      <span class="d-block text-white">
        Solde du jour :
        <?= htmlspecialchars(number_format($soldeReel, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €
      </span>
      <span class="d-block text-success">
        + Entrées attendues :
        <?= htmlspecialchars(number_format($revenusAttendusMois, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?>
        €
      </span>
      <span class="d-block text-danger">
        - À payer :
        <?= htmlspecialchars(number_format($echeancesRestantesMois, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?>
        €
      </span>
    </div>
  </div>
</div>
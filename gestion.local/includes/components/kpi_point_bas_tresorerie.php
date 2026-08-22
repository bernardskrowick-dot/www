<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 9. POINT BAS DE TRÉSORERIE 
 */
?>
<div class="col-12 col-md-4">
  <div class="form-label glass-card-nav p-3 card-kpi h-100">
    <h5 class="form-label fs-5">
      <span class="emoji fs-4">📉</span> Point bas trésorerie

      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Identifie le niveau de trésorerie minimum estimé pendant la période analysée.
          <br><br>
          Calcul : solde minimum observé après prise en compte des flux entrants et sortants
          prévus.
        </span>
      </span>

    </h5>
    <span
      class="badge-neon badge-neon-<?= htmlspecialchars($statutPointBas, ENT_QUOTES, 'UTF-8'); ?> fs-5">
      <?= htmlspecialchars(number_format($pointBasTresorerie, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €
    </span>
    <div class="text-muted mt-2 small">
      <strong class="d-block text-white">
        <?= htmlspecialchars($badgePointBas, ENT_QUOTES, 'UTF-8'); ?>
      </strong>
      <span class="d-block text-info ">
        📅 Atteint le :
        <?= htmlspecialchars(date('d/m/Y', strtotime($datePointBas)), ENT_QUOTES, 'UTF-8'); ?>
      </span>
      <span class="d-block text-muted">Solde min. estimé sur la période</span>
      <span
        class="d-block <?= ($soldeProjeteFinMois < 0) ? 'text-danger fw-bold' : 'text-success fw-bold' ?>">
        Fin de mois estimée :
        <?= htmlspecialchars(number_format($soldeProjeteFinMois, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?>
        €
      </span>
    </div>
  </div>
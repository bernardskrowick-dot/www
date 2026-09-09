<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 10. SOLDE RÉEL DU JOUR 
 */
?>
<div class="col-12 col-md-4">
  <div class="form-label glass-card-nav p-3 card-kpi h-100">
    <h5 class="form-label fs-5">
      <span class="emoji fs-4">💳</span> Solde réel du jour

      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Indique le montant disponible réellement constaté aujourd'hui après prise en compte des
          opérations enregistrées.
          <br><br>
          Calcul : Solde initial + revenus encaissés - dépenses enregistrées jusqu'à aujourd'hui.
        </span>
      </span>

    </h5>
    <span class="badge-neon badge-neon-<?= $soldeReel >= 0 ? 'success' : 'danger'; ?> fs-5">
      <?= htmlspecialchars(number_format($soldeReel, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €
    </span>
    <div class="text-muted mt-2 small">
      <?php if ($soldeReel >= 0): ?>
        <strong class="d-block text-success">✅ Solde positif</strong>
      <?php else: ?>
        <strong class="d-block text-danger">🔴 Solde négatif</strong>
      <?php endif; ?>
      <span class="d-block text-muted">Opérations jusqu'à aujourd'hui</span>
    </div>
  </div>
</div>
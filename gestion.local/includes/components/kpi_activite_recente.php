<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 1. ACTIVITÉ RÉCENTE 
 */
?>
<div class="col-lg-6">
  <div class="form-label glass-card-nav p-4 h-100">
    <h5 class="text-white mb-4">
      <span class="emoji fs-4">📌</span> Activité récente

      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Affiche les prochaines échéances et versements prévus afin de suivre les flux à venir.
          <br><br>
          Calcul : liste des flux futurs triés par date croissante.
        </span>
      </span>

    </h5>
    <div class="dashboard-list">
      <?php if (empty($prochainesEcheances)): ?>
        <p class="text-muted text-center">
          Aucune échéance ou versement à venir.
        </p>
      <?php else: ?>
        <?php foreach ($prochainesEcheances as $e): ?>
          <?php
          $isRessource = (isset($e['type_flux']) && $e['type_flux'] === 'ressource');
          $dateEvent = $e['date_event'] ?? $e['date_echeance'] ?? '';
          ?>
          <div class="dashboard-item">
            <div>
              <span class="emoji fs-4"><?= $isRessource ? '💰' : '🛒' ?></span>
              <?= htmlspecialchars($e['libelle'] ?? $e['nom_marchand'] ?? '') ?>
            </div>
            <div class="text-end">
              <strong class="<?= $isRessource ? 'text-success' : 'text-danger' ?>">
                <?= $isRessource ? '+' : '-' ?> <?= htmlspecialchars(number_format($e['montant'], 2, ',', ' ')) ?>
                €
              </strong>
              <br>
              <small class="text-muted">
                <?= htmlspecialchars(date('d/m/Y', strtotime($dateEvent))) ?>
              </small>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
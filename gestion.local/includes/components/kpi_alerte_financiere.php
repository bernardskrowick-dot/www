<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 2. ALERTES FINANCIÈRES 
 */
?>
<div class="col-lg-6">
  <div class="form-label glass-card-nav p-4 h-100">
    <h5 class="text-white mb-4">
      <span class="emoji fs-4">🚨</span> Alertes

      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Signale les échéances nécessitant une attention particulière (retards ou
          paiements/ressources à venir), tous flux confondus.
          <br><br>
          Calcul : comparaison de la date de l'événement avec la date du jour (retard vs à venir)
          et distinction ressource/achat.
        </span>
      </span>

    </h5>
    <div>
      <?php if (!empty($alertesFinancieres)): ?>
        <?php foreach ($alertesFinancieres as $alerte): ?>
          <?php
          // Adaptation conforme à la structure unifiée des flux (date_event & type_flux)
          $dateEvent = $alerte['date_event'] ?? $alerte['date_echeance'] ?? '';
          $isEnRetard = ($dateEvent < ($dateJour ?? date('Y-m-d')));
          $classAlerte = $isEnRetard ? 'alert-danger' : 'alert-warning';
          $isRessource = (isset($alerte['type_flux']) && $alerte['type_flux'] === 'ressource');
          ?>
          <div class="alert <?= $classAlerte ?> bg-opacity-10 border-0 mb-2 p-2">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <?php if ($isEnRetard): ?>
                  🔴 <strong>Retard :</strong>
                <?php else: ?>
                  🟠 <strong>À venir :</strong>
                <?php endif; ?>

                <?= htmlspecialchars($alerte['libelle'] ?? $alerte['nom_marchand'] ?? '') ?>
                <br>
                <small class="text-info">
                  Échéance : <?= htmlspecialchars(date('d/m/Y', strtotime($dateEvent))) ?>
                </small>
              </div>
              <div class="text-end">
                <strong class="<?= $isRessource ? 'text-success' : 'text-danger' ?>">
                  <?= $isRessource ? '+' : '-' ?> <?= htmlspecialchars(number_format($alerte['montant'], 2, ',', ' ')) ?>
                  €
                </strong>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="dashboard-empty-state text-center py-3">
          <span class="emoji fs-4">🔔</span>
          <p class="text-muted mb-0">
            Aucune alerte détectée.
          </p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
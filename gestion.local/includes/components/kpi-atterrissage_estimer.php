<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * KPI 3 : Atterrissage Estimé au 31 Décembre
 */
?>
<div class="col-12 col-md-4">
  <div
    class="form-label glass-card-nav card-kpi <?= $atterrissageFinAnnee >= 0 ? 'border-primary' : 'border-danger' ?> p-3 h-100">

    <small class="fw-bold">
      <span class="<?= $atterrissageFinAnnee >= 0 ? 'text-primary' : 'text-danger' ?>">
        <span class="emoji fs-4">🎯</span> ATTERRISSAGE AU 31/12/<?= $anneeEnCours ?>

        <span class="info-tooltip ms-1 text-info fs-4">
          ⓘ
          <span class="info-tooltip-text">
            Estimation du solde disponible prévu à la fin de l'année en tenant compte du solde
            actuel et des flux futurs connus.
            <br><br>
            Calcul : Solde réel actuel + revenus futurs - dépenses futures.
          </span>
        </span>

      </span>
    </small>
    <small class="text-muted d-block">
      Différence revenus - dépenses + Solde du jour : </small>
    <div class="fs-3 text-white fw-bold mt-2">
      <?= htmlspecialchars(number_format($atterrissageFinAnnee, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?>
      €
    </div>

    <small class="fw-bold mt-1 d-block">
      <span class="<?= $variationNetteAnnee >= 0 ? 'text-success' : 'text-danger' ?>">
        <?= $variationNetteAnnee >= 0 ? 'Excédent prévisionnel' : 'Déficit prévisionnel budgétaire' ?>
      </span>
    </small>

    <small class="text-muted d-block">
      Différence revenus - dépenses :
      <span class="<?= $variationNetteAnnee >= 0 ? 'text-success' : 'text-danger' ?>">
        <?= $variationNetteAnnee >= 0 ? '+' : '' ?><?= htmlspecialchars(number_format($variationNetteAnnee, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?>
        €
      </span>
    </small>
  </div>
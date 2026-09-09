<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 4. TOTAL DÉPENSES 
 */
?> 
<div class="col-12 col-md-4">
  <div class="form-label glass-card-nav p-3 card-kpi h-100">
    <h5 class="form-label text-danger text-uppercase mb-2">
      <span class="emoji fs-4">🔥</span> Plus grosse dépense

      <span class="info-tooltip ms-1 text-info fs-4">
        ⓘ
        <span class="info-tooltip-text">
          Affiche la dépense individuelle ayant le montant le plus élevé sur la période
          sélectionnée.
          <br><br>
          Calcul : recherche de l'échéance ou de l'achat présentant le montant maximum parmi les
          éléments filtrés.
        </span>
      </span>

    </h5>
    <span class="badge-neon badge-neon-danger fs-5">
      <?= htmlspecialchars(number_format($montantPlusGrosseDepense, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?>
      €
    </span>
    <div class="text-muted mt-2 small">
      <strong class="text-white d-block">
        <?= htmlspecialchars(!empty($titrePlusGrosseDepense) ? $titrePlusGrosseDepense : $nomPlusGrosseDepense, ENT_QUOTES, 'UTF-8') ?>
      </strong>
      <?php if (!empty($titrePlusGrosseDepense) && !empty($nomPlusGrosseDepense)): ?>
        <span
          class="d-block text-muted"><?= htmlspecialchars($nomPlusGrosseDepense, ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
      <?php if (!empty($niveauDepense)): ?>
        <span class="d-block">Niveau :
          <?= htmlspecialchars($niveauDepense, ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
      <?php if (!empty($datePlusGrosseDepense)): ?>
        <span class="d-block text-muted">Prévue le :
          <?= htmlspecialchars(date('d/m/Y', strtotime($datePlusGrosseDepense)), ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </div>
  </div>
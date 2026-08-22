<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * 3. CARTE MENU TABLEAU DE BORD DES ACHATS 
 */
?>
<div class="col-lg-4 col-md-4">

  <div class="quick-card h-100 d-block">

    <div class="form-label quick-card-body">

      <div class="quick-card-icon">
        🛒
      </div>

      <h3 class="quick-card-title">
        Tableau Achats

        <span class="info-tooltip ms-1 text-info">
          ⓘ
          <span class="info-tooltip-text">
            Accès au tableau de gestion des achats et de leurs échéances associées.
            <br><br>
            Contenu : achats actifs, paiements, échéances restantes et suivi des dépenses.
          </span>
        </span>

      </h3>

      <p class="quick-card-text">
        Gérer les achats, échéances et paiements.
      </p>

      <a href="<?= htmlspecialchars(BASE_URL) ?>router.php?p=dashboard_achats.php&marchand=&statut_filter=a_venir&categorie="
        class="text-decoration-none">

        <div class="quick-card-footer">
          Accéder →
        </div>

      </a>

    </div>

  </div>

</div>
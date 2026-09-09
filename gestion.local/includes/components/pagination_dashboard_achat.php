<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * Pagination
 */
?>
<?php
// Pagination //
if ($totalPages > 1):
?>
  <nav class="d-flex flex-column align-items-center my-5">
    <ul class="pagination pagination-minimal">

      <li class="page-item <?= ($pageCourante <= 1) ? 'disabled' : '' ?>">
        <a class="page-link"
          href="<?= BASE_URL ?>router.php?p=dashboard_achats.php&marchand=<?= urlencode($filtreMarchand) ?>&statut_filter=<?= $filtreStatut ?>&categorie=<?= $filtreCategorie ?>&page=<?= $pageCourante - 1 ?>">
          <span>&lsaquo;</span>
        </a>
      </li>

      <?php
      // Affichage des numéros de page
      for ($i = 1; $i <= $totalPages; $i++):
        if ($i == 1 || $i == $totalPages || ($i >= $pageCourante - 1 && $i <= $pageCourante + 1)):
      ?>
          <li class="page-item <?= ($i == $pageCourante ? 'active' : '') ?>">
            <a class="page-link"
              href="<?= BASE_URL ?>router.php?p=dashboard_achats.php&marchand=<?= urlencode($filtreMarchand) ?>&statut_filter=<?= $filtreStatut ?>&categorie=<?= $filtreCategorie ?>&page=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php elseif ($i == $pageCourante - 2 || $i == $pageCourante + 2): ?>
          <li class="page-item disabled"><span class="page-link border-0" style="background:none !important;">...</span>
          </li>
      <?php endif;
      endfor; ?>

      <li class="page-item <?= ($pageCourante >= $totalPages) ? 'disabled' : '' ?>">
        <a class="page-link"
          href="<?= BASE_URL ?>router.php?p=dashboard_achats.php&marchand=<?= urlencode($filtreMarchand) ?>&statut_filter=<?= $filtreStatut ?>&categorie=<?= $filtreCategorie ?>&page=<?= $pageCourante + 1 ?>">
          <span>&rsaquo;</span>
        </a>
      </li>
    </ul>

    <div class="text-muted mt-2" style="font-size: 0.7rem; letter-spacing: 2px;">
      AFFICHAGE DES ACHATS <?= (($pageCourante - 1) * $parPage) + 1 ?> À
      <?= min($pageCourante * $parPage, $totalAchatsFiltres) ?> SUR <?= $totalAchatsFiltres ?>
    </div>
  </nav>
<?php endif; ?>
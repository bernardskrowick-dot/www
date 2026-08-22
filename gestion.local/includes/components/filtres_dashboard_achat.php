<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * Section Filtres Dashboard µAchats 
 */
?>
<form method="GET" action="<?= BASE_URL ?>router.php" class="row g-2 align-items-center w-100 m-0">
  <input type="hidden" name="p" value="dashboard_achats.php">

  <?php
  /** ==========================================================================
   *  Année (Dynamique basé sur l'existant)
   *  ========================================================================== */
  ?>
  <div class="col-md">
    <label class="form-label small fw-bold text-white-50"><span class="emoji fs-4">📅</span> ANNÉE</label>
    <select name="annee" class="form-select bg-dark text-white border-secondary w-100">
      <option value="">Toutes les années</option>
      <?php
      $anneesStmt = $pdo->prepare('SELECT DISTINCT YEAR(date_echeance) AS annee FROM echeances INNER JOIN achats ON echeances.achat_id = achats.id WHERE achats.user_id = :user_id ORDER BY annee DESC');
      $anneesStmt->execute(['user_id' => $user_id]);
      foreach ($anneesStmt->fetchAll() as $an):
        if (!empty($an['annee'])):
      ?>
          <option value="<?= htmlspecialchars($an['annee']) ?>" <?= ($filtreAnnee == $an['annee']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($an['annee']) ?>
          </option>
      <?php
        endif;
      endforeach;
      ?>
    </select>
  </div>

  <?php
  /** ==========================================================================
   *  Date début (Du)
   *  ========================================================================== */
  ?>
  <div class="col-md">
    <label class="form-label small fw-bold text-white-50">
      <span class="emoji fs-4">📅</span> Du
    </label>
    <input type="date" name="date_debut" class="form-control bg-dark text-white border-secondary shadow-sm"
      value="<?= htmlspecialchars($dateDebut ?? '') ?>">
  </div>

  <?php
  /** ==========================================================================
   *  Date fin (Au)
   *  ========================================================================== */
  ?>
  <div class="col-md">
    <label class="form-label small fw-bold text-white-50">
      <span class="emoji fs-4">📅</span> Au
    </label>
    <input type="date" name="date_fin" class="form-control bg-dark text-white border-secondary shadow-sm"
      value="<?= htmlspecialchars($dateFin ?? '') ?>">
  </div>

  <?php
  /** ==========================================================================
   *  Marchand
   *  ========================================================================== */
  ?>
  <div class="col-md">
    <label class="form-label small fw-bold text-white-50"><span class="emoji fs-4">🏪</span> MARCHAND</label>
    <select name="marchand" class="form-select bg-dark text-white border-secondary w-100">
      <option value="">Tous les marchands</option>
      <?php
      $marchandsStmt = $pdo->prepare('SELECT DISTINCT nom_marchand FROM achats WHERE user_id = :user_id ORDER BY nom_marchand ASC');
      $marchandsStmt->execute(['user_id' => $user_id]);
      foreach ($marchandsStmt->fetchAll() as $m):
      ?>
        <option value="<?= htmlspecialchars($m['nom_marchand']) ?>" <?= ($filtreMarchand == $m['nom_marchand']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($m['nom_marchand']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php
  /** ==========================================================================
   *  Statut
   *  ========================================================================== */
  ?>
  <div class="col-md">
    <label class="form-label small fw-bold text-white-50"><span class="emoji fs-4">📌</span> STATUTS </label>
    <select name="statut_filter" class="form-select bg-dark text-white border-secondary w-100">
      <option value="" <?= empty($filtreStatut) ? 'selected' : '' ?>>Tous les statuts</option>
      <option value="a_venir" <?= ($filtreStatut == 'a_venir') ? 'selected' : '' ?>>📅 À venir</option>
      <option value="solde" <?= ($filtreStatut == 'solde') ? 'selected' : '' ?>>✅ Soldé</option>
      <option value="en_retard" <?= ($filtreStatut == 'en_retard') ? 'selected' : '' ?>>⚠️ En retard</option>
    </select>
  </div>

  <?php
  /** ==========================================================================
   *  Catégorie
   *  ========================================================================== */
  ?>
  <div class="col-md">
    <label class="form-label small fw-bold text-white-50"><span class="emoji fs-4">📁</span> CATÉGORIE</label>
    <select name="categorie" class="form-select bg-dark text-white border-secondary w-100">
      <option value="">Toutes les catégories</option>
      <?php
      $catStmt = $pdo->prepare('
                        SELECT id, nom, parent_id
                        FROM categories
                        WHERE user_id = ?
                        ORDER BY 
                            COALESCE(parent_id, id),
                            parent_id IS NOT NULL,
                            nom ASC
                    ');
      $catStmt->execute([$user_id]);
      foreach ($catStmt->fetchAll() as $c):
        $nomCategorie = !empty($c['parent_id'])
          ? '└── 🏷️ ' . $c['nom']
          : '📁 ' . $c['nom'];
      ?>
        <option value="<?= $c['id'] ?>" <?= ($filtreCategorie == $c['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($nomCategorie) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php
  /** ==========================================================================
   *  Boutons
   *  ========================================================================== */
  ?>
  <div class="row mt-3 m-0">
    <div class="col d-flex gap-2 p-0">
      <button type="submit" class="btn-create-dash shadow-sm flex-fill justify-content-center">
        <span class="emoji fs-4">🔍</span>
        Filtrer
      </button>
      <a href="router.php?p=dashboard_achats.php"
        class="btn btn-secondary shadow-sm flex-fill d-flex align-items-center justify-content-center text-decoration-none">
        <span class="emoji fs-4">🔄</span>
        Effacer les filtres
      </a>
    </div>
  </div>
</form>
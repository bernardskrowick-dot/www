<?php

/**
 * Composant KPI : Revenus cumulés
 * Variables attendues : $totalRevenusRecu, $totalRevenusPrevu
 * Revenus à percevoir 
 */
?>
<div class="col-12 align-items-center justify-content-center">
  <div class="card p-3 bg-dark text-white border-0 align-items-center justify-content-center">
    <form method="GET" action="<?= BASE_URL ?>router.php" class="row g-2 align-items-center w-100 m-0">

      <input type="hidden" name="p" value="dashboard_revenus.php">
      <?php
      /** ==========================================================================
       *  Filtre par année
       *  ========================================================================== */
      ?>
      <div class="col-md-2">
        <label class="text-white small mb-1">
          <span class="emoji fs-4">📅</span> Année</label>
        <select name="annee_filter" class="form-select bg-dark text-white border-secondary shadow-sm">
          <option value="">Toutes</option>
          <?php foreach ($anneesDisponibles as $annee): ?>
            <?php if ($annee): ?>
              <option value="<?= htmlspecialchars($annee) ?>" <?= ($anneeFilter == $annee) ? 'selected' : '' ?>>
                <?= htmlspecialchars($annee) ?>
              </option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>
      <?php
      /** ==========================================================================
       *  Organisme
       *  ========================================================================== */
      ?>
      <div class="col-md">
        <label class="form-label text-white-50 mb-1 small">
          <span class="emoji fs-4">🏢</span> Organisme
        </label>
        <select name="organisme" class="form-select bg-dark text-white border-secondary w-100">
          <option value="">Tous les organismes</option>
          <?php
          $organismesStmt = $pdo->prepare('
                            SELECT DISTINCT organisme
                            FROM ressources
                            WHERE user_id = :user_id
                            ORDER BY organisme ASC
                        ');
          $organismesStmt->execute(['user_id' => $user_id]);

          foreach ($organismesStmt->fetchAll(PDO::FETCH_ASSOC) as $o):
          ?>
            <option value="<?= htmlspecialchars($o['organisme']) ?>"
              <?= (($filtreOrganisme ?? '') === $o['organisme']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($o['organisme']) ?>
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
        <label class="form-label text-white-50 mb-1 small">
          <span class="emoji fs-4">📌</span> Statut
        </label>
        <select name="statut_filter" class="form-select bg-dark text-white border-secondary w-100">
          <option value="">Tous les statuts</option>
          <option value="attendu" <?= ($filtreStatut === 'attendu') ? 'selected' : '' ?>>
            ⏳ Attendu
          </option>
          <option value="percu" <?= ($filtreStatut === 'percu') ? 'selected' : '' ?>>
            ✅ Perçu
          </option>
          <option value="en_retard" <?= ($filtreStatut === 'en_retard') ? 'selected' : '' ?>>
            ⚠️ En retard
          </option>
        </select>
      </div>

      <?php
      /** ==========================================================================
       *  Catégorie
       *  EXEMPLE APPLIQUÉ : Filtre de catégorie hiérarchique
       *  ========================================================================== */
      ?>
      <div class="col-md-3">
        <label class="form-label small fw-bold text-white-50"><span class="emoji fs-4">📁</span> CATÉGORIE</label>
        <select name="categorie_filter" class="form-select bg-dark text-white border-secondary w-100">
          <option value="">Toutes les catégories</option>
          <?php
          $catStmt = $pdo->prepare('
                        SELECT id, nom, parent_id
                        FROM categories_ressources
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
            <option value="<?= $c['id'] ?>" <?= ($categorieFilter == $c['id']) ? 'selected' : '' ?>>
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
      <div class="col-md-auto d-flex gap-2 align-self-end">
        <?php
        /** ==========================================================================
         *  BOUTON FILTRER
         *  ========================================================================== */
        ?>
        <button type="submit"
          class="btn-create-dash shadow-sm px-3 text-nowrap d-inline-flex align-items-center justify-content-center"
          style="height: 38px;">
          🔍 Filtrer
        </button>

        <?php
        /** ==========================================================================
         *  BOUTON Effacer
         *  ========================================================================== */
        ?>
        <a href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php"
          class="btn-modifier-neon shadow-sm px-3 text-nowrap text-decoration-none d-inline-flex align-items-center justify-content-center"
          style="height: 38px;"
          title="Réinitialiser tous les filtres">
          🔄 Effacer
        </a>
      </div>
    </form>
  </div>
</div>
<?php
/* Version: v1.0.2 - 2026-08-18 */

/**
 * Composant : Gestion des messages flash (Succès / Erreur)
 * Sécurité : Nettoyage automatique de la session après affichage.
 * CORRECTION : Ajout d'un écouteur JavaScript natif pour garantir la fermeture des alertes.
 */
?>

<div id="flash-container" class="container mt-3" style="position: relative; z-index: 9999;">
  <?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success bg-success text-white border-0 shadow-lg p-3 rounded alert-dismissible fade show position-relative flash-alert"
      role="alert">
      <div class="d-flex align-items-center">
        <span class="fs-4 me-2">✅</span>
        <div class="pe-5">
          <strong>Succès !</strong><br>
          <?= $_SESSION['flash_success']; ?>
        </div>
      </div>
      <button type="button" class="btn-flash-close btn-close-flash" aria-label="Fermer">
        ❌ FERMER
      </button>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger bg-danger text-white border-0 shadow-lg p-3 rounded alert-dismissible fade show position-relative flash-alert" role="alert">
      <div class="d-flex align-items-center">
        <span class="fs-4 me-2">⚠️</span>
        <div class="pe-5">
          <strong>Erreur !</strong><br>
          <?= $_SESSION['flash_error']; ?>
        </div>
      </div>
      <button type="button" class="btn-flash-close btn-close-flash" aria-label="Fermer">
        ❌ FERMER
      </button>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Sélectionne tous les boutons de fermeture des messages flash
    const closeButtons = document.querySelectorAll('.btn-close-flash');

    closeButtons.forEach(button => {
      button.addEventListener('click', function() {
        // Trouve le conteneur d'alerte parent et le supprime de la page
        const alertBox = this.closest('.flash-alert');
        if (alertBox) {
          alertBox.remove();
        }
      });
    });
  });
</script>
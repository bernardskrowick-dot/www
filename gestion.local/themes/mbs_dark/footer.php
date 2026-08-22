<?php
/* Version: v1.12.0 (Rev #15) - 2026-08-03 */

/**
 * =====================================================
 * FOOTER DU THÈME : MBS_DARK
 * =====================================================
 * RÔLE : Fermeture des balises de structure, affichage
 * du pied de page et chargement des scripts JS.
 * -----------------------------------------------------
 */

/*
 * -----------------------------------------------------
 * RÉCUPÉRATION DYNAMIQUE DES VERSIONS (BDD)
 * -----------------------------------------------------
 */

// 1. Récupération de la dernière version globale de l'application
$stmtFooterVer = $pdo->query('SELECT version_number FROM app_versions ORDER BY id DESC LIMIT 1');
$versionAppFooter = $stmtFooterVer ? ($stmtFooterVer->fetchColumn() ?: 'v1.0.0') : 'v1.0.0';

// 2. Identification du nom lisible de la page ($titreSurcharge ou $titrePage)
$nomPageAffichage = $titreSurcharge ?? $titrePage ?? '';

// 3. Récupération du numéro de révision exact du fichier affiché
// Si 'p' existe dans l'URL, c'est le fichier cible de la page courante
$pageDemandee = $_GET['p'] ?? basename($_SERVER['SCRIPT_NAME'] ?? '');
$nomFichierStrict = basename($pageDemandee);

// On va chercher la révision du fichier correspondant au thème actif ou au nom du fichier
$stmtFooterRev = $pdo->prepare("
    SELECT num_revision 
    FROM fichiers 
    WHERE chemin LIKE ? 
    ORDER BY CASE WHEN chemin LIKE '%mbs_dark%' THEN 1 ELSE 2 END, id DESC 
    LIMIT 1
");
$stmtFooterRev->execute(['%' . $nomFichierStrict]);
$revisionPageFooter = (int) ($stmtFooterRev->fetchColumn() ?: 0);
?>

</main>

<footer class="footer mt-auto py-4 border-top border-secondary">
    <div class="container-fluid px-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">

            <div class="small d-inline-flex align-items-center">
                <a href="<?= BASE_URL ?>index.php" class="text-decoration-none footer-brand-pill me-2">
                    <img src="<?= BASE_URL ?>images/MBS Gestions Logo.png"
                        alt="MBS Gestions"
                        class="footer-logo">
                    <?php
                    /** ==========================================================================
                     *  Affichage dynamique de la version globale de l'application
                     *  ========================================================================== */
                    ?>
                    <span class="badge-neon version-badge ms-1 text-light border border-secondary">
                        <?= 'V ' . ltrim(htmlspecialchars($versionAppFooter), 'vV ') ?>
                    </span>
                </a>
                <span class="footer-status text-muted">&copy; <?= date('Y') ?> &bull; Gestion Privée</span>
            </div>

            <div class="footer-status text-muted small mt-2 mt-md-0">
                <span class="text-success">●</span> System Ready &bull;
                <?php
                /** ==========================================================================
                 *  Affichage dynamique du titre de la page courante et de sa révision
                 *  ========================================================================== */
                if (!empty($nomPageAffichage)): ?>
                    <span class="text-info"><?= htmlspecialchars($nomPageAffichage) ?> (Rev
                        #<?= $revisionPageFooter ?>)</span> &bull;
                <?php endif; ?>
                <span id="real-time-clock" class="fw-bold"><?= date('H:i:s') ?></span>
            </div>

            <div class="mt-2 mt-md-0">
                <ul class="list-inline mb-0">
                    <li class="list-inline-item"><a href="#"
                            class="text-muted small footer-link footer-btn">Documentation</a></li>
                    <li class="list-inline-item ms-3"><a href="mailto:support@mbs.com"
                            class="text-muted small footer-link footer-btn">Support</a></li>
                </ul>
            </div>

        </div>
    </div>
</footer>

<?php
/** ==========================================================================
 *  Inclusion du module Sticky Navigation
 *  ========================================================================== */
if (file_exists(__DIR__ . '/../../includes/sticky_navigation.php')) {
    include __DIR__ . '/../../includes/sticky_navigation.php';
}

/** ==========================================================================
 *  Inclusion navbar utilisateur
 *  ========================================================================== */
if (file_exists(__DIR__ . '/../../includes/navbar.php')) {
    include __DIR__ . '/../../includes/navbar.php';
}
?>

<script src="<?= BASE_URL ?>js/jquery-3.4.1.min.js"></script>

<script src="<?= BASE_URL ?>js/chart.umd.min.js"></script>
<script src="<?= BASE_URL ?>js/chartjs-plugin-datalabels.min.js"></script>

<?php
/** ==========================================================================
 *  Bouton sticky “Retour en haut”
 *  ========================================================================== */
?>
<button id="scrollTopBtn" title="Remonter en haut"><span class="icon">🏠</span></button>

<script>
    // ===================================================
    // BOUTON RETOUR EN HAUT – FADE IN / FADE OUT + PULSE
    // ===================================================

    // Récupération du bouton
    const scrollTopBtn = document.getElementById("scrollTopBtn");

    // Fonction pour gérer l'apparition/disparition
    function handleScroll() {
        if (window.scrollY > 50) { // si l'utilisateur a scrollé plus de 50px
            scrollTopBtn.classList.add('show'); // fade in + slide
            scrollTopBtn.classList.add('pulse'); // pulse doux
        } else {
            scrollTopBtn.classList.remove('show'); // fade out + slide
            scrollTopBtn.classList.remove('pulse'); // stop pulse
        }
    }

    // Au scroll, on vérifie la position
    window.addEventListener('scroll', handleScroll);

    // Au clic : remonter en douceur
    scrollTopBtn.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });


    function updateAllClocks() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        const timeString = h + ":" + m + ":" + s;

        // Mise à jour de la Top Bar
        const topClock = document.getElementById('top-bar-clock');
        if (topClock) topClock.textContent = timeString;

        // Mise à jour du Footer
        const footerClock = document.getElementById('real-time-clock');
        if (footerClock) footerClock.textContent = timeString;
    }

    // On lance et on répète
    updateAllClocks();
    setInterval(updateAllClocks, 1000);

    const stickies = document.querySelectorAll(
        '.navbar-user-sticky, .sticky-dashboard-nav'
    );

    let lastScroll = window.pageYOffset;

    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;

        stickies.forEach(el => {
            if (currentScroll > lastScroll && currentScroll > 50) {
                el.classList.add('hidden');
            } else {
                el.classList.remove('hidden');
            }
        });

        lastScroll = currentScroll;
    });

    if (window.innerWidth <= 768) {

        const stickies = document.querySelectorAll(
            '.navbar-user-sticky, .sticky-dashboard-nav'
        );

        let lastScroll = window.scrollY;

        window.addEventListener('scroll', () => {

            const currentScroll = window.scrollY;

            stickies.forEach(el => {

                if (currentScroll > lastScroll && currentScroll > 50) {
                    // Descente : masquer
                    el.classList.add('hidden');
                } else {
                    // Remontée : afficher
                    el.classList.remove('hidden');
                }

            });

            lastScroll = currentScroll;

        });

    }
</script>

</body>

</html>
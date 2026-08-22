<?php
/* Version: v1.16.2 (Rev #24) - 2026-08-05 */

/** 
 * traiter_achat.php - Traitement SQL définitif des achats et de leurs échéances
 * RÈGLES : CSRF, Constantes de dossier, Router-Ready, Commentaires, PDO Sécurisé
 */

// Sécurité : Vérification de la session utilisateur
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// ⚠️ Si mode_submit est présent, c'est un rafraîchissement de vue, pas un enregistrement
if (isset($_POST['mode_submit'])) {
    header('Location: ' . BASE_URL . 'router.php?p=ajouter_achat.php');
    exit;
}


// Règle : Vérification du jeton CSRF
verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int) $_SESSION['user_id'];

    $titre = trim($_POST['titre'] ?? '');
    $nomMarchand = trim($_POST['nom_marchand'] ?? '');
    $montantTotal = floatval($_POST['montant_total'] ?? 0);
    $dateDepart = $_POST['date_depart'] ?? '';
    $categorieId = (int) ($_POST['categorie_id'] ?? 1);
    $recurrence = $_POST['recurrence'] ?? 'unique';
    $modeEcheances = $_POST['mode_echeances'] ?? 'auto';
    $nbEcheances = max(1, (int) ($_POST['nb_echeances'] ?? 4));

    // Récupération de la date d'échéance du débit bancaire (mode unique)
    $dateEcheanceBancaire = $_POST['date_echeance_bancaire'] ?? '';
    if (!empty($dateEcheanceBancaire) && strtotime($dateEcheanceBancaire) === false) {
        $dateEcheanceBancaire = '';
    }

    // Validation des champs obligatoires et du montant
    if (empty($titre) || $montantTotal <= 0 || empty($dateDepart)) {
        $_SESSION['flash_error'] = '⚠️ Données invalides.';
        header('Location: ' . BASE_URL . 'router.php?p=ajouter_achat.php');
        exit;
    }

    // Vérification de la catégorie de l'utilisateur (Sécurité PDO)
    $stmtCheckCat = $pdo->prepare('
        SELECT id 
        FROM categories 
        WHERE id = ? 
        AND (user_id = ? OR id = 1)
    ');
    $stmtCheckCat->execute([$categorieId, $userId]);

    if (!$stmtCheckCat->fetch()) {
        $categorieId = 1;
    }

    try {
        // Début de la transaction SQL
        $pdo->beginTransaction();

        // 1. Insertion de l'achat
        $sql = '
            INSERT INTO achats 
            (
                user_id, 
                categorie_id, 
                titre, 
                nom_marchand, 
                montant_total, 
                date_depart,
                recurrence
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $userId,
            $categorieId,
            $titre,
            $nomMarchand,
            $montantTotal,
            $dateDepart,
            $recurrence
        ]);

        $achatId = $pdo->lastInsertId();

        // =====================================
        // 2. Préparation des échéances
        // =====================================
        $echeances = [];

        // -------------------------------
        // Paiement unique
        // -------------------------------
        if ($modeEcheances === 'unique') {
            // Si une date d'échéance bancaire spécifique est renseignée, on l'utilise pour l'échéance, sinon on prend la date d'achat
            $datePaiementUnique = !empty($dateEcheanceBancaire) ? $dateEcheanceBancaire : $dateDepart;

            $echeances[] = [
                'd' => $datePaiementUnique,
                'm' => $montantTotal
            ];
            $nbEcheances = 1;
        }
        // -------------------------------
        // Paiement automatique / échelonné
        // -------------------------------
        elseif ($modeEcheances === 'auto') {
            $datesAuto = $_POST['dates_echeances'] ?? [];
            $montantEch = round($montantTotal / $nbEcheances, 2);
            $cumul = 0;

            for ($i = 0; $i < $nbEcheances; $i++) {
                // Utilise la date modifiée dans le formulaire ou génération automatique mensuelle
                $dateE = !empty($datesAuto[$i])
                    ? $datesAuto[$i]
                    : date(
                        'Y-m-d',
                        strtotime("+{$i} month", strtotime($dateDepart))
                    );

                // Gestion des arrondis sur la dernière échéance
                $mFinal = ($i === $nbEcheances - 1)
                    ? round($montantTotal - $cumul, 2)
                    : $montantEch;

                $echeances[] = [
                    'd' => $dateE,
                    'm' => $mFinal
                ];

                $cumul += $mFinal;
            }
        }

        // =====================================
        // 3. Insertion des échéances (PDO)
        // =====================================
        $stmtEch = $pdo->prepare("
            INSERT INTO echeances 
            (
                achat_id,
                date_echeance,
                montant,
                statut
            )
            VALUES (?, ?, ?, 'en_attente')
        ");

        foreach ($echeances as $e) {
            $stmtEch->execute([
                $achatId,
                $e['d'],
                $e['m']
            ]);
        }

        // Validation de la transaction
        $pdo->commit();

        /**
         * 📢 Retour d'information Dashboard
         * Formatage sécurisé du message de confirmation (XSS protégé avec htmlspecialchars)
         */
        if ($modeEcheances === 'unique') {
            $dateBancaireAffichage = !empty($dateEcheanceBancaire) ? $dateEcheanceBancaire : $dateDepart;
            $descriptionEcheance = 'un paiement unique (débit prévu le ' . htmlspecialchars($dateBancaireAffichage) . ')';
        } else {
            $descriptionEcheance = 'réparti en ' . $nbEcheances . ' échéances';
        }

        $_SESSION['flash_success'] =
            "L'achat « <strong>"
            . htmlspecialchars($titre)
            . '</strong> » ('
            . htmlspecialchars($nomMarchand)
            . ') pour un total de <strong>'
            . number_format($montantTotal, 2, ',', ' ')
            . ' €</strong> avec '
            . $descriptionEcheance
            . ' a été créé avec succès.';

        header('Location: ' . BASE_URL . 'router.php?p=dashboard_achats.php');
        exit;

    } catch (Exception $e) {
        // Annulation de la transaction en cas d'erreur
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['flash_error'] = '❌ Erreur : ' . $e->getMessage();
        header('Location: ' . BASE_URL . 'router.php?p=ajouter_achat.php');
        exit;
    }
}
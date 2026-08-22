<?php
/* Version: v1.19.1 (Rev #25) - 2026-08-06 */

/**
 * =====================================================
 * INCLUDES/FONCTIONS.PHP
 * =====================================================
 * RÔLE : Fonctions de sécurité, gestion CSRF, authentification
 * et utilitaires de calcul pour l'application.
 * -----------------------------------------------------
 */


// =====================================
// 1. SÉCURITÉ : GESTION DU LOGIN
// =====================================

/**
 * Vérifie le login et récupère les informations de l'utilisateur
 * (Utilise password_verify pour valider le hachage)
 *
 * @param PDO $pdo Instance de connexion à la base de données
 * @param string $username Identifiant saisi
 * @param string $password Mot de passe en clair saisi
 * @return array Tableau contenant le statut du succès et les données
 */
function verify_login($pdo, $username, $password)
{
    /*
     * =====================================================
     * SECTION : RÉCUPÉRATION UTILISATEUR POUR CONNEXION
     * RÔLE :
     * - Chargement des droits utilisateur
     * - Chargement du niveau Super Admin
     * - Chargement des paramètres financiers de profil
     * - Chargement des préférences d'affichage (position sticky)
     * - Chargement de l'état 2FA
     * =====================================================
     */
    $stmt = $pdo->prepare('
        SELECT 
            id,
            username,
            password_hash,
            role,
            theme,
            statut,
            is_super_admin,
            solde_initial,
            premiere_periode,
            jour_debut_periode,
            sticky_pos,
            is_2fa_enabled
        FROM users 
        WHERE username = :username
        LIMIT 1
    ');

    $stmt->execute([
        'username' => $username
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Hash factice utilisé lorsque l'utilisateur n'existe pas
    $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCk0VqvR2Vq3QJ6YwWqW';

    // Toujours effectuer password_verify()
    if ($user) {
        $passwordValid = password_verify($password, $user['password_hash']);
    } else {
        $passwordValid = password_verify($password, $dummyHash);
    }

    // Vérification de l'existence de l'utilisateur et du mot de passe haché
    if ($user && password_verify($password, $user['password_hash'])) {
        // 🔒 Compte suspendu ou inactif
        if ($user['statut'] !== 'actif') {
            return [
                'success' => false,
                'reason' => 'suspendu'
            ];
        }

        /*
         * =====================================================
         * SECTION : RETOUR DES INFORMATIONS UTILISATEUR
         * RÔLE :
         * - Transmission des données nécessaires à la session
         * - Conservation du niveau de privilège administrateur
         * - Transmission des paramètres financiers et d'affichage
         * - Transmission de l'état 2FA
         * =====================================================
         */
        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'theme' => $user['theme'],
                'statut' => $user['statut'],
                'is_super_admin' => $user['is_super_admin'],
                'solde_initial' => $user['solde_initial'],
                'premiere_periode' => $user['premiere_periode'],
                'jour_debut_periode' => $user['jour_debut_periode'],
                'sticky_pos' => $user['sticky_pos'] ?? 'right',
                'is_2fa_enabled' => $user['is_2fa_enabled']
            ]
        ];
    }

    // ❌ Identifiants incorrects
    return [
        'success' => false,
        'reason' => 'identifiants'
    ];
}

/**
 * Vérifie si l'utilisateur possède une session active
 *
 * @return bool
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

// =====================================
// 2. SÉCURITÉ : PROTECTION CSRF
// =====================================

/**
 * Génère le jeton CSRF en session s'il n'existe pas encore
 *
 * @return string Le jeton CSRF unique
 */
function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Affiche le champ caché d'entrée CSRF dans un formulaire HTML
 */
function csrf_input(): void
{
    $token = generate_csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Vérifie la validité du jeton CSRF fourni en utilisant hash_equals (anti-timing attack)
 *
 * @param string|null $token
 * @return bool
 */
function check_csrf(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals((string) $_SESSION['csrf_token'], (string) $token);
}

/**
 * 🛡️ Vérification automatique du CSRF pour les requêtes POST
 * Bloque l'exécution et redirige via le routeur en cas d'échec.
 */
function verify_csrf_token(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';

        if (!check_csrf($token)) {
            $_SESSION['flash_error'] = '⚠️ Erreur de sécurité : Session expirée ou jeton invalide.';
            header('Location: ' . BASE_URL . 'router.php?p=dashboard.php');
            exit;
        }
    }
}

// =====================================
// 3. UTILITAIRES & CALCULS
// =====================================

/**
 * Formate une chaîne YYYY-MM en nom de mois et année en français
 *
 * @param string $moisFormat Format 'YYYY-MM'
 * @return string
 */
function moisFrancais(string $moisFormat): string
{
    $moisFrancais = [
        '01' => 'Janvier',
        '02' => 'Février',
        '03' => 'Mars',
        '04' => 'Avril',
        '05' => 'Mai',
        '06' => 'Juin',
        '07' => 'Juillet',
        '08' => 'Août',
        '09' => 'Septembre',
        '10' => 'Octobre',
        '11' => 'Novembre',
        '12' => 'Décembre'
    ];

    $annee = substr($moisFormat, 0, 4);
    $numMois = substr($moisFormat, 5, 2);

    return ($moisFrancais[$numMois] ?? $numMois) . " $annee";
}

/**
 * Calcule le solde bancaire cumulé par période
 *
 * @param array $echeancesParMois Liste des dépenses prévues
 * @param array $revenusParMois Liste des revenus perçus et attendus
 * @param string $premierePeriode Mois de départ des calculs
 * @param float $soldeInitial Solde de départ du compte
 * @return array
 */
function calculerSoldesParMois(
    array $echeancesParMois,
    array $revenusParMois,
    string $premierePeriode,
    float $soldeInitial
): array {
    $soldesParMois = [];
    $soldeCourant = $soldeInitial;

    foreach ($echeancesParMois as $ligne) {
        if ($ligne['mois'] < $premierePeriode) {
            continue;
        }

        $key = trim($ligne['mois']);

        $revenu =
            ($revenusParMois[$key]['percu'] ?? 0)
            + ($revenusParMois[$key]['attendu'] ?? 0);

        $depense = (float) $ligne['total_mois'];

        $soldesParMois[$key] = [
            'solde_precedent' => $soldeCourant,
            'revenu' => $revenu,
            'depense' => $depense,
            'solde' => $soldeCourant + $revenu - $depense
        ];

        $soldeCourant = $soldesParMois[$key]['solde'];
    }

    return $soldesParMois;
}

function periodeBancaire(string $mois, int $jourDebut = 1): string
{
    $dateFinRef = new DateTime($mois . '-01');

    if ($jourDebut === 1) {
        // Cas standard : du 1er au dernier jour du mois cible
        $debut = clone $dateFinRef; // 1er du mois
        $fin = (clone $dateFinRef)->modify('last day of this month'); // Dernier jour du mois
    } else {
        // Cas personnalisé (ex: du 11 au 10 du mois suivant)
        $fin = clone $dateFinRef;
        $fin->setDate((int) $fin->format('Y'), (int) $fin->format('m'), min($jourDebut, (int) $fin->format('t')));
        $fin->modify('-1 day'); // La veille du jour de début

        $debut = clone $fin;
        $debut->modify('-1 month');
        $jourDebutSecurise = min($jourDebut, (int) $debut->format('t'));
        $debut->setDate((int) $debut->format('Y'), (int) $debut->format('m'), $jourDebutSecurise);
    }

    return $debut->format('d') . ' '
        . moisFrancais($debut->format('Y-m'))
        . ' → '
        . $fin->format('d') . ' '
        . moisFrancais($fin->format('Y-m'));
}
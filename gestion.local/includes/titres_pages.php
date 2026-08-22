<?php
/* Version: v1.21.0 (Rev #26) - 2026-08-08 */

/**
 * includes/titres_pages.php
 * RÔLE : Dictionnaire complet des titres et émojis pour la TopBar.
 */
function getPageDetails(string $slug)
{
    // Nettoyage systématique du slug (retrait du chemin et de l'extension .php)
    $cleanSlug = str_replace('.php', '', basename($slug));

    $mapping = [
        // --- RACINE & DASHBOARD ---
        'index'                 => ['emoji' => '🏠', 'text' => 'Accueil'],
        'dashboard'             => ['emoji' => '🏠', 'text' => 'Tableau de Bord de Gestion'],
        'dashboard_achats'      => ['emoji' => '🛒', 'text' => 'Tableau de Bord des Achats '],
        'dashboard_revenus'     => ['emoji' => '💰', 'text' => 'Tableau de Bord des Revenus'],
        'Tableau_mensuel'       => ['emoji' => '📅', 'text' => 'Tableau Mensuel'],
        'login'                 => ['emoji' => '🔐', 'text' => 'Connexion'],

        // --- DOSSIER : ACHATS ---
        'ajouter_achat'         => ['emoji' => '🛒 ➕', 'text' => 'Nouvel Achat'],
        'echeances_mois'        => ['emoji' => '🛒 🗓️', 'text' => 'Échéances du Mois'],
        'echeancier'            => ['emoji' => '🛒 📅', 'text' => 'Suivi de Paiement'],
        'export_csv'            => ['emoji' => '🛒 📥', 'text' => 'Export des Données'],
        'gestion_categories'    => ['emoji' => '🛒 🏷️', 'text' => 'Gestion des Catégories Achats'],
        'gestion_echeances'     => ['emoji' => '🛒 ⏳', 'text' => 'Suivi des Échéances'],
        'historique'            => ['emoji' => '🛒 📜', 'text' => 'Historique des Achats'],
        'marchands'             => ['emoji' => '🛒 🏪', 'text' => 'Mes Marchands'],
        'modifier_achat'        => ['emoji' => '🛒 ✏️', 'text' => "Modifier l'Achat"],
        'modifier_echeance'     => ['emoji' => '🛒 ⚙️', 'text' => "Ajuster l'Échéance"],
        'payer_echeance'        => ['emoji' => '🛒 💳', 'text' => 'Règlement Échéance'],
        'statistiques'          => ['emoji' => '🛒 📈', 'text' => 'Analyses & Stats Achats'],
        'supprimer_achat'       => ['emoji' => '🛒 🗑️', 'text' => 'Suppression Achat'],
        'supprimer_categorie'   => ['emoji' => '🛒 🗑️', 'text' => 'Suppression Catégorie'],
        'traiter_achat'         => ['emoji' => '🛒 💾', 'text' => "Traitement d'Achat"],

        // --- DOSSIER : ADMIN ---
        'changer_statut_utilisateur' => ['emoji' => '👤 🔄', 'text' => 'Changer Statut Utilisateur'],
        'creer_utilisateur'          => ['emoji' => '👤 ➕', 'text' => 'Créer un Utilisateur'],
        'gestion_utilisateurs'        => ['emoji' => '👥', 'text' => 'Liste des Utilisateurs'],
        'modifier_utilisateur'        => ['emoji' => '👤 ✏️', 'text' => 'Modifier Utilisateur'],
        'Effacer_mdp'                   => ['emoji' => '🔑', 'text' => 'Réinitialiser Mot de Passe'],
        'settings'                    => ['emoji' => '🛠️', 'text' => 'Paramètres Système'],
        'structure_projet'            => ['emoji' => '📁', 'text' => 'Structure des Dossiers'],
        'supprimer_utilisateur'       => ['emoji' => '👤 🗑️', 'text' => 'Bannir Utilisateur'],
        'traiter_export_structure'    => ['emoji' => '📁 📥', 'text' => 'Export Structure'],

        // --- DOSSIER : PROFIL ---
        'profil_utilisateur'    => ['emoji' => '👤', 'text' => 'Mon Profil'],

        // --- DOSSIER : INCLUDES ---
        'enable-2fa'    => ['emoji' => '🔐', 'text' => 'Configuration de la double authentification'],

        // --- DOSSIER : RESSOURCES ---
        'ajouter_ressource'     => ['emoji' => '💰 ➕', 'text' => 'Ajouter un Revenu'],
        'categories_ressources' => ['emoji' => '💰 🏷️', 'text' => 'Catégories des Ressources'],
        'historique_ressources' => ['emoji' => '💰 📈', 'text' => 'Historique Ressources'],
        'organismes' => ['emoji' => '💰 🏛️', 'text' => 'Mes Oraganismes'],
        'modifier_ressource'    => ['emoji' => '💰 ✏️', 'text' => 'Modifier la Ressource'],
        'modifier_versement'    => ['emoji' => '💰 ⚙️', 'text' => 'Ajuster le Versement'],
        'payer_versement'       => ['emoji' => '💰 💳', 'text' => 'Règlement Versement'],
        'ressource_versements'  => ['emoji' => '💰 📄', 'text' => 'Suivi des versements'],
        'statistiques_revenus'  => ['emoji' => '💰 📊', 'text' => 'Statistiques des Revenus'],
        'supprimer_ressource'   => ['emoji' => '💰 🗑️', 'text' => 'Supprimer la Ressource'],
        'traiter_ressource'     => ['emoji' => '💰 💾', 'text' => 'Traitement Revenu']
    ];

    return $mapping[$cleanSlug] ?? [
        'emoji' => '📍',
        'text'  => ucwords(str_replace(['_', '-'], ' ', $cleanSlug))
    ];
}
<?php

return [
    // Messages généraux
    'access_denied' => 'Accès refusé',
    'unauthorized' => 'Non autorisé',
    'forbidden' => 'Interdit',
    'not_found' => 'Ressource non trouvée',
    'validation_failed' => 'Les données fournies sont invalides',
    'server_error' => 'Erreur interne du serveur',

    // Gestion des utilisateurs
    'user_created_successfully' => 'Utilisateur créé avec succès',
    'user_updated_successfully' => 'Utilisateur mis à jour avec succès',
    'user_deleted_successfully' => 'Utilisateur supprimé avec succès',
    'user_status_updated_successfully' => 'Statut utilisateur mis à jour avec succès',
    'cannot_delete_own_account' => 'Vous ne pouvez pas supprimer votre propre compte',
    'cannot_change_own_status' => 'Vous ne pouvez pas changer votre propre statut',

    // Gestion des rôles
    'role_created_successfully' => 'Rôle créé avec succès',
    'role_updated_successfully' => 'Rôle mis à jour avec succès',
    'role_deleted_successfully' => 'Rôle supprimé avec succès',
    'permission_created_successfully' => 'Permission créée avec succès',
    'permission_deleted_successfully' => 'Permission supprimée avec succès',

    // Documents KYC
    'document_uploaded_successfully' => 'Document téléchargé avec succès',
    'document_reviewed_successfully' => 'Document examiné avec succès',
    'document_deleted_successfully' => 'Document supprimé avec succès',
    'user_already_has_document_type' => 'L\'utilisateur a déjà un document de ce type',
    'invalid_file_type' => 'Type de fichier invalide. Seules les images et les PDF sont autorisés',
    'file_too_large' => 'La taille du fichier dépasse la limite maximale',

    // Authentification
    'login_successful' => 'Connexion réussie',
    'logout_successful' => 'Déconnexion réussie',
    'invalid_credentials' => 'Identifiants invalides',
    'account_inactive' => 'Votre compte est inactif',
    'email_not_verified' => 'Veuillez vérifier votre adresse e-mail',

    // Messages de validation
    'required' => 'Le champ :attribute est requis',
    'email' => 'Le :attribute doit être une adresse e-mail valide',
    'unique' => 'Le :attribute a déjà été pris',
    'min' => 'Le :attribute doit contenir au moins :min caractères',
    'max' => 'Le :attribute ne peut pas dépasser :max caractères',
    'confirmed' => 'La confirmation du :attribute ne correspond pas',
    'exists' => 'Le :attribute sélectionné est invalide',
    'in' => 'Le :attribute sélectionné est invalide',
    'numeric' => 'Le :attribute doit être un nombre',
    'integer' => 'Le :attribute doit être un entier',
    'boolean' => 'Le champ :attribute doit être vrai ou faux',
    'date' => 'Le :attribute n\'est pas une date valide',
    'image' => 'Le :attribute doit être une image',
    'mimes' => 'Le :attribute doit être un fichier de type : :values',
    'max_file_size' => 'Le :attribute ne peut pas dépasser :max kilo-octets',

    // Messages de réponse API
    'api_access_denied_admin' => 'Accès refusé. Rôle administrateur requis.',
    'api_access_denied_affiliate' => 'Accès refusé. Rôle affilié requis.',
    'api_access_denied_permission' => 'Accès refusé. Permission ":permission" requise.',
    'api_welcome_admin' => 'Bienvenue dans le tableau de bord administrateur',
    'api_welcome_affiliate' => 'Bienvenue dans le tableau de bord affilié',
    'api_user_management_panel' => 'Panneau de gestion des utilisateurs',
    'api_order_creation_form' => 'Formulaire de création de commande',
    'api_access_denied_no_role' => 'Accès refusé. Aucun rôle valide assigné.',
    'api_login_successful' => 'Connexion réussie',
    'api_registration_successful' => 'Inscription réussie',
    'api_invalid_credentials' => 'Les identifiants fournis sont incorrects.',

    // Profile management
    'profile_updated_successfully' => 'Profil mis à jour avec succès.',
    'profile_update_failed' => 'Échec de la mise à jour du profil.',
    'password_updated_successfully' => 'Mot de passe mis à jour avec succès.',
    'password_update_failed' => 'Échec de la mise à jour du mot de passe.',
    'current_password_incorrect' => 'Le mot de passe actuel est incorrect.',

    // Profile page
    'joined' => 'Rejoint',
    'email_verified' => 'Email Vérifié',
    'email_not_verified' => 'Email Non Vérifié',
    'contact_information' => 'Informations de Contact',
    'roles_permissions' => 'Rôles et Permissions',
    'account_overview' => 'Aperçu du Compte',
    'member_since' => 'Membre depuis',
    'last_updated' => 'Dernière mise à jour',
    'account_type' => 'Type de Compte',
    'administrator' => 'Administrateur',
    'affiliate' => 'Affilié',
    'verified' => 'Vérifié',
    'not_verified' => 'Non Vérifié',
    'unknown' => 'Inconnu',

    // Security
    'change_password' => 'Changer le Mot de Passe',
    'current_password' => 'Mot de Passe Actuel',
    'new_password' => 'Nouveau Mot de Passe',
    'confirm_password' => 'Confirmer le Mot de Passe',
    'two_factor_authentication' => 'Authentification à Deux Facteurs',
    'recent_devices' => 'Appareils Récents',

    // Account Settings
    'account_details' => 'Détails du Compte',
    'upload_photo' => 'Télécharger une Photo',
    'save_changes' => 'Enregistrer les Modifications',
    'delete_account' => 'Supprimer le Compte',

    // Activity Timeline
    'activity_timeline' => 'Chronologie d\'Activité',
    'recent_account_activity' => 'Activité récente du compte',

    // Affiliate Stats
    'total_commissions' => 'Commissions Totales',
    'pending_commissions' => 'Commissions en Attente',
    'total_orders' => 'Commandes Totales',
    'conversion_rate' => 'Taux de Conversion',
];

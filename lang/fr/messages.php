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

    // Gestion des Boutiques
    'boutique_created_successfully' => 'Boutique créée avec succès',
    'boutique_updated_successfully' => 'Boutique mise à jour avec succès',
    'boutique_deleted_successfully' => 'Boutique supprimée avec succès',
    'boutique_not_found' => 'Boutique non trouvée',
    'boutiques_retrieved_successfully' => 'Boutiques récupérées avec succès',
    'boutique_status_updated_successfully' => 'Statut de la boutique mis à jour avec succès',
    'cannot_delete_boutique_with_orders' => 'Impossible de supprimer une boutique avec des commandes existantes',
    'boutique_already_exists' => 'Une boutique avec ce nom ou cette URL existe déjà',
    'invalid_commission_rate' => 'Le taux de commission doit être entre 0 et 100',
    'invalid_boutique_url' => 'Veuillez fournir une URL de boutique valide',
    'boutique_logo_uploaded_successfully' => 'Logo de la boutique téléchargé avec succès',
    'boutique_logo_upload_failed' => 'Échec du téléchargement du logo de la boutique',
    'boutique_logo_deleted_successfully' => 'Logo de la boutique supprimé avec succès',
    'boutique_export_successful' => 'Boutiques exportées avec succès',
    'boutique_import_successful' => 'Boutiques importées avec succès',
    'boutique_import_failed' => 'Échec de l\'importation des boutiques: :error',
    'boutique_bulk_delete_successful' => ':count boutiques supprimées avec succès',
    'boutique_bulk_delete_failed' => 'Échec de la suppression des boutiques sélectionnées',
    'boutique_validation_name_required' => 'Le nom de la boutique est requis',
    'boutique_validation_url_required' => 'L\'URL de la boutique est requise',
    'boutique_validation_email_invalid' => 'Veuillez fournir un email de contact valide',
    'boutique_validation_commission_rate_invalid' => 'Le taux de commission doit être un pourcentage valide',

    // Categories
    'categories_retrieved_success' => 'Catégories récupérées avec succès',
    'categories_retrieve_error' => 'Échec de la récupération des catégories',
    'category_created_success' => 'Catégorie créée avec succès',
    'category_creation_error' => 'Échec de la création de la catégorie',
    'category_retrieved_success' => 'Catégorie récupérée avec succès',
    'category_not_found' => 'Catégorie introuvable',
    'category_updated_success' => 'Catégorie mise à jour avec succès',
    'category_update_error' => 'Échec de la mise à jour de la catégorie',
    'category_deleted_success' => 'Catégorie supprimée avec succès',
    'category_deletion_error' => 'Échec de la suppression de la catégorie',
    'category_activated_success' => 'Catégorie activée avec succès',
    'category_deactivated_success' => 'Catégorie désactivée avec succès',
    'category_status_toggle_error' => 'Échec du changement de statut de la catégorie',
    'category_has_products_error' => 'Impossible de supprimer une catégorie avec des produits existants',
    
    // Category fields
    'category_name' => 'Nom de la catégorie',
    'category_slug' => 'Slug de la catégorie',
    'category_image' => 'Image de la catégorie',
    'category_order' => 'Ordre d\'affichage',
    'category_status' => 'Statut de la catégorie',
    
    // Category validation messages
    'category_name_required' => 'Le nom de la catégorie est obligatoire',
    'category_name_string' => 'Le nom de la catégorie doit être une chaîne de caractères',
    'category_name_max' => 'Le nom de la catégorie ne doit pas dépasser 255 caractères',
    'category_slug_string' => 'Le slug de la catégorie doit être une chaîne de caractères',
    'category_slug_max' => 'Le slug de la catégorie ne doit pas dépasser 255 caractères',
    'category_slug_unique' => 'Le slug de la catégorie doit être unique',
    'category_image_string' => 'L\'image de la catégorie doit être une chaîne de caractères',
    'category_image_max' => 'L\'URL de l\'image de la catégorie ne doit pas dépasser 500 caractères',
    'category_image_url' => 'L\'image de la catégorie doit être une URL valide',
    'category_order_integer' => 'L\'ordre de la catégorie doit être un nombre',
    'category_order_min' => 'L\'ordre de la catégorie doit être au moins 0',
    'category_status_boolean' => 'Le statut de la catégorie doit être vrai ou faux',

    // Gestion des Produits
    'produits' => [
        'boutique' => 'Boutique',
        'categorie' => 'Catégorie',
        'titre' => 'Titre du Produit',
        'description' => 'Description',
        'prix_achat' => 'Prix d\'Achat',
        'prix_vente' => 'Prix de Vente',
        'slug' => 'Slug du Produit',
        'statut' => 'Statut',
        'quantite_min' => 'Quantité Minimale',
        'notes_admin' => 'Notes Admin',
    ],
    
    'produits_retrieved_successfully' => 'Produits récupérés avec succès',
    'produits_retrieve_error' => 'Échec de la récupération des produits',
    'produit_created_successfully' => 'Produit créé avec succès',
    'produit_creation_failed' => 'Échec de la création du produit',
    'produit_retrieved_successfully' => 'Produit récupéré avec succès',
    'produit_not_found' => 'Produit non trouvé',
    'produit_updated_successfully' => 'Produit mis à jour avec succès',
    'produit_update_failed' => 'Échec de la mise à jour du produit',
    'produit_deleted_successfully' => 'Produit supprimé avec succès',
    'produit_deletion_failed' => 'Échec de la suppression du produit',
    'produits_created_successfully' => 'Produit créé avec succès',
    'produits_updated_successfully' => 'Produit mis à jour avec succès',
    'produits_deleted_successfully' => 'Produit supprimé avec succès',
    'produits_delete_failed_constraints' => 'Impossible de supprimer le produit en raison d\'enregistrements liés existants',
    'produits_has_related_offers' => 'Le produit a des offres liées',
    'produits_has_related_stock' => 'Le produit a un stock lié',
    'produits_has_related_orders' => 'Le produit a des commandes liées',

    // Gestion des Images de Produits
    'produit_images' => [
        'url' => 'URL de l\'Image',
        'ordre' => 'Ordre d\'Affichage',
        'items' => 'Éléments d\'Image',
        'id' => 'ID de l\'Image',
    ],
    
    'produit_images_created_successfully' => 'Image de produit ajoutée avec succès',
    'produit_images_creation_failed' => 'Échec de l\'ajout de l\'image du produit',
    'produit_images_deleted_successfully' => 'Image de produit supprimée avec succès',
    'produit_images_deletion_failed' => 'Échec de la suppression de l\'image du produit',
    'produit_images_sorted_successfully' => 'Images de produit réorganisées avec succès',
    'produit_images_sort_failed' => 'Échec de la réorganisation des images du produit',
    'produit_images_not_found' => 'Image de produit non trouvée',
];

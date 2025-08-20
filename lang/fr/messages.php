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

    // Gestion des vidéos de produit
    'produit_videos' => [
        'url' => 'URL de la vidéo',
        'titre' => 'Titre de la vidéo',
        'ordre' => 'Ordre d\'affichage',
    ],
    
    'produit_videos_created' => 'Vidéo de produit ajoutée avec succès',
    'produit_videos_updated' => 'Vidéo de produit mise à jour avec succès',
    'produit_videos_deleted' => 'Vidéo de produit supprimée avec succès',
    'produit_videos_order_updated' => 'Ordre des vidéos mis à jour avec succès',
    'produit_videos_url_required' => 'L\'URL de la vidéo est requise',
    'produit_videos_url_format' => 'L\'URL de la vidéo doit être une URL valide',
    'produit_videos_url_max' => 'L\'URL de la vidéo ne peut pas dépasser 255 caractères',
    'produit_videos_titre_max' => 'Le titre de la vidéo ne peut pas dépasser 255 caractères',

    // Gestion des ruptures de stock
    'produit_ruptures' => [
        'variante' => 'Variante du produit',
        'actif' => 'Statut actif',
    ],
    
    'produit_ruptures_created' => 'Alerte de stock créée avec succès',
    'produit_ruptures_updated' => 'Alerte de stock mise à jour avec succès',
    'produit_ruptures_deleted' => 'Alerte de stock supprimée avec succès',
    'produit_ruptures_resolved' => 'Alerte de stock résolue avec succès',
    'produit_ruptures_already_exists' => 'Une alerte de stock existe déjà pour cette variante',
    'produit_ruptures_variante_required' => 'La variante du produit est requise',
    'produit_ruptures_variante_exists' => 'La variante sélectionnée n\'existe pas',
    'produit_ruptures_actif_boolean' => 'Le statut actif doit être vrai ou faux',

    // Gestion des variantes de produit
    'produit_variantes' => [
        'nom' => 'Nom de la variante',
        'valeur' => 'Valeur de la variante',
        'prix_vente_variante' => 'Prix de la variante',
        'sku_variante' => 'SKU de la variante',
        'actif' => 'Statut actif',
    ],
    
    'produit_variantes_created' => 'Variante de produit créée avec succès',
    'produit_variantes_updated' => 'Variante de produit mise à jour avec succès',
    'produit_variantes_deleted' => 'Variante de produit supprimée avec succès',
    'produit_variantes_nom_required' => 'Le nom de la variante est requis',
    'produit_variantes_valeur_required' => 'La valeur de la variante est requise',
    'produit_variantes_prix_numeric' => 'Le prix de la variante doit être un nombre',
    'produit_variantes_prix_min' => 'Le prix de la variante ne peut pas être négatif',
    'produit_variantes_sku_max' => 'Le SKU de la variante ne peut pas dépasser 100 caractères',
    'produit_variantes_unique_combination' => 'Cette combinaison de variante existe déjà',

    // Gestion des propositions de produit
    'produit_propositions' => [
        'type' => 'Type de proposition',
        'titre' => 'Titre de la proposition',
        'description' => 'Description',
        'image_url' => 'URL de l\'image',
        'statut' => 'Statut',
        'notes_admin' => 'Notes admin',
    ],
    
    'produit_propositions_created' => 'Proposition de produit créée avec succès',
    'produit_propositions_updated' => 'Proposition de produit mise à jour avec succès',
    'produit_propositions_deleted' => 'Proposition de produit supprimée avec succès',
    'produit_propositions_approved' => 'Proposition de produit approuvée avec succès',
    'produit_propositions_rejected' => 'Proposition de produit rejetée avec succès',

    // Gestion des avis de produit
    'avis_produits' => [
        'note' => 'Note',
        'commentaire' => 'Commentaire',
        'statut' => 'Statut',
        'auteur_type' => 'Type d\'auteur',
    ],
    
    'avis_produits_created' => 'Avis de produit créé avec succès',
    'avis_produits_updated' => 'Avis de produit mis à jour avec succès',
    'avis_produits_deleted' => 'Avis de produit supprimé avec succès',
    'avis_produits_approved' => 'Avis de produit approuvé avec succès',
    'avis_produits_rejected' => 'Avis de produit rejeté avec succès',
    'avis_produits_note_required' => 'La note est requise',
    'avis_produits_note_range' => 'La note doit être entre 1 et 5',
    'avis_produits_commentaire_max' => 'Le commentaire ne peut pas dépasser 1000 caractères',

    // Tickets de Support
    'ticket_created_successfully' => 'Ticket créé avec succès',
    'ticket_updated_successfully' => 'Ticket mis à jour avec succès',
    'ticket_deleted_successfully' => 'Ticket supprimé avec succès',
    'ticket_assigned_successfully' => 'Ticket assigné avec succès',
    'ticket_status_changed_successfully' => 'Statut du ticket changé avec succès',
    'ticket_creation_failed' => 'Échec de la création du ticket',
    'ticket_update_failed' => 'Échec de la mise à jour du ticket',
    'ticket_deletion_failed' => 'Échec de la suppression du ticket',
    'ticket_assignment_failed' => 'Échec de l\'assignation du ticket',
    'ticket_status_change_failed' => 'Échec du changement de statut du ticket',
    'message_sent_successfully' => 'Message envoyé avec succès',
    'message_send_failed' => 'Échec de l\'envoi du message',
    'message_deleted_successfully' => 'Message supprimé avec succès',
    'message_deletion_failed' => 'Échec de la suppression du message',
    'message_not_found' => 'Message non trouvé',
    'bulk_action_completed' => 'Action en lot terminée avec succès pour :count éléments',
    'bulk_action_failed' => 'Échec de l\'action en lot',

    // Validation des tickets
    'ticket_subject' => 'sujet du ticket',
    'ticket_category' => 'catégorie du ticket',
    'ticket_priority' => 'priorité du ticket',
    'ticket_requester' => 'demandeur du ticket',
    'ticket_assignee' => 'assigné du ticket',
    'ticket_status' => 'statut du ticket',
    'message_body' => 'corps du message',
    'message_type' => 'type de message',
    'attachments' => 'pièces jointes',

    // Messages de validation
    'validation_required' => 'Ce champ est requis',
    'validation_max_length' => 'Ce champ ne peut pas dépasser :max caractères',
    'validation_invalid_choice' => 'La valeur sélectionnée est invalide',
    'validation_invalid_format' => 'Le format est invalide',
    'validation_not_found' => 'L\'élément sélectionné n\'a pas été trouvé',
    'validation_max_files' => 'Vous ne pouvez pas télécharger plus de :max fichiers',
    'validation_file' => 'Ceci doit être un fichier',
    'validation_file_type' => 'Le type de fichier n\'est pas supporté',
    'validation_file_size' => 'La taille du fichier ne peut pas dépasser :max',

    // ===========================================
    // GESTION DES STOCKS
    // ===========================================

    // Messages généraux
    'stock_movement_created' => 'Mouvement de stock enregistré avec succès',
    'stock_movement_failed' => 'Échec de l\'enregistrement du mouvement de stock',
    'no_variants_found' => 'Aucune variante trouvée pour ce produit',
    'no_warehouse_found' => 'Aucun entrepôt trouvé pour cette boutique',
    'insufficient_stock' => 'Stock insuffisant. Disponible: :available, Demandé: :requested',
    'negative_stock_not_allowed' => 'Le stock ne peut pas être négatif',

    // Types de mouvements
    'movement_type_in' => 'Entrée',
    'movement_type_out' => 'Sortie',
    'movement_type_adjust' => 'Ajustement',

    // Raisons de mouvement
    'movement_reason_purchase' => 'Achat',
    'movement_reason_correction' => 'Correction',
    'movement_reason_return' => 'Retour',
    'movement_reason_damage' => 'Dommage',
    'movement_reason_manual' => 'Manuel',
    'movement_reason_delivery_return' => 'Retour de livraison',
    'movement_reason_cancel' => 'Annulation',

    // Champs de formulaire
    'product' => 'Produit',
    'variant' => 'Variante',
    'warehouse' => 'Entrepôt',
    'movement_type' => 'Type de mouvement',
    'quantity' => 'Quantité',
    'reason' => 'Raison',
    'note' => 'Note',
    'reference' => 'Référence',

    // Validation spécifique aux stocks
    'validation_min_value' => 'La valeur doit être au moins :min',
    'validation_integer' => 'Ce champ doit être un nombre entier',
    'validation_uuid' => 'Ce champ doit être un identifiant valide',
    'validation_exists' => 'L\'élément sélectionné n\'existe pas',

    // Client Final
    'orders' => [
        'client_final' => [
            'title' => 'Client final',
            'copy_address' => 'Copier l\'adresse',
            'name' => 'Nom complet',
            'phone' => 'Téléphone',
            'email' => 'Email',
            'address' => 'Adresse',
            'city' => 'Ville',
            'postal_code' => 'Code postal',
            'country' => 'Pays',
            'address_copied' => 'Adresse copiée dans le presse-papiers',
            'copy_failed' => 'Échec de la copie de l\'adresse',
        ],
        'validation' => [
            'client_name_required' => 'Le nom du client est requis',
            'client_name_min' => 'Le nom du client doit contenir au moins 2 caractères',
            'client_phone_required' => 'Le téléphone du client est requis',
            'client_phone_format' => 'Le format du téléphone est invalide',
            'client_address_required' => 'L\'adresse du client est requise',
            'client_city_required' => 'La ville du client est requise',
            'client_email_format' => 'Le format de l\'email est invalide',
        ]
    ],
];

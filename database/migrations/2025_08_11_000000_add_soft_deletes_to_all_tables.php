<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Core user and authentication tables
        $this->addSoftDeleteToTable('users');
        $this->addSoftDeleteToTable('kyc_documents');
        
        // Affiliate and profile tables
        $this->addSoftDeleteToTable('gammes_affilies');
        $this->addSoftDeleteToTable('profils_affilies');
        $this->addSoftDeleteToTable('profil_affilie_gamme_histos');
        $this->addSoftDeleteToTable('parrainage_affilies');
        
        // Business core tables
        $this->addSoftDeleteToTable('boutiques');
        $this->addSoftDeleteToTable('categories');
        $this->addSoftDeleteToTable('produits');
        $this->addSoftDeleteToTable('produit_images');
        $this->addSoftDeleteToTable('produit_videos');
        $this->addSoftDeleteToTable('produit_variantes');
        $this->addSoftDeleteToTable('produit_ruptures');
        $this->addSoftDeleteToTable('produit_propositions');
        $this->addSoftDeleteToTable('avis_produits');
        
        // Variant catalog tables
        $this->addSoftDeleteToTable('variant_attributs');
        $this->addSoftDeleteToTable('variant_valeurs');
        
        // Offer and commission tables
        $this->addSoftDeleteToTable('offres');
        $this->addSoftDeleteToTable('offre_pays');
        $this->addSoftDeleteToTable('regle_commissions');
        $this->addSoftDeleteToTable('offre_visibilites');
        $this->addSoftDeleteToTable('offre_visibilite_affilies');
        $this->addSoftDeleteToTable('offre_visibilite_gammes');
        $this->addSoftDeleteToTable('offre_assets');
        
        // Client and address tables
        $this->addSoftDeleteToTable('clients');
        $this->addSoftDeleteToTable('adresses');
        
        // Inventory and stock tables
        $this->addSoftDeleteToTable('entrepots');
        $this->addSoftDeleteToTable('stocks');
        $this->addSoftDeleteToTable('mouvement_stocks');
        $this->addSoftDeleteToTable('reservation_stocks');
        
        // Order and import tables
        $this->addSoftDeleteToTable('lots_import');
        $this->addSoftDeleteToTable('commandes');
        $this->addSoftDeleteToTable('import_commandes');
        $this->addSoftDeleteToTable('commande_articles');
        $this->addSoftDeleteToTable('conflit_commandes');
        
        // Shipping and logistics tables
        $this->addSoftDeleteToTable('transporteurs');
        $this->addSoftDeleteToTable('expeditions');
        $this->addSoftDeleteToTable('expedition_evenements');
        $this->addSoftDeleteToTable('encaissements_cod');
        $this->addSoftDeleteToTable('retours');
        $this->addSoftDeleteToTable('retour_articles');
        
        // Commission and payment tables
        $this->addSoftDeleteToTable('commission_affilies');
        $this->addSoftDeleteToTable('reglement_affilies');
        $this->addSoftDeleteToTable('reglement_lignes');
        
        // Support and communication tables
        $this->addSoftDeleteToTable('tickets');
        $this->addSoftDeleteToTable('ticket_messages');
        $this->addSoftDeleteToTable('notifications');
        
        // Marketing and assets tables
        $this->addSoftDeleteToTable('asset_marketings');
        
        // Rules and exchange tables
        $this->addSoftDeleteToTable('regles_echange_zero');
        $this->addSoftDeleteToTable('echanges_zero');
        
        // Blacklist tables
        $this->addSoftDeleteToTable('blacklist_adresses');
        $this->addSoftDeleteToTable('blacklist_telephones');
        
        // Audit table
        $this->addSoftDeleteToTable('audit_logs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove soft delete columns from all tables
        $tables = [
            'users', 'kyc_documents', 'gammes_affilies', 'profils_affilies',
            'profil_affilie_gamme_histos', 'parrainage_affilies', 'boutiques',
            'categories', 'produits', 'produit_images', 'produit_videos',
            'produit_variantes', 'produit_ruptures', 'produit_propositions',
            'avis_produits', 'variant_attributs', 'variant_valeurs', 'offres',
            'offre_pays', 'regle_commissions', 'offre_visibilites',
            'offre_visibilite_affilies', 'offre_visibilite_gammes', 'offre_assets',
            'clients', 'adresses', 'entrepots', 'stocks', 'mouvement_stocks',
            'reservation_stocks', 'lots_import', 'commandes', 'import_commandes',
            'commande_articles', 'conflit_commandes', 'transporteurs', 'expeditions',
            'expedition_evenements', 'encaissements_cod', 'retours', 'retour_articles',
            'commission_affilies', 'reglement_affilies', 'reglement_lignes',
            'tickets', 'ticket_messages', 'notifications', 'asset_marketings',
            'regles_echange_zero', 'echanges_zero', 'blacklist_adresses',
            'blacklist_telephones', 'audit_logs'
        ];

        foreach ($tables as $table) {
            $this->removeSoftDeleteFromTable($table);
        }
    }

    /**
     * Add soft delete column to a table if it doesn't exist
     */
    private function addSoftDeleteToTable(string $tableName): void
    {
        if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'deleted_at')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Remove soft delete column from a table if it exists
     */
    private function removeSoftDeleteFromTable(string $tableName): void
    {
        if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'deleted_at')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};

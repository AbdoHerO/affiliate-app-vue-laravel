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
        Schema::table('users', function (Blueprint $table) {
            // Change id to UUID
            $table->dropColumn('id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->first();

            // Drop existing columns that will be replaced
            $table->dropColumn(['name', 'password', 'email_verified_at']);

            // Add new columns according to schema
            $table->string('nom_complet')->after('id');
            $table->string('mot_de_passe_hash')->after('email');
            $table->string('telephone')->nullable()->after('mot_de_passe_hash');
            $table->text('adresse')->nullable()->after('telephone');
            $table->string('statut')->default('actif')->comment('allowed: actif,inactif,bloque')->after('adresse');
            $table->boolean('email_verifie')->default(false)->after('statut');
            $table->string('kyc_statut')->default('non_requis')->comment('allowed: non_requis,en_attente,valide,refuse')->after('email_verifie');

            // Update timestamps to use timezone
            $table->dropTimestamps();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Revert to original structure
            $table->dropColumn(['nom_complet', 'mot_de_passe_hash', 'telephone', 'adresse', 'statut', 'email_verifie', 'kyc_statut']);
            $table->dropTimestampsTz();

            $table->string('name');
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();

            // Change back to auto-increment ID
            $table->dropColumn('id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->id()->first();
        });
    }
};

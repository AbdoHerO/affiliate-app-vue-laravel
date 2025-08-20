<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            // Add client final reference (nullable for backward compatibility)
            $table->foreignUuid('client_final_id')->nullable()->after('adresse_id')->constrained('clients')->nullOnDelete();

            // Add delivery address reference (nullable for backward compatibility)
            $table->foreignUuid('adresse_livraison_id')->nullable()->after('client_final_id')->constrained('adresses')->nullOnDelete();

            // Add immutable snapshot of client final data for shipping
            $table->json('client_final_snapshot')->nullable()->after('adresse_livraison_id')->comment('Immutable snapshot of client final data for shipping');

            // Add indexes for performance
            $table->index('client_final_id');
            $table->index('adresse_livraison_id');
        });

        // Backfill existing orders with client final data
        $this->backfillClientFinalData();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['client_final_id']);
            $table->dropForeign(['adresse_livraison_id']);

            // Then drop indexes
            $table->dropIndex(['client_final_id']);
            $table->dropIndex(['adresse_livraison_id']);

            // Finally drop columns
            $table->dropColumn(['client_final_id', 'adresse_livraison_id', 'client_final_snapshot']);
        });
    }

    /**
     * Backfill existing orders with client final data
     */
    private function backfillClientFinalData(): void
    {
        // Process in chunks to avoid memory issues
        DB::table('commandes')
            ->whereNull('client_final_snapshot')
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    try {
                        // Get client and address data
                        $client = DB::table('clients')->where('id', $order->client_id)->first();
                        $address = DB::table('adresses')->where('id', $order->adresse_id)->first();
                        
                        if ($client && $address) {
                            // Look up city from shipping cities if available
                            $shippingCity = DB::table('shipping_cities')
                                ->where('provider', 'ozonexpress')
                                ->where('name', $address->ville)
                                ->where('active', true)
                                ->first();

                            // Create client final snapshot (immutable shipping data)
                            $snapshot = [
                                // Client information
                                'nom_complet' => $client->nom_complet,
                                'telephone' => $client->telephone,
                                'email' => $client->email,
                                // Address information
                                'adresse' => $address->adresse,
                                'ville' => $address->ville,
                                'ville_id' => $shippingCity ? $shippingCity->city_id : null,
                                'shipping_city_id' => $shippingCity ? $shippingCity->id : null, // Link to shipping cities table
                                'code_postal' => $address->code_postal,
                                'pays' => $address->pays ?? 'MA'
                            ];

                            // Update the order
                            DB::table('commandes')
                                ->where('id', $order->id)
                                ->update([
                                    'client_final_id' => $order->client_id,
                                    'adresse_livraison_id' => $order->adresse_id,
                                    'client_final_snapshot' => json_encode($snapshot),
                                    'updated_at' => now()
                                ]);
                        }
                    } catch (\Exception $e) {
                        // Log error but continue processing
                        Log::warning('Failed to backfill client final data for order', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });
    }
};

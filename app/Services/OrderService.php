<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Client;
use App\Models\Adresse;
use App\Models\ShippingCity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    /**
     * Create or update client final and snapshot for an order
     */
    public function attachClientFinal(Commande $order, array $clientData): array
    {
        try {
            return DB::transaction(function () use ($order, $clientData) {
                // Validate required fields
                $this->validateClientData($clientData);

                // Find or create client final
                $clientFinal = $this->findOrCreateClientFinal($clientData);

                // Find or create delivery address
                $adresseLivraison = $this->findOrCreateDeliveryAddress($clientFinal, $clientData);

                // Create client final snapshot
                $snapshot = $this->createClientSnapshot($clientFinal, $adresseLivraison);

                // Update order with client final data
                $order->update([
                    'client_final_id' => $clientFinal->id,
                    'adresse_livraison_id' => $adresseLivraison->id,
                    'client_final_snapshot' => $snapshot
                ]);

                Log::info('Client final attached to order', [
                    'order_id' => $order->id,
                    'client_final_id' => $clientFinal->id,
                    'adresse_livraison_id' => $adresseLivraison->id,
                    'snapshot_keys' => array_keys($snapshot)
                ]);

                return [
                    'success' => true,
                    'client_final' => $clientFinal,
                    'adresse_livraison' => $adresseLivraison,
                    'snapshot' => $snapshot
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to attach client final to order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'client_data' => $clientData
            ]);

            return [
                'success' => false,
                'message' => 'Failed to attach client final: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Find existing client or create new one based on phone and name
     */
    private function findOrCreateClientFinal(array $clientData): Client
    {
        // Try to find existing client by phone (primary identifier)
        $existingClient = Client::where('telephone', $clientData['telephone'])
            ->where('nom_complet', $clientData['nom_complet'])
            ->first();

        if ($existingClient) {
            // Update existing client with any new data
            $existingClient->update([
                'email' => $clientData['email'] ?? $existingClient->email,
            ]);
            
            return $existingClient;
        }

        // Create new client
        return Client::create([
            'nom_complet' => $clientData['nom_complet'],
            'telephone' => $clientData['telephone'],
            'email' => $clientData['email'] ?? null,
        ]);
    }

    /**
     * Find existing delivery address or create new one
     */
    private function findOrCreateDeliveryAddress(Client $clientFinal, array $clientData): Adresse
    {
        // Try to find existing address for this client
        $existingAddress = Adresse::where('client_id', $clientFinal->id)
            ->where('adresse', $clientData['adresse'])
            ->where('ville', $clientData['ville'] ?? '')
            ->first();

        if ($existingAddress) {
            return $existingAddress;
        }

        // Create new address
        return Adresse::create([
            'client_id' => $clientFinal->id,
            'adresse' => $clientData['adresse'],
            'ville' => $clientData['ville'] ?? '',
            'code_postal' => $clientData['code_postal'] ?? null,
            'pays' => $clientData['pays'] ?? 'MA',
            'is_default' => false, // Don't set as default automatically
        ]);
    }

    /**
     * Create immutable snapshot of client final data
     */
    private function createClientSnapshot(Client $clientFinal, Adresse $adresseLivraison): array
    {
        // Look up city from shipping cities
        $ville = $adresseLivraison->ville;
        $shippingCity = null;

        if ($ville) {
            $shippingCity = ShippingCity::where('provider', 'ozonexpress')
                ->where('name', $ville)
                ->where('active', true)
                ->first();
        }

        return [
            // Client information
            'nom_complet' => $clientFinal->nom_complet,
            'telephone' => $clientFinal->telephone,
            'email' => $clientFinal->email,
            // Address information
            'adresse' => $adresseLivraison->adresse,
            'ville' => $ville,
            'ville_id' => $shippingCity ? $shippingCity->city_id : null,
            'shipping_city_id' => $shippingCity ? $shippingCity->id : null, // Link to shipping cities table
            'code_postal' => $adresseLivraison->code_postal,
            'pays' => $adresseLivraison->pays
        ];
    }

    /**
     * Validate client data
     */
    private function validateClientData(array $clientData): void
    {
        $required = ['nom_complet', 'telephone', 'adresse', 'ville'];

        foreach ($required as $field) {
            if (empty($clientData[$field])) {
                throw new \InvalidArgumentException("Field {$field} is required");
            }
        }

        // Validate phone format (basic)
        if (!preg_match('/^[0-9+\-\s()]{10,}$/', $clientData['telephone'])) {
            throw new \InvalidArgumentException("Invalid phone number format");
        }

        // Validate name length
        if (strlen($clientData['nom_complet']) < 2) {
            throw new \InvalidArgumentException("Name must be at least 2 characters");
        }
    }

    /**
     * Get client final data from snapshot or relationships
     */
    public function getClientFinalData(Commande $order): ?array
    {
        // Prefer snapshot data (immutable)
        if ($order->client_final_snapshot) {
            return $order->client_final_snapshot;
        }

        // Fallback to clientFinal + adresseLivraison relationships
        if ($order->clientFinal && $order->adresseLivraison) {
            // Look up shipping city
            $shippingCity = ShippingCity::where('provider', 'ozonexpress')
                ->where('name', $order->adresseLivraison->ville)
                ->where('active', true)
                ->first();

            return [
                'nom_complet' => $order->clientFinal->nom_complet,
                'telephone' => $order->clientFinal->telephone,
                'email' => $order->clientFinal->email,
                'adresse' => $order->adresseLivraison->adresse,
                'ville' => $order->adresseLivraison->ville,
                'ville_id' => $shippingCity ? $shippingCity->city_id : null,
                'shipping_city_id' => $shippingCity ? $shippingCity->id : null,
                'code_postal' => $order->adresseLivraison->code_postal,
                'pays' => $order->adresseLivraison->pays ?? 'MA'
            ];
        }

        // Fallback to clientFinal + legacy address
        if ($order->clientFinal && $order->adresse) {
            $shippingCity = ShippingCity::where('provider', 'ozonexpress')
                ->where('name', $order->adresse->ville)
                ->where('active', true)
                ->first();

            return [
                'nom_complet' => $order->clientFinal->nom_complet,
                'telephone' => $order->clientFinal->telephone,
                'email' => $order->clientFinal->email,
                'adresse' => $order->adresse->adresse,
                'ville' => $order->adresse->ville,
                'ville_id' => $shippingCity ? $shippingCity->city_id : null,
                'shipping_city_id' => $shippingCity ? $shippingCity->id : null,
                'code_postal' => $order->adresse->code_postal,
                'pays' => $order->adresse->pays ?? 'MA'
            ];
        }

        // Final fallback to legacy client/address
        if ($order->client && $order->adresse) {
            $shippingCity = ShippingCity::where('provider', 'ozonexpress')
                ->where('name', $order->adresse->ville)
                ->where('active', true)
                ->first();

            return [
                'nom_complet' => $order->client->nom_complet,
                'telephone' => $order->client->telephone,
                'email' => $order->client->email,
                'adresse' => $order->adresse->adresse,
                'ville' => $order->adresse->ville,
                'ville_id' => $shippingCity ? $shippingCity->city_id : null,
                'shipping_city_id' => $shippingCity ? $shippingCity->id : null,
                'code_postal' => $order->adresse->code_postal,
                'pays' => $order->adresse->pays ?? 'MA'
            ];
        }

        return null;
    }

    /**
     * Update order with client final data
     */
    public function updateOrderClientFinal(Commande $order, array $clientData): array
    {
        return $this->attachClientFinal($order, $clientData);
    }
}

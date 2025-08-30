<?php

namespace App\Http\View\Composers;

use App\Models\Setting;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AppSettingsComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        try {
            // Get general settings for the application
            $generalSettings = Setting::getByCategory('general');
            
            // Extract the settings we need for the HTML head
            $appName = $generalSettings['app_name'] ?? 'Arif Style - Plateforme d\'Affiliation COD';
            $appDescription = $generalSettings['app_description'] ?? 'Plateforme d\'affiliation COD - Arif Style, solution complète pour la gestion des affiliés et commandes contre remboursement';
            $appKeywords = $generalSettings['app_keywords'] ?? '';
            $favicon = $generalSettings['favicon'] ?? '';
            
            // Process favicon URL if it exists
            $faviconUrl = '';
            if ($favicon && !filter_var($favicon, FILTER_VALIDATE_URL)) {
                $faviconUrl = asset('storage/settings/' . $favicon);
            } elseif ($favicon) {
                $faviconUrl = $favicon;
            }
            
            // Share with the view
            $view->with([
                'appName' => $appName,
                'appDescription' => $appDescription,
                'appKeywords' => $appKeywords,
                'faviconUrl' => $faviconUrl,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to load app settings for view: ' . $e->getMessage());
            
            // Provide fallback values
            $view->with([
                'appName' => 'Arif Style - Plateforme d\'Affiliation COD',
                'appDescription' => 'Plateforme d\'affiliation COD - Arif Style, solution complète pour la gestion des affiliés et commandes contre remboursement',
                'appKeywords' => '',
                'faviconUrl' => '',
            ]);
        }
    }
}

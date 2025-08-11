<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AddSoftDeletesToModels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'models:add-soft-deletes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add SoftDeletes trait to all models';

    /**
     * Models that should NOT have soft deletes
     */
    protected $excludedModels = [
        'User.php', // Already updated manually
        'Produit.php', // Already updated manually
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $modelsPath = app_path('Models');
        $modelFiles = File::files($modelsPath);

        $this->info('Adding SoftDeletes trait to all models...');

        foreach ($modelFiles as $file) {
            $fileName = $file->getFilename();
            
            if (in_array($fileName, $this->excludedModels)) {
                $this->info("Skipping {$fileName} (already updated)");
                continue;
            }

            $this->updateModel($file->getPathname(), $fileName);
        }

        $this->info('Completed adding SoftDeletes trait to all models!');
    }

    /**
     * Update a single model file
     */
    protected function updateModel(string $filePath, string $fileName): void
    {
        $content = File::get($filePath);
        
        // Skip if already has SoftDeletes
        if (strpos($content, 'use Illuminate\Database\Eloquent\SoftDeletes;') !== false) {
            $this->info("Skipping {$fileName} (already has SoftDeletes import)");
            return;
        }

        // Add SoftDeletes import
        $content = $this->addSoftDeletesImport($content);
        
        // Add SoftDeletes trait to use statement
        $content = $this->addSoftDeletesTrait($content);
        
        // Add deleted_at to casts
        $content = $this->addDeletedAtCast($content);

        File::put($filePath, $content);
        $this->info("Updated {$fileName}");
    }

    /**
     * Add SoftDeletes import statement
     */
    protected function addSoftDeletesImport(string $content): string
    {
        // Find the last use statement and add SoftDeletes after it
        $pattern = '/(use Illuminate\\\\Database\\\\Eloquent\\\\[^;]+;)/';
        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
        
        if (!empty($matches[0])) {
            $lastMatch = end($matches[0]);
            $insertPosition = $lastMatch[1] + strlen($lastMatch[0]);
            
            $newImport = "\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;";
            $content = substr_replace($content, $newImport, $insertPosition, 0);
        }
        
        return $content;
    }

    /**
     * Add SoftDeletes to the use statement in class
     */
    protected function addSoftDeletesTrait(string $content): string
    {
        // Find use statements in class and add SoftDeletes
        $pattern = '/(use\s+[^;]+)(;)/';
        
        if (preg_match($pattern, $content, $matches)) {
            $useStatement = $matches[1];
            
            // Only add if not already present
            if (strpos($useStatement, 'SoftDeletes') === false) {
                $newUseStatement = $useStatement . ', SoftDeletes';
                $content = str_replace($matches[0], $newUseStatement . $matches[2], $content);
            }
        }
        
        return $content;
    }

    /**
     * Add deleted_at to casts array
     */
    protected function addDeletedAtCast(string $content): string
    {
        // Find casts array and add deleted_at
        $pattern = '/(\$casts\s*=\s*\[)(.*?)(\];)/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $castsContent = $matches[2];
            
            // Only add if not already present
            if (strpos($castsContent, 'deleted_at') === false) {
                // Add deleted_at cast
                $newCast = "'deleted_at' => 'datetime',";
                
                // If casts array is not empty, add comma and newline
                if (trim($castsContent)) {
                    $castsContent = rtrim($castsContent, "\n\r\t ") . "\n        " . $newCast . "\n    ";
                } else {
                    $castsContent = "\n        " . $newCast . "\n    ";
                }
                
                $newCastsArray = $matches[1] . $castsContent . $matches[3];
                $content = str_replace($matches[0], $newCastsArray, $content);
            }
        }
        
        return $content;
    }
}

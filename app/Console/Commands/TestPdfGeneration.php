<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Console\Command;
use Barryvdh\DomPDF\Facade\Pdf;

class TestPdfGeneration extends Command
{
    protected $signature = 'test:pdf-generation {user_id}';
    protected $description = 'Test PDF generation for a user withdrawal';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return 1;
        }
        
        $this->info("📄 Testing PDF generation for: {$user->nom_complet}");
        
        $withdrawal = Withdrawal::with(['items.commission.commande', 'user'])
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->first();
        
        if (!$withdrawal) {
            $this->warn("No approved withdrawals found for this user");
            return 0;
        }
        
        $this->info("Found withdrawal: #{$withdrawal->id} - {$withdrawal->amount} MAD");
        
        try {
            $this->info("Generating PDF with DomPDF...");
            
            // Test PDF generation
            $pdf = Pdf::loadView('pdfs.withdrawal-invoice', [
                'withdrawal' => $withdrawal,
            ]);

            // Set PDF options
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
            ]);

            $pdfContent = $pdf->output();
            $this->info("✅ PDF generated successfully!");
            $this->info("PDF size: " . strlen($pdfContent) . " bytes");
            
            // Save to temp file for testing
            $tempPath = storage_path('app/temp/test-withdrawal-' . $withdrawal->id . '.pdf');
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            file_put_contents($tempPath, $pdfContent);
            $this->info("PDF saved to: {$tempPath}");
            
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ PDF generation failed!");
            $this->error("Error: " . $e->getMessage());
            $this->error("File: " . $e->getFile() . ":" . $e->getLine());
            
            if ($e->getPrevious()) {
                $this->error("Previous: " . $e->getPrevious()->getMessage());
            }
            
            return 1;
        }
    }
}

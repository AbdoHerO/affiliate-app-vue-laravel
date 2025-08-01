<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class KycDocumentController extends Controller
{
    /**
     * Display a listing of KYC documents.
     */
    public function index(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $query = KycDocument::with('utilisateur:id,nom_complet,email');

        // Apply filters
        if ($request->has('user_id') && $request->user_id) {
            $query->where('utilisateur_id', $request->user_id);
        }

        if ($request->has('type_doc') && $request->type_doc) {
            $query->where('type_doc', $request->type_doc);
        }

        if ($request->has('statut') && $request->statut) {
            $query->where('statut', $request->statut);
        }

        if ($request->has('search') && $request->search) {
            $query->whereHas('utilisateur', function($q) use ($request) {
                $q->where('nom_complet', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $documents = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $documents->items(),
            'current_page' => $documents->currentPage(),
            'last_page' => $documents->lastPage(),
            'per_page' => $documents->perPage(),
            'total' => $documents->total(),
        ]);
    }

    /**
     * Store a newly created KYC document.
     */
    public function store(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'utilisateur_id' => 'required|uuid|exists:users,id',
            'type_doc' => 'required|string|in:cni,passport,rib,contrat',
            'fichier' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
        ]);

        // Check if user already has this document type
        $existingDoc = KycDocument::where('utilisateur_id', $request->utilisateur_id)
            ->where('type_doc', $request->type_doc)
            ->first();

        if ($existingDoc) {
            return response()->json([
                'message' => 'User already has a document of this type'
            ], Response::HTTP_CONFLICT);
        }

        // Store the file
        $file = $request->file('fichier');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileName = time() . '_' . uniqid() . '.' . $extension;
        $filePath = $file->storeAs('kyc-documents', $fileName, 'public');

        $document = KycDocument::create([
            'utilisateur_id' => $request->utilisateur_id,
            'type_doc' => $request->type_doc,
            'url_fichier' => $filePath,
            'statut' => 'en_attente',
        ]);

        $document->load('utilisateur:id,nom_complet,email');

        return response()->json([
            'message' => 'KYC document uploaded successfully',
            'document' => $document
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified KYC document.
     */
    public function show(Request $request, string $id)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $document = KycDocument::with('utilisateur:id,nom_complet,email')->findOrFail($id);

        return response()->json(['document' => $document]);
    }

    /**
     * Update the specified KYC document.
     */
    public function update(Request $request, string $id)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $document = KycDocument::findOrFail($id);

        $request->validate([
            'statut' => 'required|string|in:en_attente,valide,refuse',
            'motif_refus' => 'required_if:statut,refuse|nullable|string|max:500',
        ]);

        $document->update([
            'statut' => $request->statut,
            'motif_refus' => $request->statut === 'refuse' ? $request->motif_refus : null,
        ]);

        // Update user KYC status based on documents
        $this->updateUserKycStatus($document->utilisateur_id);

        $document->load('utilisateur:id,nom_complet,email');

        return response()->json([
            'message' => 'KYC document updated successfully',
            'document' => $document
        ]);
    }

    /**
     * Remove the specified KYC document.
     */
    public function destroy(Request $request, string $id)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $document = KycDocument::findOrFail($id);

        // Delete the file from storage
        if (Storage::disk('public')->exists($document->url_fichier)) {
            Storage::disk('public')->delete($document->url_fichier);
        }

        $userId = $document->utilisateur_id;
        $document->delete();

        // Update user KYC status after deletion
        $this->updateUserKycStatus($userId);

        return response()->json([
            'message' => 'KYC document deleted successfully'
        ]);
    }

    /**
     * Download the specified KYC document.
     */
    public function download(Request $request, string $id)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $document = KycDocument::findOrFail($id);

        if (!Storage::disk('public')->exists($document->url_fichier)) {
            return response()->json(['message' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        $filePath = Storage::disk('public')->path($document->url_fichier);
        $fileName = basename($document->url_fichier);

        // Get file extension to determine MIME type
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $mimeType = match(strtolower($extension)) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream'
        };

        // Create a more descriptive filename
        $downloadName = "kyc-{$document->type_doc}-{$document->utilisateur->nom_complet}.{$extension}";
        $downloadName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $downloadName);

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $downloadName . '"'
        ]);
    }

    /**
     * View the specified KYC document in browser.
     */
    public function view(Request $request, string $id)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $document = KycDocument::findOrFail($id);

        if (!Storage::disk('public')->exists($document->url_fichier)) {
            return response()->json(['message' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        $filePath = Storage::disk('public')->path($document->url_fichier);
        $fileName = basename($document->url_fichier);

        // Get file extension to determine MIME type
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $mimeType = match(strtolower($extension)) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream'
        };

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"'
        ]);
    }

    /**
     * Update user KYC status based on their documents.
     */
    private function updateUserKycStatus(string $userId)
    {
        $user = User::findOrFail($userId);
        $documents = KycDocument::where('utilisateur_id', $userId)->get();

        if ($documents->isEmpty()) {
            $user->update(['kyc_statut' => 'non_requis']);
            return;
        }

        $hasRefused = $documents->where('statut', 'refuse')->isNotEmpty();
        $allValidated = $documents->every(fn($doc) => $doc->statut === 'valide');

        if ($hasRefused) {
            $user->update(['kyc_statut' => 'refuse']);
        } elseif ($allValidated && $documents->count() > 0) {
            $user->update(['kyc_statut' => 'valide']);
        } else {
            $user->update(['kyc_statut' => 'en_attente']);
        }
    }

    /**
     * Get KYC documents for a specific user.
     */
    public function getUserDocuments(Request $request, string $userId)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $user = User::findOrFail($userId);
        $documents = KycDocument::where('utilisateur_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'user' => $user->only(['id', 'nom_complet', 'email', 'kyc_statut']),
            'documents' => $documents
        ]);
    }
}

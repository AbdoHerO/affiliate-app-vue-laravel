<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    /**
     * Upload profile image
     */
    public function uploadProfileImage(Request $request)
    {
        // Debug logging
        Log::info('File upload request received', [
            'method' => $request->method(),
            'has_file' => $request->hasFile('profile_image'),
            'user' => $request->user() ? $request->user()->id : 'none'
        ]);

        $request->validate([
            'profile_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        try {
            $file = $request->file('profile_image');
            
            // Generate unique filename
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            
            // Store in public/storage/profile-images directory
            $path = $file->storeAs('profile-images', $filename, 'public');
            
            // Return the full URL
            $url = Storage::url($path);
            
            return response()->json([
                'success' => true,
                'message' => __('messages.file_uploaded_successfully'),
                'url' => $url,
                'path' => $path
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.file_upload_failed'),
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete uploaded file
     */
    public function deleteFile(Request $request)
    {
        $request->validate([
            'path' => 'required|string'
        ]);

        try {
            $path = $request->path;
            
            // Remove 'storage/' prefix if present
            $path = str_replace('storage/', '', $path);
            
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                
                return response()->json([
                    'success' => true,
                    'message' => __('messages.file_deleted_successfully')
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => __('messages.file_not_found')
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.file_delete_failed'),
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

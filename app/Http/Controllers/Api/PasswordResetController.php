<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    /**
     * Send password reset link to user's email
     */
    public function sendResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ], [
            'email.required' => __('messages.email_required'),
            'email.email' => __('messages.email_invalid'),
            'email.exists' => __('messages.email_not_found'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if user account is active
        $user = User::where('email', $request->email)->first();
        if ($user->statut !== 'actif') {
            $statusMessage = match($user->statut) {
                'inactif' => __('messages.account_inactive'),
                'bloque' => __('messages.account_blocked'),
                default => __('messages.account_not_active')
            };

            return response()->json([
                'success' => false,
                'message' => $statusMessage,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // Send password reset link
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => __('messages.password_reset_link_sent'),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => __('messages.password_reset_link_failed'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.server_error'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'token.required' => __('messages.token_required'),
            'email.required' => __('messages.email_required'),
            'email.email' => __('messages.email_invalid'),
            'email.exists' => __('messages.email_not_found'),
            'password.required' => __('messages.password_required'),
            'password.min' => __('messages.password_min_length'),
            'password.confirmed' => __('messages.password_confirmation_mismatch'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // Reset password
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) {
                    $user->forceFill([
                        'mot_de_passe_hash' => Hash::make($password)
                    ])->save();

                    // Delete all existing tokens for security
                    $user->tokens()->delete();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'success' => true,
                    'message' => __('messages.password_reset_successful'),
                ]);
            }

            // Handle different error cases
            $errorMessage = match($status) {
                Password::INVALID_TOKEN => __('messages.password_reset_token_invalid'),
                Password::INVALID_USER => __('messages.email_not_found'),
                default => __('messages.password_reset_failed')
            };

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.server_error'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate password reset token
     */
    public function validateToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // Check if token is valid
            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.email_not_found'),
                ], Response::HTTP_NOT_FOUND);
            }

            // Use Laravel's password broker to validate token
            $broker = Password::broker();
            $token = $broker->getRepository()->exists($user, $request->token);

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.password_reset_token_invalid'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json([
                'success' => true,
                'message' => __('messages.password_reset_token_valid'),
                'data' => [
                    'email' => $user->email,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.server_error'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

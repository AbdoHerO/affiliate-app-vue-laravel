<?php

return [
    // General messages
    'access_denied' => 'Access denied',
    'unauthorized' => 'Unauthorized',
    'forbidden' => 'Forbidden',
    'not_found' => 'Resource not found',
    'validation_failed' => 'The given data was invalid',
    'server_error' => 'Internal server error',

    // User management
    'user_created_successfully' => 'User created successfully',
    'user_updated_successfully' => 'User updated successfully',
    'user_deleted_successfully' => 'User deleted successfully',
    'user_status_updated_successfully' => 'User status updated successfully',
    'cannot_delete_own_account' => 'You cannot delete your own account',
    'cannot_change_own_status' => 'You cannot change your own status',

    // Role management
    'role_created_successfully' => 'Role created successfully',
    'role_updated_successfully' => 'Role updated successfully',
    'role_deleted_successfully' => 'Role deleted successfully',
    'permission_created_successfully' => 'Permission created successfully',
    'permission_deleted_successfully' => 'Permission deleted successfully',

    // KYC Documents
    'document_uploaded_successfully' => 'Document uploaded successfully',
    'document_reviewed_successfully' => 'Document reviewed successfully',
    'document_deleted_successfully' => 'Document deleted successfully',
    'user_already_has_document_type' => 'User already has a document of this type',
    'invalid_file_type' => 'Invalid file type. Only images and PDFs are allowed',
    'file_too_large' => 'File size exceeds the maximum limit',

    // Authentication
    'login_successful' => 'Login successful',
    'logout_successful' => 'Logout successful',
    'invalid_credentials' => 'Invalid credentials',
    'account_inactive' => 'Your account is inactive',
    'email_not_verified' => 'Please verify your email address',

    // Validation messages
    'required' => 'The :attribute field is required',
    'email' => 'The :attribute must be a valid email address',
    'unique' => 'The :attribute has already been taken',
    'min' => 'The :attribute must be at least :min characters',
    'max' => 'The :attribute may not be greater than :max characters',
    'confirmed' => 'The :attribute confirmation does not match',
    'exists' => 'The selected :attribute is invalid',
    'in' => 'The selected :attribute is invalid',
    'numeric' => 'The :attribute must be a number',
    'integer' => 'The :attribute must be an integer',
    'boolean' => 'The :attribute field must be true or false',
    'date' => 'The :attribute is not a valid date',
    'image' => 'The :attribute must be an image',
    'mimes' => 'The :attribute must be a file of type: :values',
    'max_file_size' => 'The :attribute may not be greater than :max kilobytes',

    // API Response Messages
    'api_access_denied_admin' => 'Access denied. Admin role required.',
    'api_access_denied_affiliate' => 'Access denied. Affiliate role required.',
    'api_access_denied_permission' => 'Access denied. ":permission" permission required.',
    'api_welcome_admin' => 'Welcome to Admin Dashboard',
    'api_welcome_affiliate' => 'Welcome to Affiliate Dashboard',
    'api_user_management_panel' => 'User Management Panel',
    'api_order_creation_form' => 'Order creation form',
    'api_access_denied_no_role' => 'Access denied. No valid role assigned.',
    'api_login_successful' => 'Login successful',
    'api_registration_successful' => 'Registration successful',
    'api_invalid_credentials' => 'The provided credentials are incorrect.',

    // File upload messages
    'file_uploaded_successfully' => 'File uploaded successfully.',
    'file_upload_failed' => 'File upload failed.',
    'file_deleted_successfully' => 'File deleted successfully.',
    'file_delete_failed' => 'File deletion failed.',
    'file_not_found' => 'File not found.',

    // Profile management
    'profile_updated_successfully' => 'Profile updated successfully.',
    'profile_update_failed' => 'Profile update failed.',
    'password_updated_successfully' => 'Password updated successfully.',
    'password_update_failed' => 'Password update failed.',
    'current_password_incorrect' => 'Current password is incorrect.',
];

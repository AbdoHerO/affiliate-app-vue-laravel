# Laravel Authentication with Spatie Permission - Implementation Guide

## Overview

This Laravel application implements a complete authentication system with role and permission management using:
- **Laravel Sanctum** for API authentication
- **Spatie Laravel Permission** for role and permission management
- **Vue.js frontend** integration ready

## Default Users

The system comes with pre-configured users:

| Email | Password | Role | Access |
|-------|----------|------|--------|
| `admin@cod.test` | `password` | admin | Full system access |
| `affiliate@cod.test` | `password` | affiliate | Affiliate-specific features |

## Roles and Permissions

### Roles
- **admin**: Full system access
- **affiliate**: Limited access for affiliate users

### Permissions
- **Admin Permissions**: `manage users`, `manage affiliates`, `manage products`, `manage orders`, `manage payments`, `view reports`, `manage settings`
- **Affiliate Permissions**: `create orders`, `view own orders`, `view own commissions`, `view marketing materials`, `update profile`

## API Endpoints

### Authentication Endpoints

#### Login
```bash
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@cod.test",
  "password": "password"
}
```

#### Register (Affiliate only)
```bash
POST /api/auth/register
Content-Type: application/json

{
  "name": "New Affiliate",
  "email": "newaffiliate@example.com",
  "password": "password",
  "password_confirmation": "password",
  "role": "affiliate"
}
```

#### Get User Info
```bash
GET /api/auth/user
Authorization: Bearer {token}
```

#### Logout
```bash
POST /api/auth/logout
Authorization: Bearer {token}
```

### Protected Routes

#### Role-based Routes

**Admin Only:**
```bash
GET /api/admin/dashboard
GET /api/admin/users
GET /api/admin/affiliates
GET /api/admin/reports
```

**Affiliate Only:**
```bash
GET /api/affiliate/dashboard
GET /api/affiliate/orders
POST /api/affiliate/orders
GET /api/affiliate/commissions
```

#### Permission-based Routes
```bash
GET /api/admin/users/manage          # Requires: manage users
GET /api/admin/reports/analytics     # Requires: view reports
POST /api/orders/create              # Requires: create orders
GET /api/orders/my-orders           # Requires: view own orders
```

#### Controller Examples
```bash
GET /api/dashboard                   # Smart routing based on user role
GET /api/dashboard/admin            # Admin dashboard
GET /api/dashboard/affiliate        # Affiliate dashboard
GET /api/users/manage               # User management
GET /api/orders/create-form         # Order creation form
```

## Usage in Controllers

### Check User Roles
```php
// Check if user has specific role
if ($request->user()->hasRole('admin')) {
    // Admin-specific logic
}

// Check if user has any of the roles
if ($request->user()->hasAnyRole(['admin', 'affiliate'])) {
    // Logic for admin or affiliate
}
```

### Check User Permissions
```php
// Check if user has specific permission
if ($request->user()->can('manage users')) {
    // User management logic
}

// Check multiple permissions
if ($request->user()->canAny(['create orders', 'view own orders'])) {
    // Order-related logic
}
```

### Get User Roles and Permissions
```php
$user = $request->user();

// Get all roles
$roles = $user->getRoleNames(); // Collection

// Get all permissions
$permissions = $user->getAllPermissions(); // Collection

// Get direct permissions
$directPermissions = $user->getDirectPermissions();

// Get permissions via roles
$rolePermissions = $user->getPermissionsViaRoles();
```

## Middleware Usage

### In Routes
```php
// Role-based protection
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Admin routes
});

// Permission-based protection
Route::middleware(['auth:sanctum', 'permission:manage users'])->group(function () {
    // Routes requiring specific permission
});

// Multiple roles
Route::middleware(['auth:sanctum', 'role:admin|affiliate'])->group(function () {
    // Routes for admin OR affiliate
});
```

### Available Middleware
- `role:admin` - Requires admin role
- `role:affiliate` - Requires affiliate role
- `role:admin|affiliate` - Requires admin OR affiliate role
- `permission:manage users` - Requires specific permission
- `role_or_permission:admin|manage users` - Requires role OR permission

## Frontend Integration (Vue.js)

### Login Example
```javascript
// Login function
const login = async (credentials) => {
  const response = await fetch('/api/auth/login', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(credentials),
  });
  
  const data = await response.json();
  
  if (response.ok) {
    // Store token
    localStorage.setItem('token', data.token);
    // Store user data
    localStorage.setItem('user', JSON.stringify(data.user));
  }
  
  return data;
};
```

### API Calls with Authentication
```javascript
// Authenticated API call
const makeAuthenticatedRequest = async (url, options = {}) => {
  const token = localStorage.getItem('token');
  
  return fetch(url, {
    ...options,
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      ...options.headers,
    },
  });
};
```

### Role-based UI Components
```vue
<template>
  <div>
    <!-- Admin only content -->
    <div v-if="hasRole('admin')">
      <h2>Admin Dashboard</h2>
      <!-- Admin features -->
    </div>
    
    <!-- Affiliate only content -->
    <div v-if="hasRole('affiliate')">
      <h2>Affiliate Dashboard</h2>
      <!-- Affiliate features -->
    </div>
  </div>
</template>

<script setup>
const user = JSON.parse(localStorage.getItem('user') || '{}');

const hasRole = (role) => {
  return user.roles && user.roles.includes(role);
};

const hasPermission = (permission) => {
  return user.permissions && user.permissions.includes(permission);
};
</script>
```

## Testing the System

1. **Login as Admin:**
   ```bash
   curl -X POST http://127.0.0.1:8000/api/auth/login \
     -H "Content-Type: application/json" \
     -d '{"email":"admin@cod.test","password":"password"}'
   ```

2. **Access Admin Route:**
   ```bash
   curl -X GET http://127.0.0.1:8000/api/admin/dashboard \
     -H "Authorization: Bearer {admin_token}"
   ```

3. **Login as Affiliate:**
   ```bash
   curl -X POST http://127.0.0.1:8000/api/auth/login \
     -H "Content-Type: application/json" \
     -d '{"email":"affiliate@cod.test","password":"password"}'
   ```

4. **Try Admin Route with Affiliate Token (should fail):**
   ```bash
   curl -X GET http://127.0.0.1:8000/api/admin/dashboard \
     -H "Authorization: Bearer {affiliate_token}"
   ```

## Database Structure

The system creates the following tables:
- `users` - User accounts
- `roles` - Available roles
- `permissions` - Available permissions
- `model_has_roles` - User-role assignments
- `model_has_permissions` - Direct user-permission assignments
- `role_has_permissions` - Role-permission assignments
- `personal_access_tokens` - Sanctum API tokens

## Security Features

- **Token-based Authentication**: Secure API access with Sanctum
- **Role-based Access Control**: Hierarchical permission system
- **Permission-based Access**: Granular control over features
- **Token Management**: Automatic token cleanup on login/logout
- **Middleware Protection**: Route-level security
- **Controller-level Checks**: Additional security in business logic

This authentication system provides a solid foundation for your affiliate platform with proper role separation between administrators and affiliates.

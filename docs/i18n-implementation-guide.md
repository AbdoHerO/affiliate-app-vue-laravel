# Internationalization (i18n) Implementation Guide

## Overview

This guide documents the comprehensive i18n implementation for the Laravel + Vue.js affiliate platform, covering English (en), French (fr), and Arabic (ar) locales with full RTL support.

## Architecture

### Frontend (Vue.js)
- **Framework**: Vue i18n
- **Location**: `starter-kit/resources/ts/plugins/i18n/`
- **Translation Files**: 
  - `locales/en.json` (English)
  - `locales/fr.json` (French) 
  - `locales/ar.json` (Arabic)

### Backend (Laravel)
- **Framework**: Laravel Localization
- **Location**: `starter-kit/lang/`
- **Translation Files**:
  - `en/messages.php` (English)
  - `fr/messages.php` (French)
  - `ar/messages.php` (Arabic)

## Translation Key Taxonomy

### Naming Convention
All translation keys follow a consistent hierarchical structure:

```
{domain}_{context}_{specific}
```

### Key Domains

#### 1. Navigation (`nav_`)
- `nav_dashboard` - Dashboard
- `nav_user_management` - User Management
- `nav_all_users` - All Users
- `nav_roles_permissions` - Roles & Permissions

#### 2. Page Titles (`title_`)
- `title_admin_dashboard` - Admin Dashboard
- `title_affiliate_dashboard` - Affiliate Dashboard
- `title_user_management` - User Management

#### 3. Actions (`action_`)
- `action_login` - Login
- `action_logout` - Logout
- `action_save` - Save
- `action_delete` - Delete

#### 4. Statistics (`stats_`)
- `stats_total_affiliates` - Total Affiliates
- `stats_total_orders` - Total Orders
- `stats_revenue` - Revenue

#### 5. Table Elements (`table_`)
- `table_order_id` - Order ID
- `table_product` - Product
- `table_customer` - Customer
- `table_status` - Status

#### 6. Status Labels (`status_`)
- `status_active` - Active
- `status_pending` - Pending
- `status_delivered` - Delivered

#### 7. Form Elements (`form_`, `placeholder_`)
- `form_full_name` - Full Name
- `form_email` - Email
- `placeholder_enter_email` - Enter email

#### 8. Validation (`validation_`)
- `validation_required` - This field is required
- `validation_email` - Please enter a valid email address
- `validation_min_length` - Must be at least {min} characters

#### 9. Messages (`success_`, `error_`, `confirm_`)
- `success_user_created` - User created successfully
- `error_validation_failed` - Validation failed
- `confirm_delete_user` - Are you sure you want to delete {name}?

#### 10. API Messages (`api_`)
- `api_access_denied_admin` - Access denied. Admin role required.
- `api_welcome_admin` - Welcome to Admin Dashboard

## Usage Patterns

### Frontend (Vue.js)

#### Basic Usage
```vue
<template>
  <h1>{{ $t('title_admin_dashboard') }}</h1>
  <p>{{ $t('welcome_admin', { name: user.name }) }}</p>
</template>
```

#### With Parameters
```vue
<template>
  <p>{{ $t('stats_updated_ago', { time: '1 hour' }) }}</p>
  <span>{{ $t('validation_min_length', { min: 8 }) }}</span>
</template>
```

#### In Composables
```typescript
import { useI18n } from 'vue-i18n'

export function useNavigation() {
  const { t } = useI18n()
  
  return {
    title: t('nav_dashboard'),
    items: [
      { title: t('nav_user_management'), to: 'admin-users' }
    ]
  }
}
```

### Backend (Laravel)

#### Basic Usage
```php
return response()->json([
    'message' => __('messages.api_welcome_admin'),
    'success' => true
]);
```

#### With Parameters
```php
return response()->json([
    'message' => __('messages.api_access_denied_permission', [
        'permission' => 'Manage Users'
    ])
]);
```

#### In Validation
```php
$validator = Validator::make($request->all(), [
    'email' => 'required|email'
], [
    'email.required' => __('messages.validation_required'),
    'email.email' => __('messages.validation_email')
]);
```

## Adding New Translations

### Step 1: Identify the Domain
Determine which domain your new key belongs to (nav, title, action, etc.)

### Step 2: Create the Key
Follow the naming convention: `{domain}_{context}_{specific}`

### Step 3: Add to All Locale Files
Add the key to all three translation files:

**English (`en.json`)**
```json
{
  "action_export": "Export"
}
```

**French (`fr.json`)**
```json
{
  "action_export": "Exporter"
}
```

**Arabic (`ar.json`)**
```json
{
  "action_export": "تصدير"
}
```

### Step 4: Use in Code
```vue
<VBtn>{{ $t('action_export') }}</VBtn>
```

## RTL Support

### Arabic Language Considerations
- Text direction is automatically handled by the existing RTL system
- Ensure proper spacing and punctuation in Arabic translations
- Test layout components with Arabic text for proper alignment

### CSS Classes
The application automatically applies RTL classes when Arabic is selected:
- `.flip-in-rtl` - Flips elements in RTL mode
- Text alignment is handled automatically

## Best Practices

### 1. Consistency
- Always use the established key taxonomy
- Maintain consistent terminology across all locales
- Use the same parameter names across similar keys

### 2. Parameter Interpolation
```vue
<!-- Good -->
{{ $t('welcome_user', { name: user.name }) }}

<!-- Avoid -->
{{ $t('welcome') + ' ' + user.name }}
```

### 3. Pluralization
For keys that need pluralization, use Vue i18n's built-in support:
```json
{
  "item_count": "no items | one item | {count} items"
}
```

### 4. Validation Messages
Always use translation keys in validators:
```typescript
// Good
export const requiredValidator = (value: unknown) => {
  return !!value || t('validation_required')
}

// Avoid
export const requiredValidator = (value: unknown) => {
  return !!value || 'This field is required'
}
```

### 5. Error Handling
Provide fallback values for missing translations:
```vue
{{ $t('some_key', 'Fallback text') }}
```

## Testing i18n Implementation

### 1. Language Switching
- Test switching between all three languages
- Verify all text updates correctly
- Check RTL layout for Arabic

### 2. Parameter Interpolation
- Test all parameterized translations
- Verify correct variable substitution
- Check formatting in all languages

### 3. Form Validation
- Test validation messages in all languages
- Verify error display formatting
- Check field labels and placeholders

### 4. API Responses
- Test backend API responses in different languages
- Verify error messages are localized
- Check success messages

## Maintenance

### Adding New Features
1. Identify all user-facing text
2. Create appropriate translation keys
3. Add translations for all three languages
4. Test in all locales

### Updating Existing Text
1. Update the translation in all locale files
2. Test the change in all languages
3. Verify parameter interpolation still works

### Quality Assurance
- Regular review of translation quality
- Native speaker validation for French and Arabic
- Consistency checks across similar contexts

## File Structure

```
starter-kit/
├── resources/ts/plugins/i18n/
│   ├── index.ts                 # i18n configuration
│   └── locales/
│       ├── en.json             # English translations
│       ├── fr.json             # French translations
│       └── ar.json             # Arabic translations
├── lang/
│   ├── en/messages.php         # English backend messages
│   ├── fr/messages.php         # French backend messages
│   └── ar/messages.php         # Arabic backend messages
└── docs/
    └── i18n-implementation-guide.md  # This guide
```

## Troubleshooting

### Common Issues

1. **Missing Translation Key**
   - Check console for missing key warnings
   - Verify key exists in all locale files
   - Check for typos in key names

2. **Parameter Not Interpolating**
   - Verify parameter syntax: `{paramName}`
   - Check parameter is passed correctly
   - Ensure parameter names match

3. **RTL Layout Issues**
   - Test with Arabic locale
   - Check CSS classes for RTL support
   - Verify icon and image positioning

4. **Backend Translation Not Working**
   - Check Laravel locale is set correctly
   - Verify translation key exists in messages.php
   - Ensure `__()` helper is used correctly

## Support

For questions or issues with the i18n implementation:
1. Check this guide first
2. Review the existing translation files for examples
3. Test in all three languages before deployment
4. Maintain consistency with established patterns

<?php

return [
    // الرسائل العامة
    'access_denied' => 'تم رفض الوصول',
    'unauthorized' => 'غير مخول',
    'forbidden' => 'محظور',
    'not_found' => 'المورد غير موجود',
    'validation_failed' => 'البيانات المقدمة غير صالحة',
    'server_error' => 'خطأ داخلي في الخادم',

    // إدارة المستخدمين
    'user_created_successfully' => 'تم إنشاء المستخدم بنجاح',
    'user_updated_successfully' => 'تم تحديث المستخدم بنجاح',
    'user_deleted_successfully' => 'تم حذف المستخدم بنجاح',
    'user_status_updated_successfully' => 'تم تحديث حالة المستخدم بنجاح',
    'cannot_delete_own_account' => 'لا يمكنك حذف حسابك الخاص',
    'cannot_change_own_status' => 'لا يمكنك تغيير حالتك الخاصة',

    // إدارة الأدوار
    'role_created_successfully' => 'تم إنشاء الدور بنجاح',
    'role_updated_successfully' => 'تم تحديث الدور بنجاح',
    'role_deleted_successfully' => 'تم حذف الدور بنجاح',
    'permission_created_successfully' => 'تم إنشاء الصلاحية بنجاح',
    'permission_deleted_successfully' => 'تم حذف الصلاحية بنجاح',

    // وثائق التحقق
    'document_uploaded_successfully' => 'تم رفع الوثيقة بنجاح',
    'document_reviewed_successfully' => 'تم مراجعة الوثيقة بنجاح',
    'document_deleted_successfully' => 'تم حذف الوثيقة بنجاح',
    'user_already_has_document_type' => 'المستخدم لديه بالفعل وثيقة من هذا النوع',
    'invalid_file_type' => 'نوع ملف غير صالح. يُسمح فقط بالصور وملفات PDF',
    'file_too_large' => 'حجم الملف يتجاوز الحد الأقصى',

    // المصادقة
    'login_successful' => 'تم تسجيل الدخول بنجاح',
    'logout_successful' => 'تم تسجيل الخروج بنجاح',
    'invalid_credentials' => 'بيانات اعتماد غير صالحة',
    'account_inactive' => 'حسابك غير نشط',
    'email_not_verified' => 'يرجى التحقق من عنوان بريدك الإلكتروني',

    // رسائل التحقق
    'required' => 'حقل :attribute مطلوب',
    'email' => 'يجب أن يكون :attribute عنوان بريد إلكتروني صالح',
    'unique' => 'تم أخذ :attribute بالفعل',
    'min' => 'يجب أن يحتوي :attribute على :min أحرف على الأقل',
    'max' => 'لا يمكن أن يتجاوز :attribute :max حرف',
    'confirmed' => 'تأكيد :attribute غير متطابق',
    'exists' => ':attribute المحدد غير صالح',
    'in' => ':attribute المحدد غير صالح',
    'numeric' => 'يجب أن يكون :attribute رقماً',
    'integer' => 'يجب أن يكون :attribute عدداً صحيحاً',
    'boolean' => 'يجب أن يكون حقل :attribute صحيح أو خطأ',
    'date' => ':attribute ليس تاريخاً صالحاً',
    'image' => 'يجب أن يكون :attribute صورة',
    'mimes' => 'يجب أن يكون :attribute ملف من نوع: :values',
    'max_file_size' => 'لا يمكن أن يتجاوز :attribute :max كيلوبايت',

    // رسائل استجابة API
    'api_access_denied_admin' => 'تم رفض الوصول. دور المدير مطلوب.',
    'api_access_denied_affiliate' => 'تم رفض الوصول. دور الشريك مطلوب.',
    'api_access_denied_permission' => 'تم رفض الوصول. صلاحية ":permission" مطلوبة.',
    'api_welcome_admin' => 'مرحباً بك في لوحة تحكم المدير',
    'api_welcome_affiliate' => 'مرحباً بك في لوحة تحكم الشريك',
    'api_user_management_panel' => 'لوحة إدارة المستخدمين',
    'api_order_creation_form' => 'نموذج إنشاء الطلب',
    'api_access_denied_no_role' => 'تم رفض الوصول. لم يتم تعيين دور صالح.',
    'api_login_successful' => 'تم تسجيل الدخول بنجاح',
    'api_registration_successful' => 'تم التسجيل بنجاح',
    'api_invalid_credentials' => 'بيانات الاعتماد المقدمة غير صحيحة.',
];

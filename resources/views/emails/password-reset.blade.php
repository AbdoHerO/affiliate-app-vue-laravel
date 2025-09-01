<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.password_reset_link_sent') }} - Affilio</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
            direction: {{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }};
        }
        .container {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #6366f1;
            margin-bottom: 10px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 20px;
        }
        .content {
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 30px;
        }
        .reset-button {
            display: inline-block;
            background-color: #dc2626;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            text-align: center;
            margin: 20px 0;
            transition: background-color 0.3s;
        }
        .reset-button:hover {
            background-color: #b91c1c;
        }
        .info-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
            text-align: center;
        }
        .link {
            color: #6366f1;
            text-decoration: none;
        }
        .link:hover {
            text-decoration: underline;
        }
        .security-notice {
            background-color: #fee2e2;
            border-left: 4px solid #dc2626;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🔐 Affilio</div>
            <h1 class="title">
                @if(app()->getLocale() === 'ar')
                    إعادة تعيين كلمة المرور
                @elseif(app()->getLocale() === 'fr')
                    Réinitialisation du mot de passe
                @else
                    Password Reset
                @endif
            </h1>
        </div>

        <div class="content">
            <p>
                @if(app()->getLocale() === 'ar')
                    مرحباً <strong>{{ $user->nom_complet }}</strong>،
                @elseif(app()->getLocale() === 'fr')
                    Bonjour <strong>{{ $user->nom_complet }}</strong>,
                @else
                    Hello <strong>{{ $user->nom_complet }}</strong>,
                @endif
            </p>
            
            <p>
                @if(app()->getLocale() === 'ar')
                    لقد تلقينا طلباً لإعادة تعيين كلمة المرور لحسابك على منصة Affilio. إذا كنت قد طلبت ذلك، انقر على الزر أدناه لإعادة تعيين كلمة المرور.
                @elseif(app()->getLocale() === 'fr')
                    Nous avons reçu une demande de réinitialisation du mot de passe pour votre compte Affilio. Si vous avez fait cette demande, cliquez sur le bouton ci-dessous pour réinitialiser votre mot de passe.
                @else
                    We received a request to reset the password for your Affilio account. If you made this request, click the button below to reset your password.
                @endif
            </p>
            
            <div style="text-align: center;">
                <a href="{{ $resetUrl }}" class="reset-button">
                    @if(app()->getLocale() === 'ar')
                        🔑 إعادة تعيين كلمة المرور
                    @elseif(app()->getLocale() === 'fr')
                        🔑 Réinitialiser le mot de passe
                    @else
                        🔑 Reset Password
                    @endif
                </a>
            </div>
            
            <div class="info-box">
                <strong>
                    @if(app()->getLocale() === 'ar')
                        ⏰ معلومات مهمة:
                    @elseif(app()->getLocale() === 'fr')
                        ⏰ Informations importantes :
                    @else
                        ⏰ Important Information:
                    @endif
                </strong>
                <ul>
                    <li>
                        @if(app()->getLocale() === 'ar')
                            هذا الرابط صالح لمدة <strong>60 دقيقة</strong> فقط
                        @elseif(app()->getLocale() === 'fr')
                            Ce lien expire dans <strong>60 minutes</strong>
                        @else
                            This link expires in <strong>60 minutes</strong>
                        @endif
                    </li>
                    <li>
                        @if(app()->getLocale() === 'ar')
                            يمكن استخدام هذا الرابط مرة واحدة فقط
                        @elseif(app()->getLocale() === 'fr')
                            Ce lien ne peut être utilisé qu'une seule fois
                        @else
                            This link can only be used once
                        @endif
                    </li>
                </ul>
            </div>

            <div class="security-notice">
                <strong>
                    @if(app()->getLocale() === 'ar')
                        🛡️ تنبيه أمني:
                    @elseif(app()->getLocale() === 'fr')
                        🛡️ Avertissement de sécurité :
                    @else
                        🛡️ Security Notice:
                    @endif
                </strong>
                <p>
                    @if(app()->getLocale() === 'ar')
                        إذا لم تطلب إعادة تعيين كلمة المرور، يرجى تجاهل هذا البريد الإلكتروني. حسابك آمن ولن يتم تغيير أي شيء.
                    @elseif(app()->getLocale() === 'fr')
                        Si vous n'avez pas demandé de réinitialisation de mot de passe, veuillez ignorer cet email. Votre compte est sécurisé et aucun changement ne sera effectué.
                    @else
                        If you did not request a password reset, please ignore this email. Your account is secure and no changes will be made.
                    @endif
                </p>
            </div>
            
            <p>
                @if(app()->getLocale() === 'ar')
                    إذا لم يعمل الزر، يمكنك نسخ ولصق هذا الرابط في متصفحك:
                @elseif(app()->getLocale() === 'fr')
                    Si le bouton ne fonctionne pas, vous pouvez copier et coller ce lien dans votre navigateur :
                @else
                    If the button doesn't work, you can copy and paste this link into your browser:
                @endif
            </p>
            <p style="word-break: break-all; background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace;">
                {{ $resetUrl }}
            </p>
        </div>

        <div class="footer">
            <p>
                @if(app()->getLocale() === 'ar')
                    <strong>فريق Affilio</strong><br>
                    <a href="mailto:affiliosup@zincolo.com" class="link">affiliosup@zincolo.com</a>
                @elseif(app()->getLocale() === 'fr')
                    <strong>Équipe Affilio</strong><br>
                    <a href="mailto:affiliosup@zincolo.com" class="link">affiliosup@zincolo.com</a>
                @else
                    <strong>Affilio Team</strong><br>
                    <a href="mailto:affiliosup@zincolo.com" class="link">affiliosup@zincolo.com</a>
                @endif
            </p>
        </div>
    </div>
</body>
</html>

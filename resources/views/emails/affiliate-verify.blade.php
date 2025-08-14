<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérifiez votre adresse email - Affilio</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
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
        .verify-button {
            display: inline-block;
            background-color: #6366f1;
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
        .verify-button:hover {
            background-color: #4f46e5;
        }
        .info-box {
            background-color: #f3f4f6;
            border-left: 4px solid #6366f1;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🚀 Affilio</div>
            <h1 class="title">Vérifiez votre adresse email</h1>
        </div>

        <div class="content">
            <p>Bonjour <strong>{{ $affilie->nom_complet }}</strong>,</p>
            
            <p>Merci de vous être inscrit(e) sur notre plateforme d'affiliation ! Pour finaliser votre inscription et commencer à gagner des commissions, nous devons vérifier votre adresse email.</p>
            
            <div style="text-align: center;">
                <a href="{{ $verificationUrl }}" class="verify-button">
                    ✅ Vérifier mon email
                </a>
            </div>
            
            <div class="info-box">
                <strong>📋 Informations importantes :</strong>
                <ul>
                    <li>Ce lien expire dans <strong>48 heures</strong></li>
                    <li>Après vérification, votre compte sera soumis à l'approbation de notre équipe</li>
                    <li>Vous recevrez une notification une fois votre compte approuvé</li>
                </ul>
            </div>
            
            <p>Si le bouton ne fonctionne pas, vous pouvez copier et coller ce lien dans votre navigateur :</p>
            <p style="word-break: break-all; background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace;">
                {{ $verificationUrl }}
            </p>
        </div>

        <div class="footer">
            <p>Si vous n'avez pas créé de compte sur Affilio, vous pouvez ignorer cet email.</p>
            <p>
                <strong>Équipe Affilio</strong><br>
                <a href="mailto:affiliosup@zincolo.com" class="link">affiliosup@zincolo.com</a>
            </p>
        </div>
    </div>
</body>
</html>

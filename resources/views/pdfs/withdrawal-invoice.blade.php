<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Facture de Retrait #{{ $withdrawal->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-section h3 {
            color: #007bff;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .info-grid {
            display: table;
            width: 100%;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 150px;
            padding: 5px 0;
        }
        .info-value {
            display: table-cell;
            padding: 5px 0;
        }
        .commissions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .commissions-table th,
        .commissions-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .commissions-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .commissions-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .total-section {
            margin-top: 30px;
            text-align: right;
        }
        .total-amount {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
            border: 2px solid #007bff;
            padding: 10px;
            display: inline-block;
            margin-top: 10px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-paid { background-color: #d1ecf1; color: #0c5460; }
        .status-in_payment { background-color: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <div class="header">
        <h1>FACTURE DE RETRAIT</h1>
        <p>Référence: #{{ $withdrawal->id }}</p>
        <p>Date d'émission: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="info-section">
        <h3>Informations du Retrait</h3>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Référence:</div>
                <div class="info-value">#{{ $withdrawal->id }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Statut:</div>
                <div class="info-value">
                    <span class="status-badge status-{{ $withdrawal->status }}">
                        {{ ucfirst(str_replace('_', ' ', $withdrawal->status)) }}
                    </span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Méthode:</div>
                <div class="info-value">{{ ucfirst(str_replace('_', ' ', $withdrawal->method)) }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date de création:</div>
                <div class="info-value">{{ $withdrawal->created_at->format('d/m/Y H:i') }}</div>
            </div>
            @if($withdrawal->approved_at)
            <div class="info-row">
                <div class="info-label">Date d'approbation:</div>
                <div class="info-value">{{ $withdrawal->approved_at->format('d/m/Y H:i') }}</div>
            </div>
            @endif
            @if($withdrawal->paid_at)
            <div class="info-row">
                <div class="info-label">Date de paiement:</div>
                <div class="info-value">{{ $withdrawal->paid_at->format('d/m/Y H:i') }}</div>
            </div>
            @endif
        </div>
    </div>

    <div class="info-section">
        <h3>Informations de l'Affilié</h3>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nom complet:</div>
                <div class="info-value">{{ $withdrawal->user->nom_complet }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value">{{ $withdrawal->user->email }}</div>
            </div>
            @if($withdrawal->iban_rib)
            <div class="info-row">
                <div class="info-label">RIB/IBAN:</div>
                <div class="info-value">{{ $withdrawal->iban_rib }}</div>
            </div>
            @endif
            @if($withdrawal->bank_type)
            <div class="info-row">
                <div class="info-label">Type de banque:</div>
                <div class="info-value">{{ $withdrawal->bank_type }}</div>
            </div>
            @endif
        </div>
    </div>

    @if($withdrawal->items && $withdrawal->items->count() > 0)
    <div class="info-section">
        <h3>Commissions Incluses</h3>
        <table class="commissions-table">
            <thead>
                <tr>
                    <th>Référence Commission</th>
                    <th>Commande</th>
                    <th>Type</th>
                    <th>Montant</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($withdrawal->items as $item)
                <tr>
                    <td>#{{ $item->commission->id }}</td>
                    <td>
                        @if($item->commission->commande)
                            #{{ $item->commission->commande->id }}
                        @else
                            N/A
                        @endif
                    </td>
                    <td>{{ ucfirst($item->commission->type) }}</td>
                    <td>{{ number_format($item->commission->amount, 2) }} MAD</td>
                    <td>{{ $item->commission->created_at->format('d/m/Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="total-section">
        <div class="total-amount">
            MONTANT TOTAL: {{ number_format($withdrawal->amount, 2) }} MAD
        </div>
    </div>

    @if($withdrawal->notes)
    <div class="info-section">
        <h3>Notes</h3>
        <p>{{ $withdrawal->notes }}</p>
    </div>
    @endif

    <div class="footer">
        <p>Ce document a été généré automatiquement le {{ now()->format('d/m/Y à H:i') }}</p>
        <p>Plateforme d'Affiliation - Tous droits réservés</p>
    </div>
</body>
</html>

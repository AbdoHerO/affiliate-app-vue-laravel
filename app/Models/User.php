<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nom_complet',
        'email',
        'mot_de_passe_hash',
        'telephone',
        'adresse',
        'photo_profil',
        'statut',
        'email_verifie',
        'kyc_statut',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'mot_de_passe_hash',
        'remember_token',
    ];

    /**
     * Get the password attribute for authentication
     */
    public function getAuthPassword()
    {
        return $this->mot_de_passe_hash;
    }

    /**
     * Get the name of the password attribute
     */
    public function getAuthPasswordName()
    {
        return 'mot_de_passe_hash';
    }

    /**
     * Get the password attribute (alias for mot_de_passe_hash)
     */
    public function getPasswordAttribute()
    {
        return $this->mot_de_passe_hash;
    }

    /**
     * Set the password attribute (alias for mot_de_passe_hash)
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['mot_de_passe_hash'] = $value;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verifie' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the KYC documents for the user.
     */
    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class, 'utilisateur_id');
    }

    /**
     * Get the affiliate profile for the user.
     */
    public function profilAffilie(): HasOne
    {
        return $this->hasOne(ProfilAffilie::class, 'utilisateur_id');
    }

    /**
     * Get the boutiques owned by the user.
     */
    public function boutiques(): HasMany
    {
        return $this->hasMany(Boutique::class, 'proprietaire_id');
    }

    /**
     * Get the tickets created by the user.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'auteur_id');
    }

    /**
     * Get the ticket messages by the user.
     */
    public function ticketMessages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'auteur_id');
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'utilisateur_id');
    }

    /**
     * Get the product propositions by the user.
     */
    public function produitPropositions(): HasMany
    {
        return $this->hasMany(ProduitProposition::class, 'auteur_id');
    }

    /**
     * Get the product reviews by the user.
     */
    public function avisProduits(): HasMany
    {
        return $this->hasMany(AvisProduit::class, 'auteur_id');
    }

    /**
     * Get the zero exchanges applied by the user.
     */
    public function echangesZero(): HasMany
    {
        return $this->hasMany(EchangeZero::class, 'applique_par');
    }

    /**
     * Get the audit logs for the user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'auteur_id');
    }

    /**
     * Get the users sponsored by this user.
     */
    public function filleuls(): HasMany
    {
        return $this->hasMany(ParrainageAffilie::class, 'parrain_id');
    }

    /**
     * Get the sponsor of this user.
     */
    public function parrain(): HasOne
    {
        return $this->hasOne(ParrainageAffilie::class, 'filleul_id');
    }
}

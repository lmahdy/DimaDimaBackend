<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Doctrine\DBAL\Types\Types;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * Utilisateur
 */
#[ORM\Table(name: 'utilisateur')]
#[ORM\Index(name: 'idx_role', columns: ['role'])]
#[ORM\UniqueConstraint(name: 'email', columns: ['email'])]
#[ORM\Entity]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;

    /**
     * @var string
     */
    #[ORM\Column(name: 'nom', type: 'string', length: 50, nullable: false)]
    private $nom;

    /**
     * @var string
     */
    #[ORM\Column(name: 'prenom', type: 'string', length: 50, nullable: false)]
    private $prenom;

    /**
     * @var string
     */
    #[ORM\Column(name: 'email', type: 'string', length: 100, nullable: false)]
    private $email;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'telephone', type: 'string', length: 20, nullable: true)]
    private $telephone;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'role', type: 'string', length: 0, nullable: true, options: ['default' => 'Client'])]
    private $role = 'Client';

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'adresse', type: 'text', length: 65535, nullable: true)]
    private $adresse;

    /**
     * @var string
     */
    #[ORM\Column(name: 'mot_de_passe', type: 'string', length: 255, nullable: false)]
private string $password;
    /**
     * @var string|null
     */
    #[ORM\Column(name: 'statut', type: 'string', length: 0, nullable: true, options: ['default' => 'en_attente'])]
    private $statut = 'en_attente';

    /**
     * @var bool|null
     */
    #[ORM\Column(name: 'isConfirmed', type: 'boolean', nullable: true)]
    private $isconfirmed = '0';

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'face_data', type: 'text', length: 0, nullable: true)]
    private $faceData;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'reset_token', type: 'string', length: 255, nullable: true)]
    private $resetToken;

    #[ORM\Column(name: 'signature', type: 'string', length: 255, nullable: true)]
private ?string $signature = null;

    /**
     * @var \DateTime|null
     */
    #[ORM\Column(name: 'reset_token_expiry', type: 'datetime', nullable: true)]
    private $resetTokenExpiry;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */

    /**
     * Constructor
     */
   

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }
    public function getFullName(): ?string
{
    if ($this->nom === null && $this->prenom === null) {
        return null;
    }

    return trim($this->nom . ' ' . $this->prenom);
}


    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getMotDePasse(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
{
    $this->password = $password;

    return $this;
}
public function getSignature(): ?string
{
    return $this->signature;
}

public function setSignature(?string $signature): static
{
    $this->signature = $signature;
    return $this;
}


    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function isconfirmed(): ?bool
    {
        return $this->isconfirmed;
    }

    public function setIsconfirmed(?bool $isconfirmed): static
    {
        $this->isconfirmed = $isconfirmed;

        return $this;
    }
    public function getFaceData()
    {
        return is_resource($this->faceData) ? stream_get_contents($this->faceData) : $this->faceData;
    }

    public function setFaceData($faceData): self
    {
        $this->faceData = $faceData;
        return $this;
    }
    public function getFaceDataUpdatedAt(): ?\DateTimeInterface
{
    return $this->faceDataUpdatedAt;
}

public function setFaceDataUpdatedAt(?\DateTimeInterface $faceDataUpdatedAt): self
{
    $this->faceDataUpdatedAt = $faceDataUpdatedAt;
    return $this;
}

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;

        return $this;
    }

    public function getResetTokenExpiry(): ?\DateTimeInterface
    {
        return $this->resetTokenExpiry;
    }

    public function setResetTokenExpiry(?\DateTimeInterface $resetTokenExpiry): static
    {
        $this->resetTokenExpiry = $resetTokenExpiry;

        return $this;
    }


    
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getRoles(): array
{
    $roles = ['ROLE_' . strtoupper($this->role ?? 'CLIENT')];
    $roles[] = 'ROLE_USER'; // rôle de base, toujours utile
    return array_unique($roles);
}

    public function eraseCredentials(): void
    {
        // Si tu stockes des données sensibles temporairement, efface-les ici
    }
    #[ORM\OneToOne(mappedBy: 'artisan', targetEntity: Artisan::class)]
private ?Artisan $artisan = null;

#[ORM\OneToOne(mappedBy: 'constructeur', targetEntity: Constructeur::class)]
private ?Constructeur $constructeur = null;


public function getArtisan(): ?Artisan
{
    return $this->artisan;
}

public function setArtisan(?Artisan $artisan): static
{
    $this->artisan = $artisan;
    return $this;
}

public function getConstructeur(): ?Constructeur
{
    return $this->constructeur;
}

public function setConstructeur(?Constructeur $constructeur): static
{
    $this->constructeur = $constructeur;
    return $this;
}
#[ORM\OneToOne(mappedBy: 'client', targetEntity: Client::class)]
private ?Client $client = null;

public function getClient(): ?Client
{
    return $this->client;
}

public function setClient(?Client $client): static
{
    $this->client = $client;
    return $this;
}
#[ORM\OneToOne(mappedBy: 'gestionnairestock', targetEntity: Gestionnairestock::class)]
private ?Gestionnairestock $gestionnaireStock = null;

public function getGestionnaireStock(): ?Gestionnairestock
{
    return $this->gestionnaireStock;
}

public function setGestionnaireStock(?Gestionnairestock $gestionnaireStock): static
{
    $this->gestionnaireStock = $gestionnaireStock;
    return $this;
}

// In Utilisateur entity
    public function __toString(): string
    {
        return sprintf('%s %s (%s)',
            $this->prenom,
            $this->nom,
            $this->role ?? 'No role'
        );
    }


#[ORM\Column(type: 'float', nullable: true)]
private ?float $latitude = null;

#[ORM\Column(type: 'float', nullable: true)]
private ?float $longitude = null;
public function getLatitude(): ?float { return $this->latitude; }
public function setLatitude(?float $latitude): self { $this->latitude = $latitude; return $this; }

public function getLongitude(): ?float { return $this->longitude; }
public function setLongitude(?float $longitude): self { $this->longitude = $longitude; return $this; }
public function isCompleted(): bool
{
    return $this->adresse !== null && 
           $this->telephone !== null && 
           $this->password !== null;
}


    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: UserAbonnement::class, cascade: ['persist', 'remove'])]
    private Collection $userAbonnements;

    // Constructor
    public function __construct()
    {
        $this->userAbonnements = new ArrayCollection();
    }

    // Getter and setter for userAbonnements
    public function getUserAbonnements(): Collection
    {
        return $this->userAbonnements;
    }

    public function addUserAbonnement(UserAbonnement $userAbonnement): static
    {
        if (!$this->userAbonnements->contains($userAbonnement)) {
            $this->userAbonnements[] = $userAbonnement;
            $userAbonnement->setUtilisateur($this);
        }

        return $this;
    }

    public function removeUserAbonnement(UserAbonnement $userAbonnement): static
    {
        if ($this->userAbonnements->removeElement($userAbonnement)) {
            if ($userAbonnement->getUtilisateur() === $this) {
                $userAbonnement->setUtilisateur(null);
            }
        }

        return $this;
    }


// src/Entity/Utilisateur.php

// Utilisez soit les annotations soit les attributs, mais pas les deux mélangés
// Je recommande d'utiliser les attributs (syntaxe #[...]) pour Symfony 5.4+

// Propriétés pour la réinitialisation de mot de passe
#[ORM\Column(type: 'string', length: 6, nullable: true)]
private ?string $resetPasswordCode = null;

#[ORM\Column(type: 'integer', nullable: true)]
private ?int $resetPasswordAttempts = 0;

#[ORM\Column(type: 'datetime', nullable: true)]
private ?\DateTimeInterface $resetPasswordCodeSentAt = null;

#[ORM\Column(type: 'datetime', nullable: true)]
private ?\DateTimeInterface $resetPasswordCodeExpiresAt = null;

#[ORM\Column(type: 'integer', nullable: true)]
private ?int $resetPasswordCodeResendCount = 0;

// Getters et Setters (version optimisée)
public function getResetPasswordCode(): ?string
{
    return $this->resetPasswordCode;
}

public function setResetPasswordCode(?string $resetPasswordCode): self
{
    $this->resetPasswordCode = $resetPasswordCode;
    return $this;
}

public function getResetPasswordAttempts(): int
{
    return $this->resetPasswordAttempts ?? 0;
}

public function setResetPasswordAttempts(?int $resetPasswordAttempts): self
{
    $this->resetPasswordAttempts = $resetPasswordAttempts;
    return $this;
}

public function getResetPasswordCodeSentAt(): ?\DateTimeInterface
{
    return $this->resetPasswordCodeSentAt;
}

public function setResetPasswordCodeSentAt(?\DateTimeInterface $resetPasswordCodeSentAt): self
{
    $this->resetPasswordCodeSentAt = $resetPasswordCodeSentAt;
    return $this;
}

public function getResetPasswordCodeExpiresAt(): ?\DateTimeInterface
{
    return $this->resetPasswordCodeExpiresAt;
}

public function setResetPasswordCodeExpiresAt(?\DateTimeInterface $resetPasswordCodeExpiresAt): self
{
    $this->resetPasswordCodeExpiresAt = $resetPasswordCodeExpiresAt;
    return $this;
}

public function getResetPasswordCodeResendCount(): int
{
    return $this->resetPasswordCodeResendCount ?? 0;
}

public function setResetPasswordCodeResendCount(?int $resetPasswordCodeResendCount): self
{
    $this->resetPasswordCodeResendCount = $resetPasswordCodeResendCount;
    return $this;
}


}
<?php

namespace App\Entity;

use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WebauthnCredentialRepository::class)]
#[ORM\Table(name: 'webauthn_credential')]
class WebauthnCredential
{
    /**
     * The credential ID is a base64url-encoded binary string from the authenticator.
     */
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /**
     * CBOR-encoded public key, stored as base64url string.
     */
    #[ORM\Column(type: 'text')]
    private string $publicKey;

    #[ORM\Column(type: 'integer')]
    private int $counter = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, User $user, string $publicKey)
    {
        $this->id        = $id;
        $this->user      = $user;
        $this->publicKey = $publicKey;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getPublicKey(): string { return $this->publicKey; }
    public function getCounter(): int { return $this->counter; }
    public function setCounter(int $counter): void { $this->counter = $counter; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

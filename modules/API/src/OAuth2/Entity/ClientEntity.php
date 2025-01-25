<?php
namespace Gibbon\Module\API\OAuth2\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

class ClientEntity implements ClientEntityInterface
{
    use EntityTrait, ClientTrait;

    private $name;
    private $redirectUri;
    private $grantTypes;
    private $isConfidential;

    public function __construct(
        string $identifier,
        string $name,
        array $redirectUri = [],
        array $grantTypes = ['client_credentials'],
        bool $isConfidential = true
    ) {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->redirectUri = $redirectUri;
        $this->grantTypes = $grantTypes;
        $this->isConfidential = $isConfidential;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRedirectUri(): array
    {
        return $this->redirectUri;
    }

    public function getGrantTypes(): array
    {
        return $this->grantTypes;
    }

    public function isConfidential(): bool
    {
        return $this->isConfidential;
    }
}

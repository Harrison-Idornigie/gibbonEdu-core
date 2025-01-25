<?php
namespace Gibbon\Module\API\OAuth2\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Gibbon\Domain\User\UserGateway;

class UserRepository implements UserRepositoryInterface
{
    private $userGateway;

    public function __construct(UserGateway $userGateway)
    {
        $this->userGateway = $userGateway;
    }

    public function getUserEntityByUserCredentials(
        $username,
        $password,
        $grantType,
        ClientEntityInterface $clientEntity
    ) {
        // Validate user credentials using Gibbon's authentication
        $criteria = ['username' => $username];
        $user = $this->userGateway->selectBy($criteria)->fetch();

        if (!$user) {
            return null;
        }

        // Use Gibbon's password hashing mechanism to verify password
        if (!password_verify($password, $user['password'])) {
            return null;
        }

        // Return user identifier if credentials are valid
        return $user['gibbonPersonID'];
    }
}

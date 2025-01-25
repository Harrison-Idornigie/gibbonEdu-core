<?php
namespace Gibbon\Module\API\Middleware;

use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

class OAuth2Middleware
{
    private $resourceServer;
    private $psr17Factory;
    private $serverRequestCreator;

    public function __construct(ResourceServer $resourceServer)
    {
        $this->resourceServer = $resourceServer;
        $this->psr17Factory = new Psr17Factory();
        $this->serverRequestCreator = new ServerRequestCreator(
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory
        );
    }

    public function __invoke()
    {
        try {
            $request = $this->serverRequestCreator->fromGlobals();
            $request = $this->resourceServer->validateAuthenticatedRequest($request);
            
            // Store validated attributes in request
            $GLOBALS['oauth2_user_id'] = $request->getAttribute('oauth_user_id');
            $GLOBALS['oauth2_client_id'] = $request->getAttribute('oauth_client_id');
            $GLOBALS['oauth2_scopes'] = $request->getAttribute('oauth_scopes', []);
            
            return true;
        } catch (OAuthServerException $exception) {
            http_response_code($exception->getHttpStatusCode());
            echo json_encode([
                'error' => $exception->getErrorType(),
                'message' => $exception->getMessage()
            ]);
            exit;
        } catch (\Exception $exception) {
            http_response_code(500);
            echo json_encode([
                'error' => 'internal_server_error',
                'message' => 'Internal server error'
            ]);
            exit;
        }
    }
}

<?php
namespace Gibbon\Module\API;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use League\OAuth2\Server\Exception\OAuthServerException;
use Gibbon\Module\API\OAuth2\OAuth2Provider;

// Create PSR-7 message factories
$psr17Factory = new Psr17Factory();

// Create PSR-7 server request
$creator = new ServerRequestCreator(
    $psr17Factory, // ServerRequestFactory
    $psr17Factory, // UriFactory
    $psr17Factory, // UploadedFileFactory
    $psr17Factory  // StreamFactory
);

$serverRequest = $creator->fromGlobals();

try {
    // Get OAuth2 provider from container
    $provider = $container->get(OAuth2Provider::class);
    
    // Get server based on request path
    $path = $serverRequest->getUri()->getPath();
    
    if (strpos($path, '/oauth/token') !== false) {
        // Handle token requests
        $server = $provider->getAuthorizationServer();
        $response = $server->respondToAccessTokenRequest($serverRequest, $psr17Factory->createResponse());
    } elseif (strpos($path, '/oauth/authorize') !== false) {
        // Handle authorization requests
        $server = $provider->getAuthorizationServer();
        $authRequest = $server->validateAuthorizationRequest($serverRequest);
        
        // Store the auth request in the session
        $_SESSION['oauth2_auth_request'] = serialize($authRequest);
        
        // Redirect to login/consent page
        header('Location: oauth2_authorize.php');
        exit;
    } else {
        throw new OAuthServerException('Invalid request path', 400, 'invalid_request');
    }

    // Send response
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }
    
    echo $response->getBody();
} catch (OAuthServerException $exception) {
    // OAuth2 server exception
    $response = $exception->generateHttpResponse($psr17Factory->createResponse());
    
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }
    
    echo $response->getBody();
} catch (\Exception $exception) {
    // Unknown exception
    $response = $psr17Factory->createResponse(500);
    $response->getBody()->write($exception->getMessage());
    
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }
    
    echo $response->getBody();
}

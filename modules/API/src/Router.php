<?php
namespace Gibbon\Module\API;

class Router {
    private $routes = [];
    private $basePrefix = '/api/v1';

    public function get($path, $handler) {
        $this->routes['GET'][$this->basePrefix . $path] = $handler;
        return $this;
    }

    public function post($path, $handler) {
        $this->routes['POST'][$this->basePrefix . $path] = $handler;
        return $this;
    }

    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Find matching route
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $pattern = $this->convertRouteToRegex($route);
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // Remove full match
                return $this->handleRequest($handler, $matches);
            }
        }

        // No route found
        http_response_code(404);
        return ['error' => 'Not Found'];
    }

    private function convertRouteToRegex($route) {
        return '#^' . preg_replace('/\{([a-zA-Z]+)\}/', '([^/]+)', $route) . '$#';
    }

    private function handleRequest($handler, $params) {
        if (is_callable($handler)) {
            return $handler(...$params);
        }
        
        if (is_array($handler) && count($handler) === 2) {
            list($controller, $method) = $handler;
            if (is_string($controller)) {
                $controller = new $controller();
            }
            return $controller->$method(...$params);
        }
        
        throw new \RuntimeException('Invalid route handler');
    }
}

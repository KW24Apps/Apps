<?php

namespace Core;

require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\LogHelper;

class Router
{
    /** @var array Armazena todas as rotas registradas. */
    protected $routes = [];

    /**
     * O construtor agora carrega as definições de rota.
     */
    public function __construct()
    {
        $this->routes = require __DIR__ . '/../routers/apirouters.php';
    }

    /**
     * Procura e executa a rota correspondente à URI e ao método da requisição.
     * @param string $uri A URI da requisição atual.
     * @param string $method O método HTTP da requisição atual.
     * @return mixed O resultado da execução do controller ou uma mensagem de erro em JSON.
     */
    public function dispatch($uri, $method)
    {
        if (isset($this->routes[$uri])) {
            $route = $this->routes[$uri];
            $routeMethod = $route[2] ?? 'GET'; // Default para GET se não especificado

            if (strtoupper($routeMethod) === strtoupper($method)) {
                $controllerName = $route[0];
                $methodName = $route[1];
                $controllerFile = __DIR__ . '/../controllers/' . $controllerName . '.php';

                if (file_exists($controllerFile)) {
                    require_once $controllerFile;
                    $controllerFullName = 'Controllers\\' . $controllerName;

                    if (class_exists($controllerFullName)) {
                        $controller = new $controllerFullName();
                        if (method_exists($controller, $methodName)) {
                            // Chama o método do controller
                            return $controller->$methodName();
                        }
                    }
                }

                // Se algo deu errado com a configuração da rota
                http_response_code(500);
                echo json_encode(['erro' => 'Ação da rota mal configurada.']);
                return;
            }
        }

        // Se a rota não for encontrada
        http_response_code(404);
        LogHelper::registrarRotaNaoEncontrada($uri, $method, 'Router.php');
        echo json_encode(['erro' => 'Rota não encontrada.', 'uri' => $uri, 'method' => $method]);
    }
}

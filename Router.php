<?php

class Router
{
    private $routes = [
        'GET'    => [],
        'POST'   => [],
        'PUT'    => [],
        'DELETE' => [],
    ];

    public function get($path, $callback)
    {
        $this->routes['GET'][$path] = $callback;
    }

    public function post($path, $callback)
    {
        $this->routes['POST'][$path] = $callback;
    }

    public function put($path, $callback)
    {
        $this->routes['PUT'][$path] = $callback;
    }

    public function delete($path, $callback)
    {
        $this->routes['DELETE'][$path] = $callback;
    }

    public function run()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        $url = isset($_GET['url']) ? $_GET['url'] : '';

        if (isset($this->routes[$method][$url])) {
            $callback = $this->routes[$method][$url];
            call_user_func($callback);
        } else {
            header("HTTP/1.0 404 Not Found");
            echo json_encode(["error" => "Route not found"]);
        }
    }
}

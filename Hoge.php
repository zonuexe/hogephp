<?php

declare(strict_types=1);

namespace Hoge;

use Closure;

final class Framework
{
    private string $docroot;
    private Closure $notfound;
    private string $method;
    private string $uri;
    private array $static_routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
    ];

    public function __call(string $name, array $args)
    {
        if (in_array($name, ['get', 'post', 'put', 'delete'], true)) {
            $path = array_shift($args);
            $callback = array_shift($args);

            return $this->addRoute($name, $path, $callback);
        }
    }

    public function __construct(array $server, string $docroot)
    {
        $this->docroot = $docroot;
        $this->notfound = static fn() => [404, [], '<h1>404 NotFound</h1>'] ;
        $this->method = $server['REQUEST_METHOD'];
        $this->uri = parse_url($server['REQUEST_URI'], PHP_URL_PATH);
    }

    public function __invoke(Closure $callback)
    {
        $callback($this);
        $response = $this->dispatch($this->method, $this->uri);

        http_response_code($response['status']);

        foreach ($response['headers'] as $name => $fields) {
            foreach ((array)$fields as $field) {
                header("{$name}: {$field}");
            }
        }

        if ($this->method === 'HEAD') {
            return '';
        }

        return $response['body'];
    }

    /**
     * @return array{status:int,headers:array<string|string[]>,body:string}
     */
    public function dispatch(string $method, string $uri): array
    {
        $default_status = 200;
        $response = [
            'status' => 200,
            'headers' => [],
        ];

        if ($method === 'HEAD') {
            $method = 'GET';
        }

        if (strpos($uri, '..') !== false) {
            $callback = $this->notfound;
        } else {
            $callback = $this->static_routes[$method][$uri] ?? false;
        }

        if ($callback === false && $method === 'GET') {
            $path = $this->docroot . $uri;
            if (is_file($path)) {
                $callback = static fn () => [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => mime_content_type($path),
                        'Content-Length' => filesize($path)
                    ],
                    'body' => new class ($path) {
                        function __construct($path) {$this->path = $path;}
                        function __toString() {return file_get_contents($this->path);}
                    }
                ];
            }
        }

        if ($callback === false) {
            $callback = $this->notfound;
        }

        try {
            ob_start();

            $result = $callback();
        } catch (Throwable $e) {
            $default_status = 500;
        } finally {
            $output = ob_get_clean();
        }

        if ($result === null) {
            $response['body'] = $output;
        } elseif (is_array($result)) {
            $response['status'] = $result['status'] ?? $result[0] ?? $default_status;
            $response['headers'] = $result['headers'] ?? $result[1] ?? [];
            $response['body'] = $result['body'] ?? $result[2] ?? '';
        } else {
            $response['body'] = (string)$result;
            $response['headers']['Content-Length'] = strlen($response['body']);
        }

        return $response;
    }

    public function addRoute(string $method, string $path, Closure $callback): void
    {
        $this->static_routes[strtoupper($method)][$path] = $callback;
    }
}

return new Framework($_SERVER, dirname(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file']));

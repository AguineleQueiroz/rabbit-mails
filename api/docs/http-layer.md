# Camada HTTP — Request, Response, Pipeline

Implementação própria das abstrações HTTP, sem dependência de framework. Três classes simples com responsabilidades bem definidas.

## Request — `src/Http/Request.php`

Representa a requisição HTTP recebida. Imutável por design — todas as propriedades são `readonly`.

```php
class Request
{
    public function __construct(
        public readonly string $method,      // GET, POST, etc.
        public readonly string $uri,         // /api/emails
        public readonly array  $body,        // JSON decodificado do body
        public readonly array  $queryParams, // $_GET
        public readonly array  $headers,
    ) {}
}
```

### `fromGlobals()`
Factory que constrói o Request a partir das superglobais do PHP:

```php
Request::fromGlobals()
// Equivalente a:
new Request(
    method:      $_SERVER['REQUEST_METHOD'],
    uri:         parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
    body:        json_decode(file_get_contents('php://input'), true) ?? [],
    queryParams: $_GET,
    headers:     getallheaders(),
)
```

### Helpers de acesso
- `$request->input('key', $default)` — acessa o body JSON
- `$request->query('key', $default)` — acessa query string

## Response — `src/Http/Response.php`

Representa a resposta HTTP. Também imutável — criada via factory `json()`.

```php
Response::json(['status' => 'ok'])          // 200
Response::json(['error' => 'Not found'], 404)
```

`json()` sempre seta `Content-Type: application/json` e serializa com `JSON_UNESCAPED_UNICODE` para preservar acentos.

A resposta é emitida no final do `index.php`:
```php
http_response_code($response->statusCode);
foreach ($response->headers as $name => $value) {
    header("$name: $value");
}
echo $response->body;
```

## Pipeline — `src/Http/Pipeline.php`

Implementa o padrão **Middleware Chain** (também chamado de Pipe and Filter). Cada middleware pode:
1. Processar o request e passar para o próximo: `return $next($request)`
2. Interromper a cadeia e retornar uma Response imediatamente

```php
$pipeline = new Pipeline();
$response = $pipeline
    ->pipe(CorsMiddleware::class, JsonMiddleware::class)
    ->run($request, $handler);
```

### Como funciona internamente

`array_reduce` com `array_reverse` constrói a cadeia de forma funcional:

```php
// Para [A, B] com destino D, produz:
// fn($req) => A->handle($req, fn($req) => B->handle($req, D))
```

Os middlewares são executados na **ordem em que foram adicionados** (A antes de B) graças ao `array_reverse` antes do reduce.

## Fluxo Completo no `public/index.php`

```
Request::fromGlobals()
    │
    ▼
Pipeline::run()
    │
    ├── CorsMiddleware::handle()   ← Seta headers CORS, responde OPTIONS
    │       │
    │       ▼
    └── JsonMiddleware::handle()   ← Seta Content-Type: application/json
            │
            ▼
        FastRoute dispatch
            │
            ├── NOT_FOUND          → Response 404
            ├── METHOD_NOT_ALLOWED → Response 405
            └── FOUND              → (new Controller)->method($request, $vars)
```

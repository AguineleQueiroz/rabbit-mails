# Middlewares

Implementam a interface `MiddlewareInterface` e são executados em cadeia pelo `Pipeline` antes de qualquer lógica de controller.

## MiddlewareInterface

```php
interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
```

Cada middleware recebe o `Request` atual e um `$next` callable que representa o restante da cadeia. Pode chamar `$next($request)` para continuar ou retornar um `Response` diretamente para interromper.

## CorsMiddleware

Permite que o dashboard Vue.js (em `localhost:5173`) faça requisições para a API (em `localhost:8000`). Sem esse middleware, o browser bloquearia as chamadas por violar a política Same-Origin.

```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($request->method === 'OPTIONS') {
    return new Response('', 204);  // Responde preflight sem passar ao controller
}
```

**Por que interceptar OPTIONS?**
Antes de uma requisição `POST` ou `PUT`, o browser envia uma requisição `OPTIONS` (preflight) para verificar se o servidor aceita cross-origin. O CorsMiddleware responde com `204 No Content` imediatamente, sem acionar o FastRoute nem instanciar controllers.

## JsonMiddleware

Garante que **todas** as respostas da API sejam JSON, independente do que o controller retornar.

```php
header('Content-Type: application/json');
return $next($request);
```

É o middleware mais simples — apenas seta o header e passa o controle adiante. Trabalha em conjunto com o `Response::json()` que já inclui `Content-Type` no objeto de resposta.

## Ordem de Execução

```php
$pipeline->pipe(CorsMiddleware::class, JsonMiddleware::class)
```

```
Requisição → [CorsMiddleware] → [JsonMiddleware] → [Controller]
Resposta   ← [CorsMiddleware] ← [JsonMiddleware] ← [Controller]
```

**CorsMiddleware antes de JsonMiddleware** porque o CORS precisa responder preflight `OPTIONS` antes mesmo de saber o Content-Type da resposta.

## Adicionando um Novo Middleware

1. Criar a classe em `src/Middleware/` implementando `MiddlewareInterface`
2. Adicionar ao `pipe()` no `public/index.php`

Exemplo — middleware de rate limiting:
```php
class RateLimitMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        if ($this->isRateLimited($ip)) {
            return Response::json(['error' => 'Too many requests'], 429);
        }
        return $next($request);
    }
}
```

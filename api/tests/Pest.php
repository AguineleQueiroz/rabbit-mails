<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Bootstrap dos testes
|--------------------------------------------------------------------------
| Carrega .env de teste (usa variáveis já definidas no container Docker)
| e inicializa o autoloader.
*/

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Garante que a conexão singleton seja resetada entre suítes
afterEach(function () {
    \App\Database\Connection::reset();
    Mockery::close();
});

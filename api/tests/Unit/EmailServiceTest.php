<?php

use App\Services\EmailService;

beforeEach(function () {
    $_ENV['MAIL_HOST'] = '127.0.0.1';
    $_ENV['MAIL_PORT'] = '2525';
    $_ENV['MAIL_FROM'] = 'test@emailqueue.local';
    $_ENV['MAIL_TLS']  = 'false';
    $_ENV['MAIL_USER'] = '';
    $_ENV['MAIL_PASS'] = '';
});

it('lança exceção quando não consegue conectar ao SMTP', function () {
    $_ENV['MAIL_PORT'] = '1'; // porta fechada — força falha de conexão

    $service = new EmailService();

    expect(fn () => $service->send('dest@test.com', 'Assunto', '<p>Corpo</p>'))
        ->toThrow(\RuntimeException::class, 'Falha ao conectar ao SMTP');
});

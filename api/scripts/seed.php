#!/usr/bin/env php
<?php

/**
 * Publica N e-mails na fila para simular carga.
 *
 * Uso:
 *   docker compose run --rm api php scripts/seed.php [quantidade]
 *
 * Exemplos:
 *   docker compose run --rm api php scripts/seed.php        # 50 e-mails
 *   docker compose run --rm api php scripts/seed.php 200    # 200 e-mails
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Repositories\EmailRepository;
use App\Repositories\EventRepository;
use App\Services\QueueService;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$count = max(1, (int)($argv[1] ?? 50));

$emailRepo = new EmailRepository();
$eventRepo = new EventRepository();
$queue     = new QueueService();

$domains  = ['gmail.com', 'outlook.com', 'yahoo.com', 'empresa.com.br', 'teste.dev'];
$subjects = [
    'Bem-vindo à plataforma!',
    'Confirmação de cadastro',
    'Sua fatura está disponível',
    'Redefinição de senha',
    'Promoção exclusiva para você',
    'Relatório semanal',
    'Notificação de sistema',
    'Resumo de atividades',
];

$width = strlen((string) $count);
$start = microtime(true);

echo str_repeat('─', 60) . "\n";
echo " Seed: publicando {$count} e-mail(s) na fila\n";
echo str_repeat('─', 60) . "\n";

for ($i = 1; $i <= $count; $i++) {
    $domain    = $domains[array_rand($domains)];
    $subject   = $subjects[array_rand($subjects)];
    $recipient = "usuario{$i}@{$domain}";

    $email = $emailRepo->create([
        'recipient' => $recipient,
        'subject'   => $subject,
        'body'      => "<p>Olá, este é o e-mail de teste <strong>#{$i}</strong>.</p>",
    ]);

    $queue->publish([
        'email_id'     => $email['id'],
        'recipient'    => $email['recipient'],
        'subject'      => $email['subject'],
        'body'         => $email['body'],
        'attempt'      => 1,
        'published_at' => date('c'),
    ]);

    $emailRepo->updateStatus($email['id'], 'queued');
    $eventRepo->create($email['id'], 'published');

    $bar     = str_pad(str_repeat('█', (int)(($i / $count) * 30)), 30);
    $pct     = str_pad((int)(($i / $count) * 100), 3, ' ', STR_PAD_LEFT);
    $counter = str_pad($i, $width, ' ', STR_PAD_LEFT);
    echo "\r  [{$bar}] {$pct}%  {$counter}/{$count}";
}

$elapsed = round(microtime(true) - $start, 2);

echo "\n" . str_repeat('─', 60) . "\n";
echo " {$count} e-mail(s) publicados em {$elapsed}s\n";
echo str_repeat('─', 60) . "\n\n";
echo " Para escalar workers antes de processar:\n";
echo "   docker compose up -d --scale consumer=<N>\n\n";

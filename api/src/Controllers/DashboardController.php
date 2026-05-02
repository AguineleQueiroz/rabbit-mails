<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\EmailRepository;
use App\Repositories\EventRepository;
use App\Services\HeartbeatService;

readonly class DashboardController
{
    public function __construct(
        private EmailRepository  $emails    = new EmailRepository(),
        private EventRepository  $events    = new EventRepository(),
        private HeartbeatService $heartbeat = new HeartbeatService('api'),
    ) {}

    public function stats(Request $request): Response
    {
        $emailStats = $this->emails->getStats();
        $consumers  = $this->heartbeat->getAll();

        return Response::json([
            'queue' => [
                'pending'    => (int)$emailStats['queued'],
                'processing' => (int)$emailStats['processing'],
            ],
            'emails' => [
                'sent_today'   => (int)$emailStats['sent_today'],
                'failed_today' => (int)$emailStats['failed_today'],
                'dead'         => (int)$emailStats['dead'],
                'total'        => (int)$emailStats['total'],
            ],
            'consumers' => [
                'active' => count(array_filter($consumers, fn ($c) => $c['status'] === 'active')),
                'idle'   => count(array_filter($consumers, fn ($c) => $c['status'] === 'idle')),
            ],
        ]);
    }

    public function chart(Request $request): Response
    {
        return Response::json($this->emails->getChartData());
    }

    public function consumers(Request $request): Response
    {
        return Response::json($this->heartbeat->getAll());
    }

    public function events(Request $request): Response
    {
        $limit = (int)$request->query('limit', 50);
        return Response::json($this->events->latest($limit));
    }

    public function queue(Request $request): Response
    {
        $url  = "{$_ENV['RABBITMQ_MANAGEMENT_URL']}/api/queues/%2F/emails.pending";
        $auth = base64_encode("{$_ENV['RABBITMQ_USER']}:{$_ENV['RABBITMQ_PASSWORD']}");

        $ctx = stream_context_create(['http' => [
            'header'  => "Authorization: Basic $auth",
            'timeout' => 3,
        ]]);

        $raw  = @file_get_contents($url, false, $ctx);
        $data = $raw ? json_decode($raw, true) : [];

        return Response::json([
            'messages'      => $data['messages'] ?? 0,
            'consumers'     => $data['consumers'] ?? 0,
            'message_stats' => $data['message_stats'] ?? [],
        ]);
    }
}

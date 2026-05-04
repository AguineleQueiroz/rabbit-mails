<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\EmailRepository;
use App\Repositories\EventRepository;
use App\Services\QueueService;

readonly class EmailController
{
    public function __construct(
        private EmailRepository $emails = new EmailRepository(),
        private EventRepository $events = new EventRepository(),
        private QueueService    $queue  = new QueueService(),
    ) {}

    public function store(Request $request): Response
    {
        $recipient = $request->input('recipient');
        $subject   = $request->input('subject');
        $body      = $request->input('body');

        if (!$recipient || !$subject || !$body) {
            return Response::json(['error' => 'recipient, subject and body is required'], 422);
        }

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return Response::json(['error' => 'E-mail inválido'], 422);
        }

        $email = $this->emails->create(compact('recipient', 'subject', 'body'));

        $this->queue->publish([
            'email_id'     => $email['id'],
            'recipient'    => $email['recipient'],
            'subject'      => $email['subject'],
            'body'         => $email['body'],
            'attempt'      => 1,
            'published_at' => date('c'),
        ]);

        $this->emails->updateStatus($email['id'], 'queued');
        $this->events->create($email['id'], 'published');

        return Response::json([
            'id'      => $email['id'],
            'status'  => 'queued',
            'message' => 'Email successfully added to queue.',
        ], 202);
    }

    public function index(Request $request): Response
    {
        $result = $this->emails->paginate(
            status:  $request->query('status', ''),
            page:    (int)$request->query('page', 1),
            perPage: (int)$request->query('per_page', 20),
        );

        return Response::json($result);
    }

    public function show(Request $request, array $vars): Response
    {
        $email = $this->emails->findById($vars['id']);

        if (!$email) {
            return Response::json(['error' => 'E-mail not found'], 404);
        }

        return Response::json($email);
    }

    public function requeue(Request $request, array $vars): Response
    {
        $email = $this->emails->findById($vars['id']);

        if (!$email) {
            return Response::json(['error' => 'E-mail not found'], 404);
        }

        $this->emails->resetForRequeue($email['id']);

        $this->queue->publish([
            'email_id'     => $email['id'],
            'recipient'    => $email['recipient'],
            'subject'      => $email['subject'],
            'body'         => $email['body'],
            'attempt'      => 1,
            'published_at' => date('c'),
        ]);

        $this->events->create($email['id'], 'requeued');

        return Response::json(['message' => 'Email re-queued.']);
    }
}

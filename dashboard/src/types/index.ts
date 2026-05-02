export interface Email {
  id: string
  recipient: string
  subject: string
  body: string
  status: 'pending' | 'queued' | 'processing' | 'sent' | 'failed' | 'dead'
  attempts: number
  error_message: string | null
  queued_at: string | null
  processed_at: string | null
  created_at: string
  updated_at: string
}

export interface Stats {
  queue: { pending: number; processing: number }
  emails: { sent_today: number; failed_today: number; dead: number; total: number }
  consumers: { active: number; idle: number }
}

export interface Consumer {
  worker_id: string
  status: 'active' | 'idle' | 'stopped'
  last_heartbeat: string
  emails_processed: number
  emails_failed: number
}

export interface QueueEvent {
  id: string
  email_id: string
  event: string
  worker_id: string | null
  payload: Record<string, unknown>
  created_at: string
  recipient: string
  subject: string
}

export interface PaginatedEmails {
  data: Email[]
  total: number
  page: number
  per_page: number
  total_pages: number
}

export interface QueueInfo {
  messages: number
  consumers: number
  message_stats: Record<string, unknown>
}

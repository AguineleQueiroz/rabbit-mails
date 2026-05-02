import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000',
  headers: { 'Content-Type': 'application/json' },
})

export const dashboardApi = {
  getStats:     () => api.get('/api/dashboard/stats'),
  getChart:     () => api.get('/api/dashboard/chart'),
  getQueue:     () => api.get('/api/dashboard/queue'),
  getConsumers: () => api.get('/api/dashboard/consumers'),
  getEvents:    (limit = 50) => api.get(`/api/dashboard/events?limit=${limit}`),
}

export const emailsApi = {
  list:    (params: Record<string, string | number>) => api.get('/api/emails', { params }),
  show:    (id: string) => api.get(`/api/emails/${id}`),
  create:  (data: { recipient: string; subject: string; body: string }) => api.post('/api/emails', data),
  requeue: (id: string) => api.post(`/api/emails/${id}/requeue`),
}

export default api

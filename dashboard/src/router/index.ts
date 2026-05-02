import { createRouter, createWebHistory } from 'vue-router'
import DashboardView from '@/views/DashboardView.vue'
import EmailsView    from '@/views/EmailsView.vue'
import ConsumersView from '@/views/ConsumersView.vue'
import EventsView    from '@/views/EventsView.vue'

export default createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/',          component: DashboardView, name: 'dashboard' },
    { path: '/emails',    component: EmailsView,    name: 'emails' },
    { path: '/consumers', component: ConsumersView, name: 'consumers' },
    { path: '/events',    component: EventsView,    name: 'events' },
  ],
})

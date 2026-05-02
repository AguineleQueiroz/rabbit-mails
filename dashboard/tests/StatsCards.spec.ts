import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import StatsCards from '@/components/StatsCards.vue'
import type { Stats } from '@/types'

const mockStats: Stats = {
  queue:     { pending: 12, processing: 3 },
  emails:    { sent_today: 342, failed_today: 5, dead: 2, total: 1500 },
  consumers: { active: 2, idle: 0 },
}

describe('StatsCards', () => {
  it('renderiza os 4 cards', () => {
    const wrapper = mount(StatsCards, { props: { stats: mockStats } })
    const cards = wrapper.findAll('[data-testid="stat-card"]')
    expect(cards.length).toBe(4)
  })

  it('exibe quantidade na fila corretamente', () => {
    const wrapper = mount(StatsCards, { props: { stats: mockStats } })
    expect(wrapper.text()).toContain('12')
  })

  it('exibe enviados hoje corretamente', () => {
    const wrapper = mount(StatsCards, { props: { stats: mockStats } })
    expect(wrapper.text()).toContain('342')
  })

  it('exibe falharam hoje corretamente', () => {
    const wrapper = mount(StatsCards, { props: { stats: mockStats } })
    expect(wrapper.text()).toContain('5')
  })

  it('exibe workers ativos corretamente', () => {
    const wrapper = mount(StatsCards, { props: { stats: mockStats } })
    expect(wrapper.text()).toContain('2')
  })

  it('exibe dead letter count', () => {
    const wrapper = mount(StatsCards, { props: { stats: mockStats } })
    expect(wrapper.text()).toContain('dead letter')
  })
})

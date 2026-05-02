import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import EmailTable from '@/components/EmailTable.vue'
import type { Email } from '@/types'

const makeEmail = (overrides: Partial<Email> = {}): Email => ({
  id: 'uuid-1',
  recipient: 'test@example.com',
  subject: 'Assunto',
  body: '<p>Corpo</p>',
  status: 'sent',
  attempts: 1,
  error_message: null,
  queued_at: null,
  processed_at: new Date().toISOString(),
  created_at: new Date().toISOString(),
  updated_at: new Date().toISOString(),
  ...overrides,
})

const defaultProps = {
  emails:     [makeEmail()],
  total:      1,
  page:       1,
  totalPages: 1,
  loading:    false,
}

describe('EmailTable', () => {
  it('renderiza linha para cada e-mail', () => {
    const emails  = [makeEmail({ id: '1' }), makeEmail({ id: '2' })]
    const wrapper = mount(EmailTable, { props: { ...defaultProps, emails, total: 2 } })
    const rows    = wrapper.findAll('tbody tr')
    expect(rows.length).toBe(2)
  })

  it('exibe mensagem quando não há e-mails', () => {
    const wrapper = mount(EmailTable, { props: { ...defaultProps, emails: [], total: 0 } })
    expect(wrapper.text()).toContain('Nenhum e-mail encontrado')
  })

  it('exibe botão Reprocessar apenas para status failed e dead', async () => {
    const failed  = makeEmail({ id: '1', status: 'failed' })
    const sent    = makeEmail({ id: '2', status: 'sent' })
    const wrapper = mount(EmailTable, { props: { ...defaultProps, emails: [failed, sent], total: 2 } })

    const buttons = wrapper.findAll('button').filter(b => b.text() === 'Reprocessar')
    expect(buttons.length).toBe(1)
  })

  it('emite requeue com id correto ao clicar em Reprocessar', async () => {
    const email   = makeEmail({ status: 'failed' })
    const wrapper = mount(EmailTable, { props: { ...defaultProps, emails: [email] } })

    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('requeue')?.[0]).toEqual(['uuid-1'])
  })

  it('emite page-change ao clicar em Próxima', async () => {
    const wrapper = mount(EmailTable, { props: { ...defaultProps, page: 1, totalPages: 3 } })
    const nextBtn = wrapper.findAll('button').find(b => b.text().includes('Próxima'))
    await nextBtn?.trigger('click')
    expect(wrapper.emitted('page-change')?.[0]).toEqual([2])
  })

  it('emite filter-change ao mudar o select', async () => {
    const wrapper = mount(EmailTable, { props: defaultProps })
    const select  = wrapper.find('select')
    await select.setValue('failed')
    expect(wrapper.emitted('filter-change')?.[0]).toEqual(['failed'])
  })

  it('exibe estado de loading', () => {
    const wrapper = mount(EmailTable, { props: { ...defaultProps, loading: true } })
    expect(wrapper.text()).toContain('Carregando')
  })
})

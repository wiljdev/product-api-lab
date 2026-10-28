import { describe, expect, it } from 'vitest'
import { formatMoney, formatRate } from './format'

// Intl separates amount and currency with non-breaking spaces.
const plain = (text) => text.replace(/\s/gu, ' ')

describe('formatMoney', () => {
  it('formats API decimal strings with the currency', () => {
    expect(plain(formatMoney('89.90', 'USD'))).toBe('89,90 US$')
    expect(plain(formatMoney('1250000.00', 'COP'))).toBe('1.250.000,00 COP')
  })

  it('accepts other locales', () => {
    expect(plain(formatMoney('89.90', 'USD', 'en-US'))).toBe('$89.90')
  })

  it('falls back to plain text for invalid currency codes', () => {
    expect(plain(formatMoney('10.5', 'XXXX'))).toBe('10.50 XXXX')
  })

  it('returns the raw value when the amount is not numeric', () => {
    expect(plain(formatMoney('n/a', 'USD'))).toBe('n/a USD')
  })
})

describe('formatRate', () => {
  it('removes trailing zeros from exchange rates', () => {
    expect(formatRate('1.000000')).toBe('1')
    expect(formatRate('0.920000')).toBe('0,92')
    expect(formatRate('4150.000000')).toBe('4150')
  })
})

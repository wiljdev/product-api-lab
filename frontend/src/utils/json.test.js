import { describe, expect, it } from 'vitest'
import { escapeHtml, highlightJson } from './json'

describe('escapeHtml', () => {
  it('escapes HTML special characters', () => {
    expect(escapeHtml(`<a href="x">'&'</a>`)).toBe('&lt;a href=&quot;x&quot;&gt;&#39;&amp;&#39;&lt;/a&gt;')
  })
})

describe('highlightJson', () => {
  it('wraps keys, strings, numbers and literals', () => {
    const html = highlightJson({ name: 'Desk', price: 10.5, active: true, deleted_at: null })

    expect(html).toContain('<span class="json-key">&quot;name&quot;</span>:')
    expect(html).toContain('<span class="json-string">&quot;Desk&quot;</span>')
    expect(html).toContain('<span class="json-number">10.5</span>')
    expect(html).toContain('<span class="json-literal">true</span>')
    expect(html).toContain('<span class="json-literal">null</span>')
  })

  it('never renders HTML coming from the API', () => {
    const html = highlightJson({ name: '<img src=x onerror=alert(1)>' })

    expect(html).not.toContain('<img')
    expect(html).toContain('&lt;img src=x onerror=alert(1)&gt;')
  })

  it('handles escaped quotes inside strings', () => {
    const html = highlightJson({ name: 'Monitor 27" QHD' })

    expect(html).toContain('<span class="json-string">&quot;Monitor 27\\&quot; QHD&quot;</span>')
  })

  it('returns an empty string for empty bodies', () => {
    expect(highlightJson(null)).toBe('')
    expect(highlightJson('')).toBe('')
  })

  it('escapes plain text bodies', () => {
    expect(highlightJson('<html>error</html>')).toBe('&lt;html&gt;error&lt;/html&gt;')
  })
})

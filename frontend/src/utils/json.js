const ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }

/**
 * @param {string} text
 */
export function escapeHtml(text) {
  return text.replace(/[&<>"']/g, (character) => ESCAPES[character])
}

/**
 * Pretty-print a value as JSON and wrap its tokens in spans for highlighting.
 *
 * The JSON text is HTML-escaped before any markup is added, so strings coming
 * from the API (for example a product named "<script>") are rendered as text.
 *
 * @param {any} value
 * @returns {string} Safe HTML.
 */
export function highlightJson(value) {
  if (value === null || value === undefined || value === '') {
    return ''
  }

  const json = typeof value === 'string' ? value : JSON.stringify(value, null, 2)
  const escaped = escapeHtml(json)

  return escaped.replace(
    /(&quot;(?:\\.|(?!&quot;).)*&quot;)(\s*:)?|\b(true|false|null)\b|(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/g,
    (match, string, colon, literal, number) => {
      if (string) {
        const kind = colon ? 'json-key' : 'json-string'
        return `<span class="${kind}">${string}</span>${colon ?? ''}`
      }
      if (literal) {
        return `<span class="json-literal">${literal}</span>`
      }
      if (number) {
        return `<span class="json-number">${number}</span>`
      }
      return match
    },
  )
}

/**
 * Format an API amount (a decimal string such as "89.90") for display.
 *
 * Amounts stay strings everywhere else; they are converted to numbers only here,
 * to render them with the currency format of the user's language. Two decimals
 * are always shown, matching what the API stores: otherwise each browser's locale
 * data decides (for example, recent ICU versions drop the decimals of COP).
 *
 * @param {string|number} amount
 * @param {string} currencyCode ISO 4217 code, e.g. "USD".
 * @param {string} [locale]
 */
export function formatMoney(amount, currencyCode, locale = 'es') {
  const value = Number(amount)

  if (!Number.isFinite(value)) {
    return `${amount} ${currencyCode}`.trim()
  }

  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: currencyCode,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(value)
  } catch {
    return `${value.toFixed(2)} ${currencyCode}`
  }
}

/**
 * Format an exchange rate against 1 USD without trailing zeros, e.g. "4150.000000" -> "4.150".
 *
 * @param {string|number} rate
 * @param {string} [locale]
 */
export function formatRate(rate, locale = 'es') {
  const value = Number(rate)

  if (!Number.isFinite(value)) {
    return String(rate)
  }

  return new Intl.NumberFormat(locale, { maximumFractionDigits: 6 }).format(value)
}

const API_BASE_URL = 'https://api.frankfurter.dev/v2'

export async function fetchExchangeRates() {

    const url = new URL(`${API_BASE_URL}/rates`)

    url.searchParams.set('base', 'MYR')
    url.searchParams.set('quotes', 'USD,SGD,EUR')

    const response = await fetch(url, {
        headers: {
            Accept: 'application/json'
        }
    })

    const data = await response.json().catch(() => null)

    if (!response.ok) {
        throw new Error(
            data?.message ||
            `Exchange rate API error: ${response.status}`
        )
    }

    if (!Array.isArray(data)) {
        throw new Error('Invalid exchange rate response.')
    }

    const rates = {
        MYR: 1
    }

    const dates = []

    for (const item of data) {

        if (
            ['USD', 'SGD', 'EUR'].includes(item.quote) &&
            item.base === 'MYR' &&
            Number.isFinite(Number(item.rate))
        ) {
            rates[item.quote] = Number(item.rate)

            if (item.date) {
                dates.push(item.date)
            }
        }
    }

    const missing = ['USD', 'SGD', 'EUR'].filter(
        code => rates[code] === undefined
    )

    if (missing.length > 0) {
        throw new Error(
            `Missing exchange rates: ${missing.join(', ')}`
        )
    }

    return {
        rates,
        date: dates.sort()[0] || null
    }
}

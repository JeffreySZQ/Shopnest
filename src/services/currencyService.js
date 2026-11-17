//address the third party API for exchange rates
const API_BASE_URL = 'https://api.frankfurter.dev/v2'

//function to fetch exchange rates from the API
export async function fetchExchangeRates() {

    //create the URL for the API request
    const url = new URL(`${API_BASE_URL}/rates`)

    url.searchParams.set('base', 'MYR')
    url.searchParams.set('quotes', 'USD,SGD,EUR')

    //sends the HTTP request to the API and waits for the response
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

    //loops through the data array and extracts the exchange rates for USD, SGD, and EUR
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

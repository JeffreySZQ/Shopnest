import { defineStore } from 'pinia'
import { fetchExchangeRates } from '@/services/currencyService.js'

const CURRENCY_KEY = 'shopnest-selected-currency'
const CACHE_KEY = 'shopnest-exchange-rate-cache'

const CACHE_DURATION = 12 * 60 * 60 * 1000

const SUPPORTED_CURRENCIES = [
    'MYR',
    'USD',
    'SGD',
    'EUR'
]

function readCurrency() {
    try {
        const saved = localStorage.getItem(CURRENCY_KEY)

        return SUPPORTED_CURRENCIES.includes(saved)
            ? saved
            : 'MYR'
    } catch {
        return 'MYR'
    }
}

function saveCurrency(currency) {
    try {
        localStorage.setItem(CURRENCY_KEY, currency)
    } catch {
        // Currency remains available in Pinia memory.
    }
}

function readCache() {
    try {
        const saved = localStorage.getItem(CACHE_KEY)

        if (!saved) return null

        const cache = JSON.parse(saved)

        const valid = cache?.rates &&
            ['MYR', 'USD', 'SGD', 'EUR'].every(
                code => Number.isFinite(Number(cache.rates[code]))
            )

        return valid ? cache : null
    } catch {
        return null
    }
}

function formatMoney(amount, currency) {
    return new Intl.NumberFormat('en-MY', {
        style: 'currency',
        currency
    }).format(amount)
}

export const useCurrencyStore = defineStore('currency', {

    state: () => {
        const cache = readCache()

        return {
            selectedCurrency: readCurrency(),

            rates: cache?.rates || {
                MYR: 1
            },

            rateDate: cache?.date || null,

            fetchedAt: Number(cache?.fetchedAt) || 0,

            loading: false,

            error: '',

            ratesReady: Boolean(cache)
        }
    },

    actions: {

        setCurrency(currency) {
            if (!SUPPORTED_CURRENCIES.includes(currency)) {
                return
            }

            this.selectedCurrency = currency

            saveCurrency(currency)
        },

        async loadRates(force = false) {

            if (this.loading) return

            const cache = readCache()

            if (cache) {
                this.rates = cache.rates
                this.rateDate = cache.date
                this.fetchedAt = Number(cache.fetchedAt) || 0
                this.ratesReady = true
            }

            const cacheIsFresh =
                cache &&
                Date.now() - Number(cache.fetchedAt) <
                CACHE_DURATION

            if (!force && cacheIsFresh) {
                return
            }

            this.loading = true
            this.error = ''

            try {

                const result = await fetchExchangeRates()

                this.rates = result.rates
                this.rateDate = result.date
                this.fetchedAt = Date.now()
                this.ratesReady = true

                try {
                    localStorage.setItem(
                        CACHE_KEY,
                        JSON.stringify({
                            rates: result.rates,
                            date: result.date,
                            fetchedAt: this.fetchedAt
                        })
                    )
                } catch {
                    // Continue without local cache.
                }

            } catch (error) {

                if (cache) {

                    this.error =
                        'Unable to refresh rates. Using saved exchange rates.'

                } else {

                    this.setCurrency('MYR')

                    this.rates = {
                        MYR: 1
                    }

                    this.error =
                        'Exchange rates unavailable. Showing MYR prices.'
                }

            } finally {
                this.loading = false
            }
        },

        formatPrice(amount) {

            const value = Number(amount)

            if (!Number.isFinite(value)) {
                return '—'
            }

            const rate = this.rates[this.selectedCurrency]

            if (!Number.isFinite(Number(rate))) {
                return this.loading
                    ? 'Loading rate...'
                    : formatMoney(value, 'MYR')
            }

            const convertedAmount = value * Number(rate)

            return formatMoney(
                convertedAmount,
                this.selectedCurrency
            )
        }
    }
})

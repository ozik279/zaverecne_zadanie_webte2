const DEFAULT_API_BASE_URL = '/api'

export function getApiBaseUrl() {
  return (import.meta.env.VITE_API_BASE_URL || DEFAULT_API_BASE_URL).replace(/\/$/, '')
}

export function getApiKey() {
  return import.meta.env.VITE_API_KEY || 'dev-api-key'
}

export async function requestJson(path, options = {}) {
  const response = await fetch(`${getApiBaseUrl()}${path}`, {
    ...options,
    credentials: options.credentials || 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(getApiKey() ? { 'X-API-Key': getApiKey() } : {}),
      ...(options.headers || {}),
    },
  })

  const text = await response.text()
  const data = text ? safeJsonParse(text) : null

  if (!response.ok) {
    const message = data?.message || data?.error || data?.detail || response.statusText || 'Request failed.'
    throw new Error(message)
  }

  return data
}

export async function requestBlob(path, options = {}) {
  const response = await fetch(`${getApiBaseUrl()}${path}`, {
    ...options,
    credentials: options.credentials || 'same-origin',
    headers: {
      Accept: options.accept || '*/*',
      ...(getApiKey() ? { 'X-API-Key': getApiKey() } : {}),
      ...(options.headers || {}),
    },
  })

  if (!response.ok) {
    const text = await response.text()
    const data = text ? safeJsonParse(text) : null
    const message = data?.message || data?.error || data?.detail || response.statusText || 'Request failed.'
    throw new Error(message)
  }

  return response.blob()
}

export function buildQuery(params) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      query.set(key, value)
    }
  })

  const text = query.toString()
  return text ? `?${text}` : ''
}

function safeJsonParse(text) {
  try {
    return JSON.parse(text)
  } catch {
    return null
  }
}

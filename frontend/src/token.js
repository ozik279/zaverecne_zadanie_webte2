const COOKIE_NAME = 'cas_client_token'

export function getOrCreateClientToken() {
  const existing = readCookie(COOKIE_NAME)

  if (existing) {
    return existing
  }

  const token = createToken()
  writeCookie(COOKIE_NAME, token, 365)
  return token
}

export function readCookie(name) {
  const match = document.cookie.match(new RegExp(`(?:^|; )${escapeRegExp(name)}=([^;]*)`))
  return match ? decodeURIComponent(match[1]) : ''
}

export function writeCookie(name, value, days) {
  const expires = new Date(Date.now() + days * 86400000).toUTCString()
  document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`
}

function createToken() {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID()
  }

  return `cas-${Math.random().toString(36).slice(2)}-${Date.now().toString(36)}`
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

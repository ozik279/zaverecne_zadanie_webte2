import { useEffect, useMemo, useState } from 'react'
import SwaggerUI from 'swagger-ui-react'
import 'swagger-ui-react/swagger-ui.css'
import { getApiKey, requestBlob, requestJson } from '../api'
import { translations } from '../i18n'

export default function ApiDocsPage({ language }) {
  const t = translations[language]
  const [spec, setSpec] = useState(null)
  const [loading, setLoading] = useState(true)
  const [pdfLoading, setPdfLoading] = useState(false)
  const [error, setError] = useState('')
  const requestInterceptor = useMemo(() => createSwaggerRequestInterceptor(), [])

  useEffect(() => {
    let cancelled = false

    async function loadSpec() {
      setLoading(true)
      setError('')

      try {
        const data = await requestJson('/openapi.json')

        if (!cancelled) {
          setSpec(data)
        }
      } catch (requestError) {
        if (!cancelled) {
          setError(requestError.message)
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    loadSpec()

    return () => {
      cancelled = true
    }
  }, [])

  async function openPdf() {
    setPdfLoading(true)
    setError('')

    try {
      const blob = await requestBlob('/openapi.pdf', {
        accept: 'application/pdf',
      })
      const url = URL.createObjectURL(blob)
      const opened = window.open(url, '_blank', 'noopener,noreferrer')

      if (!opened) {
        const link = document.createElement('a')
        link.href = url
        link.target = '_blank'
        link.rel = 'noreferrer'
        document.body.appendChild(link)
        link.click()
        link.remove()
      }

      window.setTimeout(() => URL.revokeObjectURL(url), 60000)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setPdfLoading(false)
    }
  }

  return (
    <main className="page tool-page">
      <section className="hero compact tool-hero">
        <div>
          <p className="eyebrow">{t.apiDocs}</p>
          <h1>{t.apiDocsTitle}</h1>
        </div>
      </section>

      <section className="card data-card api-docs-card tool-card">
        <div className="section-head data-head">
          <div>
            <h2>{spec?.info?.title || t.apiDocs}</h2>
          </div>
          <button className="primary-button" type="button" onClick={openPdf} disabled={pdfLoading}>
            {pdfLoading ? t.loadingData : t.apiDocsPdf}
          </button>
        </div>

        {loading ? <div className="empty-state">{t.loadingData}</div> : null}
        {error ? <div className="alert error">{t.error}: {error}</div> : null}

        {!loading && !spec ? (
          <div className="empty-state">{t.noData}</div>
        ) : null}

        {spec ? (
          <div className="swagger-panel">
            <SwaggerUI
              spec={spec}
              deepLinking
              displayRequestDuration
              docExpansion="list"
              requestInterceptor={requestInterceptor}
            />
          </div>
        ) : null}
      </section>
    </main>
  )
}

function createSwaggerRequestInterceptor() {
  return (request) => {
    const apiKey = getApiKey()
    request.headers = request.headers || {}

    const hasApiKey = Object.keys(request.headers).some((header) => header.toLowerCase() === 'x-api-key')

    if (apiKey && !hasApiKey) {
      request.headers['X-API-Key'] = apiKey
    }

    return request
  }
}

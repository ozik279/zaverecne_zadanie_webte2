import { useEffect, useState } from 'react'
import { buildQuery, requestBlob, requestJson } from '../api'
import { translations } from '../i18n'

const INITIAL_FILTERS = {
  source: '',
  successful: '',
  perPage: '25',
  page: 1,
}

export default function LogsPage({ language }) {
  const t = translations[language]
  const [filters, setFilters] = useState(INITIAL_FILTERS)
  const [appliedFilters, setAppliedFilters] = useState(INITIAL_FILTERS)
  const [logs, setLogs] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(false)
  const [exporting, setExporting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    let cancelled = false

    async function loadLogs() {
      setLoading(true)
      setError('')

      try {
        const data = await requestJson(`/logs${buildQuery(appliedFilters)}`)

        if (!cancelled) {
          setLogs(data?.data || [])
          setMeta(data?.meta || null)
        }
      } catch (requestError) {
        if (!cancelled) {
          setError(requestError.message)
          setLogs([])
          setMeta(null)
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    loadLogs()

    return () => {
      cancelled = true
    }
  }, [appliedFilters])

  function updateFilter(name, value) {
    setFilters((current) => ({
      ...current,
      [name]: value,
    }))
  }

  function applyFilters(event) {
    event.preventDefault()
    setAppliedFilters({ ...filters, page: 1 })
  }

  function clearFilters() {
    setFilters(INITIAL_FILTERS)
    setAppliedFilters(INITIAL_FILTERS)
  }

  function goToPage(page) {
    setAppliedFilters((current) => ({
      ...current,
      page,
    }))
  }

  async function exportCsv() {
    setExporting(true)
    setError('')

    try {
      const csvFilters = Object.fromEntries(
        Object.entries(appliedFilters).filter(([key]) => !['page', 'perPage'].includes(key)),
      )
      const blob = await requestBlob(`/logs/export.csv${buildQuery(csvFilters)}`, {
        accept: 'text/csv',
      })
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `cas-request-logs-${new Date().toISOString().slice(0, 10)}.csv`
      document.body.appendChild(link)
      link.click()
      link.remove()
      URL.revokeObjectURL(url)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setExporting(false)
    }
  }

  const currentPage = meta?.currentPage || 1
  const lastPage = meta?.lastPage || 1

  return (
    <main className="page tool-page">
      <section className="hero compact tool-hero">
        <div>
          <p className="eyebrow">{t.logs}</p>
          <h1>{t.logsTitle}</h1>
        </div>
      </section>

      <section className="card form-card tool-card">
        <div className="section-head">
          <h2>{t.logFilters}</h2>
        </div>

        <form className="filters-grid" onSubmit={applyFilters}>
          <label className="field">
            <span>{t.source}</span>
            <select value={filters.source} onChange={(event) => updateFilter('source', event.target.value)}>
              <option value="">{t.allSources}</option>
              <option value="console">console</option>
              <option value="simulation">{t.simulationSource}</option>
            </select>
          </label>

          <label className="field">
            <span>{t.status}</span>
            <select value={filters.successful} onChange={(event) => updateFilter('successful', event.target.value)}>
              <option value="">{t.allStatuses}</option>
              <option value="true">{t.successOnly}</option>
              <option value="false">{t.failedOnly}</option>
            </select>
          </label>

          <div className="button-row filters-actions">
            <button className="primary-button" type="submit" disabled={loading}>
              {loading ? t.loadingData : t.applyFilters}
            </button>
            <button className="ghost-button" type="button" onClick={clearFilters}>
              {t.clearFilters}
            </button>
            <button className="ghost-button" type="button" onClick={exportCsv} disabled={exporting}>
              {exporting ? t.loadingData : t.exportCsv}
            </button>
          </div>
        </form>

        {error ? <div className="alert error">{t.error}: {error}</div> : null}
      </section>

      <section className="card data-card tool-card">
        <div className="section-head data-head">
          <div>
            <h2>{t.logs}</h2>
            <p>{meta ? `${t.total}: ${meta.total}` : t.noData}</p>
          </div>
          {meta ? (
            <div className="pagination">
              <button className="ghost-button" type="button" disabled={currentPage <= 1} onClick={() => goToPage(currentPage - 1)}>
                {t.previous}
              </button>
              <span>{t.page} {currentPage} / {lastPage}</span>
              <button className="ghost-button" type="button" disabled={currentPage >= lastPage} onClick={() => goToPage(currentPage + 1)}>
                {t.next}
              </button>
            </div>
          ) : null}
        </div>

        {loading ? <div className="empty-state">{t.loadingData}</div> : null}

        {!loading && logs.length === 0 ? (
          <div className="empty-state">{t.noLogs}</div>
        ) : null}

        {logs.length > 0 ? (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>{t.createdAt}</th>
                  <th>{t.status}</th>
                  <th>{t.source}</th>
                  <th>{t.command}</th>
                  <th>{t.executionMs}</th>
                  <th>{t.errorMessage}</th>
                </tr>
              </thead>
              <tbody>
                {logs.map((log, index) => (
                  <tr key={`${log.createdAt || 'log'}-${index}`}>
                    <td>{formatDate(log.createdAt)}</td>
                    <td>
                      <span className={`status-pill ${log.successful ? 'success' : 'failed'}`}>
                        {log.successful ? t.successful : t.failed}
                      </span>
                    </td>
                    <td>{log.source || '-'}</td>
                    <td><code>{log.command || '-'}</code></td>
                    <td>{log.executionMs == null ? '-' : `${log.executionMs} ms`}</td>
                    <td>{log.errorMessage || '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
      </section>
    </main>
  )
}

function formatDate(value) {
  if (!value) {
    return '-'
  }

  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString()
}

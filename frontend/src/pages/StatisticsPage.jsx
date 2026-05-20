import { useEffect, useState } from 'react'
import { requestJson } from '../api'
import { translations } from '../i18n'

export default function StatisticsPage({ language }) {
  const t = translations[language]
  const [data, setData] = useState([])
  const [selectedSimulation, setSelectedSimulation] = useState('inverted-pendulum')
  const [detail, setDetail] = useState(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false

    async function loadStatistics() {
      setLoading(true)
      setError('')

      try {
        const summary = await requestJson('/statistics')
        if (cancelled) {
          return
        }

        setData(summary.data || [])
        const nextSelected = summary.data?.[0]?.simulation || 'inverted-pendulum'
        setSelectedSimulation(nextSelected)
        const nextDetail = await requestJson(`/statistics/${nextSelected}`)

        if (!cancelled) {
          setDetail(nextDetail.data || null)
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

    loadStatistics()

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    let cancelled = false

    async function loadDetail() {
      if (!selectedSimulation) {
        return
      }

      try {
        const response = await requestJson(`/statistics/${selectedSimulation}`)
        if (!cancelled) {
          setDetail(response.data || null)
        }
      } catch {
        if (!cancelled) {
          setDetail(null)
        }
      }
    }

    loadDetail()

    return () => {
      cancelled = true
    }
  }, [selectedSimulation])

  return (
    <main className="page">
      <section className="hero compact">
        <div>
          <p className="eyebrow">{t.statistics}</p>
          <h1>{t.statistics}</h1>
          <p className="hero-copy">{t.statisticsHelp}</p>
        </div>
      </section>

      <section className="card">
        <div className="section-head">
          <h2>{t.metrics}</h2>
          <p>{t.statisticsHelp}</p>
        </div>

        {loading ? <div className="empty-state">{t.loading}</div> : null}
        {error ? <div className="alert error">{error}</div> : null}

        <div className="stats-grid">
          {data.map((item) => (
            <button
              key={item.simulation}
              type="button"
              className={`stats-card ${selectedSimulation === item.simulation ? 'active' : ''}`}
              onClick={() => setSelectedSimulation(item.simulation)}
            >
              <strong>{item.simulation}</strong>
              <span>{t.runs}: {item.runs}</span>
              <span>{t.usages}: {item.usages}</span>
            </button>
          ))}
        </div>

        {detail ? (
          <>
            <div className="detail-grid">
              <div className="detail-card"><span>{t.runs}</span><strong>{detail.runs}</strong></div>
              <div className="detail-card"><span>{t.successfulRuns}</span><strong>{detail.successfulRuns}</strong></div>
              <div className="detail-card"><span>{t.usages}</span><strong>{detail.usages}</strong></div>
              <div className="detail-card"><span>{t.lastRunAt}</span><strong>{formatDate(detail.lastRunAt)}</strong></div>
              <div className="detail-card"><span>{t.lastUsageAt}</span><strong>{formatDate(detail.lastUsageAt)}</strong></div>
            </div>

            <DetailTable
              title={t.recentUsages}
              rows={detail.recentUsages || []}
              columns={[
                ['createdAt', t.createdAt, formatDate],
                ['city', t.city],
                ['country', t.country],
              ]}
              emptyText={t.noData}
            />

            <DetailTable
              title={t.recentRuns}
              rows={detail.recentRuns || []}
              columns={[
                ['createdAt', t.createdAt, formatDate],
                ['successful', t.status, (value) => value ? t.successful : t.failed],
                ['durationMs', t.durationMs, (value) => value == null ? '-' : `${value} ms`],
                ['city', t.city],
                ['country', t.country],
              ]}
              emptyText={t.noData}
            />
          </>
        ) : null}
      </section>
    </main>
  )
}

function DetailTable({ title, rows, columns, emptyText }) {
  return (
    <section className="form-card">
      <div className="section-head">
        <h2>{title}</h2>
      </div>

      {rows.length === 0 ? (
        <div className="empty-state">{emptyText}</div>
      ) : (
        <div className="detail-table-wrap">
          <table className="detail-table">
            <thead>
              <tr>
                {columns.map(([key, label]) => <th key={key}>{label}</th>)}
              </tr>
            </thead>
            <tbody>
              {rows.map((row, rowIndex) => (
                <tr key={`${title}-${rowIndex}`}>
                  {columns.map(([key, , formatter]) => (
                    <td key={key}>{formatter ? formatter(row[key]) : (row[key] || '-')}</td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}

function formatDate(value) {
  if (!value) {
    return '-'
  }

  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString()
}

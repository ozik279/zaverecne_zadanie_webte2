import { useEffect, useState } from 'react'
import { requestJson } from '../api'
import { translations } from '../i18n'

export default function StatisticsPage({ language }) {
  const t = translations[language]
  const [data, setData] = useState([])
  const [selectedSimulation, setSelectedSimulation] = useState('')
  const [detail, setDetail] = useState(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [detailLoading, setDetailLoading] = useState(false)

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

        const summaryRows = summary.data || []
        setData(summaryRows)
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
        setDetail(null)
        return
      }

      setDetailLoading(true)

      try {
        const response = await requestJson(`/statistics/${selectedSimulation}`)

        if (!cancelled) {
          setDetail(response.data || null)
        }
      } catch (requestError) {
        if (!cancelled) {
          setError(requestError.message)
          setDetail(null)
        }
      } finally {
        if (!cancelled) {
          setDetailLoading(false)
        }
      }
    }

    loadDetail()

    return () => {
      cancelled = true
    }
  }, [selectedSimulation])

  return (
    <main className="page tool-page statistics-page">
      <section className="hero compact tool-hero">
        <div>
          <p className="eyebrow">{t.statistics}</p>
          <h1>{t.statistics}</h1>
        </div>
      </section>

      <section className="card tool-card">
        <div className="section-head">
          <h2>{t.metrics}</h2>
        </div>

        {loading ? <div className="empty-state">{t.loadingData}</div> : null}
        {error ? <div className="alert error">{error}</div> : null}

        <div className="stats-grid">
          {data.map((item) => (
            <button
              key={item.simulation}
              type="button"
              className={`stats-card ${selectedSimulation === item.simulation ? 'active' : ''}`}
              onClick={() => setSelectedSimulation(item.simulation)}
            >
              <strong>{labelForSimulation(item.simulation, t)}</strong>
              <span>{t.runs}: {item.usages}</span>
            </button>
          ))}
        </div>

        {detailLoading ? <div className="empty-state">{t.loadingData}</div> : null}

        {!selectedSimulation && !loading ? <div className="empty-state">{t.selectSimulation}</div> : null}

        {detail && !detailLoading ? <SimulationStatisticsDetail detail={detail} t={t} /> : null}
      </section>
    </main>
  )
}

function SimulationStatisticsDetail({ detail, t }) {
  return (
    <section className="statistics-detail-section">
      <div className="section-head">
        <h2>{labelForSimulation(detail.simulation, t)}</h2>
      </div>

      <div className="detail-grid">
        <div className="detail-card"><span>{t.runs}</span><strong>{detail.usages}</strong></div>
        <div className="detail-card"><span>{t.lastRunAt}</span><strong>{formatDate(detail.lastUsageAt)}</strong></div>
      </div>

      <DetailTable
        title={t.recentRuns}
        rows={detail.recentUsages || []}
        columns={[
          ['createdAt', t.createdAt, formatDate],
          ['city', t.city],
          ['country', t.country],
        ]}
        emptyText={t.noData}
      />
    </section>
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
        <div className="table-wrap">
          <table className="data-table">
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

function labelForSimulation(simulation, t) {
  if (simulation === 'inverted-pendulum') {
    return t.pendulum
  }

  if (simulation === 'ball-beam') {
    return t.ballBeam
  }

  return simulation
}

function formatDate(value) {
  if (!value) {
    return '-'
  }

  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString()
}
import { useMemo, useState } from 'react'
import { requestJson } from '../api'
import { getOrCreateClientToken } from '../token'
import SimulationViz from './SimulationViz'

export default function SimulationPage({ title, simulation, simulationKey, endpoint, description, initialForm, languageStrings }) {
  const [form, setForm] = useState(initialForm)
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [playing, setPlaying] = useState(true)
  const clientToken = useMemo(() => getOrCreateClientToken(), [])

  async function handleSubmit(event) {
    event.preventDefault()
    setLoading(true)
    setError('')

    try {
      const payload = {
        ...form,
        clientToken,
      }

      const data = await requestJson(endpoint, {
        method: 'POST',
        body: JSON.stringify(payload),
      })

      setResult(data)
      setPlaying(true)
    } catch (requestError) {
      setError(requestError.message)
      setResult(null)
    } finally {
      setLoading(false)
    }
  }

  function updateField(name, value) {
    setForm((current) => ({
      ...current,
      [name]: value,
    }))
  }

  return (
    <main className="page">
      <section className="hero compact">
        <div>
          <p className="eyebrow">{title}</p>
          <h1>{simulation}</h1>
          <p className="hero-copy">{description}</p>
        </div>
        <div className="hero-chip">
          <span>{languageStrings.currentToken}:</span>
          <strong>{clientToken}</strong>
        </div>
      </section>

      <section className="panel-grid">
        <form className="card form-card" onSubmit={handleSubmit}>
          <div className="section-head">
            <h2>{languageStrings.requestPayload}</h2>
            <p>{languageStrings.apiKeyHint}</p>
          </div>

          <div className="form-grid">
            {Object.entries(form).map(([name, value]) => (
              <label key={name} className="field">
                <span>{labelFor(name, languageStrings)}</span>
                <input
                  type="number"
                  step="any"
                  value={value}
                  onChange={(event) => updateField(name, event.target.value)}
                />
              </label>
            ))}
          </div>

          <div className="button-row">
            <button className="primary-button" type="submit" disabled={loading}>
              {loading ? languageStrings.loading : languageStrings.run}
            </button>
            <button
              className="ghost-button"
              type="button"
              onClick={() => setForm(initialForm)}
            >
              {languageStrings.reset}
            </button>
          </div>

          {error ? <div className="alert error">{languageStrings.error}: {error}</div> : null}
          {result ? (
            <div className={`alert ${result.success ? 'success' : 'error'}`}>
              <strong>{result.success ? languageStrings.success : languageStrings.error}</strong>
              <span>{result.message || result.error || 'OK'}</span>
            </div>
          ) : null}
        </form>

        <div className="card result-card">
          <div className="section-head">
            <h2>{languageStrings.response}</h2>
            <p>{title}</p>
          </div>

          {result ? (
            <>
              <div className="stats-strip">
                    <Stat label={languageStrings.simulationTime} value={`${result.time?.length || 0} ${languageStrings.points}`} />
                    <Stat label={languageStrings.simulationSeries} value={`${result.series?.length || 0}`} />
                    <Stat label={languageStrings.simulationState} value={`${result.state?.length || 0}`} />
                    <Stat label={languageStrings.simulationFrames} value={`${result.frames?.length || 0}`} />
              </div>

              <SimulationViz
                key={`${simulationKey}-${result.frames?.length}`}
                simulation={simulationKey}
                frames={result.frames || []}
                time={result.time || []}
                series={result.series || []}
                title={simulation}
                languageStrings={languageStrings}
                playing={playing}
                playbackDelayMs={Number(form.slowdownMs) || 80}
                onTogglePlaying={() => setPlaying((current) => !current)}
              />
            </>
          ) : (
            <div className="empty-state">{languageStrings.noData}</div>
          )}
        </div>
      </section>
    </main>
  )
}

function Stat({ label, value }) {
  return (
    <div className="stat-box">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  )
}

function labelFor(name, languageStrings) {
  return languageStrings.fieldLabels?.[name] || name
}



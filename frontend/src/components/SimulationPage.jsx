import { useState } from 'react'
import { requestJson } from '../api'
import SimulationViz from './SimulationViz'

export default function SimulationPage({ title, simulation, simulationKey, endpoint, initialForm, fieldConstraints = {}, languageStrings }) {
  const [form, setForm] = useState(initialForm)
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [playing, setPlaying] = useState(true)
  const [resultVersion, setResultVersion] = useState(0)
  const [resultPlaybackDelayMs, setResultPlaybackDelayMs] = useState(Number(initialForm.slowdownMs) || 0)

  async function handleSubmit(event) {
    event.preventDefault()
    setLoading(true)
    setError('')

    try {
      const data = await requestJson(endpoint, {
        method: 'POST',
        body: JSON.stringify(form),
      })

      setResult(data)
      setResultPlaybackDelayMs(Number(form.slowdownMs) || 0)
      setResultVersion((current) => current + 1)
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
    setPlaying(false)
  }

  function resetForm() {
    setForm(initialForm)
    setPlaying(false)
  }

  return (
    <main className="page tool-page simulation-page">
      <section className="hero compact tool-hero">
        <div>
          <p className="eyebrow">{title}</p>
          <h1>{simulation}</h1>
        </div>
      </section>

      <section className="panel-grid simulation-grid">
        <form className="card form-card tool-card" onSubmit={handleSubmit}>
          <div className="section-head">
            <h2>{languageStrings.requestPayload}</h2>
          </div>

          <div className="form-grid">
            {Object.entries(form).map(([name, value]) => {
              const constraints = fieldConstraints[name] || {}

              return (
                <label key={name} className="field">
                  <span>{labelFor(name, languageStrings, simulationKey)}</span>
                  <input
                    type="number"
                    min={constraints.min}
                    max={constraints.max}
                    step={constraints.inputStep ?? 'any'}
                    value={value}
                    onChange={(event) => updateField(name, event.target.value)}
                  />
                  {hasRange(constraints) ? (
                    <small className="field-range">
                      {formatRange(constraints, languageStrings)}
                    </small>
                  ) : null}
                </label>
              )
            })}
          </div>

          <div className="button-row">
            <button className="primary-button" type="submit" disabled={loading}>
              {loading ? languageStrings.loading : languageStrings.run}
            </button>
            <button
              className="ghost-button"
              type="button"
              onClick={resetForm}
            >
              {languageStrings.reset}
            </button>
          </div>

          {error ? <div className="alert error">{languageStrings.error}: {error}</div> : null}
          {result && !result.success ? <div className="alert error">{result.message || result.error || languageStrings.error}</div> : null}
        </form>

        <div className="card result-card tool-card simulation-result-card">
          <div className="section-head">
            <h2>{languageStrings.response}</h2>
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
                key={`${simulationKey}-${resultVersion}`}
                simulation={simulationKey}
                frames={result.frames || []}
                time={result.time || []}
                series={result.series || []}
                languageStrings={languageStrings}
                playing={playing}
                playbackDelayMs={resultPlaybackDelayMs}
                onTogglePlaying={() => setPlaying((current) => !current)}
                onResetPlayback={() => setPlaying(false)}
                onPlaybackEnd={() => setPlaying(false)}
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

function labelFor(name, languageStrings, simulationKey) {
  const simulationLabels = languageStrings.fieldLabelsBySimulation?.[simulationKey]

  if (simulationLabels?.[name]) {
    return simulationLabels[name]
  }

  return languageStrings.fieldLabels?.[name] || name
}

function hasRange(constraints) {
  return constraints.min !== undefined || constraints.max !== undefined
}

function formatRange(constraints, languageStrings) {
  const min = constraints.min ?? '-'
  const max = constraints.max ?? '-'
  const template = languageStrings.fieldRange || 'Range: {min} - {max}'

  return template.replace('{min}', min).replace('{max}', max)
}

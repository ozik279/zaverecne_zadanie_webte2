import { useMemo, useState } from 'react'
import { requestJson } from '../api'
import { getOrCreateClientToken } from '../token'
import { translations } from '../i18n'

export default function CasConsolePage({ language }) {
  const t = translations[language]
  const clientToken = useMemo(() => getOrCreateClientToken(), [])
  const [command, setCommand] = useState('a=1+1\na+2')
  const [output, setOutput] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const highlighted = highlightCas(command)

  async function execute(event) {
    event.preventDefault()
    setLoading(true)
    setError('')

    try {
      const data = await requestJson('/cas/execute', {
        method: 'POST',
        body: JSON.stringify({ clientToken, command }),
      })

      setOutput([data.stdout, data.stderr].filter(Boolean).join('\n') || JSON.stringify(data, null, 2))
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setLoading(false)
    }
  }

  async function resetSession() {
    setLoading(true)
    setError('')

    try {
      await requestJson('/cas/reset', {
        method: 'POST',
        body: JSON.stringify({ clientToken }),
      })
      setOutput(t.casSessionReset)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <main className="page">
      <section className="hero compact">
        <div>
          <p className="eyebrow">{t.casConsole}</p>
          <h1>{t.casConsoleTitle}</h1>
          <p className="hero-copy">{t.casConsoleHelp}</p>
        </div>
        <div className="hero-chip">
          <span>{t.currentToken}:</span>
          <strong>{clientToken}</strong>
        </div>
      </section>

      <section className="panel-grid console-grid">
        <form className="card form-card" onSubmit={execute}>
          <div className="section-head">
            <h2>{t.casCommand}</h2>
            <p>{t.casCommandHint}</p>
          </div>

          <label className="field syntax-field">
            <span>{t.casTextarea}</span>
            <div className="syntax-editor">
              <pre aria-hidden="true" dangerouslySetInnerHTML={{ __html: highlighted }} />
              <textarea
                spellCheck="false"
                value={command}
                onChange={(event) => setCommand(event.target.value)}
              />
            </div>
          </label>

          <div className="button-row">
            <button className="primary-button" type="submit" disabled={loading}>
              {loading ? t.loading : t.casExecute}
            </button>
            <button className="ghost-button" type="button" onClick={resetSession} disabled={loading}>
              {t.casReset}
            </button>
          </div>

          {error ? <div className="alert error">{t.error}: {error}</div> : null}
        </form>

        <div className="card result-card">
          <div className="section-head">
            <h2>{t.casOutput}</h2>
            <p>{t.casOutputHint}</p>
          </div>
          <pre className="json-block console-output">{output || t.noData}</pre>
        </div>
      </section>
    </main>
  )
}

function highlightCas(value) {
  return value
    .split('\n')
    .map((line) => {
      const trimmed = line.trimStart()

      if (trimmed.startsWith('%') || trimmed.startsWith('#')) {
        return `<span class="syntax-comment">${escapeHtml(line)}</span>`
      }

      return line.replace(/\b(sin|cos|tan|exp|log|sqrt|inv|ss|lsim|lqr|place|plot|ones|size|mat2str)\b|(\d*\.\d+|\d+)(?:e[-+]?\d+)?|([=+\-*/^])/gi, (match, fn, number, operator) => {
        if (fn) {
          return `<span class="syntax-fn">${escapeHtml(match)}</span>`
        }

        if (number) {
          return `<span class="syntax-number">${escapeHtml(match)}</span>`
        }

        if (operator) {
          return `<span class="syntax-op">${escapeHtml(match)}</span>`
        }

        return escapeHtml(match)
      })
    })
    .join('\n')
}

function escapeHtml(value) {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
}

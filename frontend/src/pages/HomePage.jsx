import { Link } from 'react-router-dom'
import { translations } from '../i18n'

export default function HomePage({ language }) {
  const t = translations[language]

  return (
    <main className="page">
      <section className="hero">
        <div>
          <p className="eyebrow">{t.appName}</p>
          <h1>{t.homeTitle}</h1>
          <p className="hero-copy">{t.homeSubtitle}</p>
        </div>
        <div className="hero-actions">
          <Link className="primary-button" to="/pendulum">{t.pendulum}</Link>
          <Link className="ghost-button" to="/ball-beam">{t.ballBeam}</Link>
          <Link className="ghost-button" to="/statistics">{t.statistics}</Link>
          <Link className="ghost-button" to="/cas-console">{t.casConsole}</Link>
        </div>
      </section>

      <section className="card card-grid">
        <article className="feature-card">
          <span>01</span>
          <h2>{t.pendulum}</h2>
          <p>{t.pendulumCardCopy}</p>
        </article>
        <article className="feature-card">
          <span>02</span>
          <h2>{t.ballBeam}</h2>
          <p>{t.ballBeamCardCopy}</p>
        </article>
        <article className="feature-card">
          <span>03</span>
          <h2>{t.statistics}</h2>
          <p>{t.statisticsCardCopy}</p>
        </article>
        <article className="feature-card">
          <span>04</span>
          <h2>{t.casConsole}</h2>
          <p>{t.casConsoleCardCopy}</p>
        </article>
      </section>
    </main>
  )
}

import { Link } from 'react-router-dom'
import { translations } from '../i18n'

export default function HomePage({ language }) {
  const t = translations[language]

  return (
    <main className="page home-page">
      <section className="home-hero home-hero-minimal">
        <div className="home-hero-content">
          <h1>{t.homeTitle}</h1>
          <div className="home-actions">
            <Link className="primary-button" to="/console">{t.homeStartCas}</Link>
            <Link className="ghost-button" to="/pendulum">{t.pendulum}</Link>
            <Link className="ghost-button" to="/ball-beam">{t.ballBeam}</Link>
          </div>
        </div>
      </section>
    </main>
  )
}

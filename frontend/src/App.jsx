import { useEffect, useMemo, useState } from 'react'
import { Link, Navigate, Route, Routes } from 'react-router-dom'
import HomePage from './pages/HomePage'
import PendulumPage from './pages/PendulumPage'
import BallBeamPage from './pages/BallBeamPage'
import StatisticsPage from './pages/StatisticsPage'
import CasConsolePage from './pages/CasConsolePage'
import { translations } from './i18n'

export default function App() {
  const [language, setLanguage] = useState(() => localStorage.getItem('language') || 'sk')
  const t = useMemo(() => translations[language] || translations.sk, [language])

  useEffect(() => {
    localStorage.setItem('language', language)
    document.documentElement.lang = language
  }, [language])

  return (
    <div className="app-shell">
      <header className="topbar">
        <Link className="brand" to="/">
          {t.appName}
        </Link>
        <nav className="nav">
          <Link to="/pendulum">{t.pendulum}</Link>
          <Link to="/ball-beam">{t.ballBeam}</Link>
          <Link to="/statistics">{t.statistics}</Link>
          <Link to="/cas-console">{t.casConsole}</Link>
        </nav>
        <label className="language-switch">
          <span>{t.language}</span>
          <select value={language} onChange={(event) => setLanguage(event.target.value)}>
            <option value="sk">SK</option>
            <option value="en">EN</option>
          </select>
        </label>
      </header>

      <Routes>
        <Route path="/" element={<HomePage language={language} />} />
        <Route path="/pendulum" element={<PendulumPage language={language} />} />
        <Route path="/ball-beam" element={<BallBeamPage language={language} />} />
        <Route path="/statistics" element={<StatisticsPage language={language} />} />
        <Route path="/cas-console" element={<CasConsolePage language={language} />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </div>
  )
}

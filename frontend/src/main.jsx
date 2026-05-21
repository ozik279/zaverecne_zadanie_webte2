import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { HashRouter } from 'react-router-dom'
import './index.css'
import App from './App'

const frontendRoutes = new Set(['/console', '/logs', '/api-docs', '/pendulum', '/ball-beam', '/statistics', '/cas-console'])

if (!window.location.hash && frontendRoutes.has(window.location.pathname)) {
  window.history.replaceState(null, '', `/#${window.location.pathname}${window.location.search}`)
}

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <HashRouter>
      <App />
    </HashRouter>
  </StrictMode>,
)

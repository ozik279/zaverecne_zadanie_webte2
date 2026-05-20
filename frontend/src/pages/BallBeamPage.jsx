import SimulationPage from '../components/SimulationPage'
import { translations } from '../i18n'

export default function BallBeamPage({ language }) {
  const t = translations[language]

  return (
    <SimulationPage
      title={t.ballBeam}
      simulation={t.ballBeamSimulation}
      simulationKey="ball-beam"
      endpoint="/simulations/ball-beam"
      description={t.ballBeamDescription}
      initialForm={{
        reference: '0.25',
        initialPosition: '0',
        initialAngle: '0',
        duration: '5',
        step: '0.01',
        slowdownMs: '0',
      }}
      languageStrings={t}
    />
  )
}

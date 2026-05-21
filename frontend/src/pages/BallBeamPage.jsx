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
      initialForm={{
        reference: '0.25',
        initialBallPosition: '0',
        initialBallVelocity: '0',
        initialBeamAngle: '0',
        initialBeamAngularVelocity: '0',
        duration: '5',
        step: '0.01',
        slowdownMs: '0',
      }}
      fieldConstraints={{
        reference: { min: 0, max: 0.5 },
        initialBallPosition: { min: 0, max: 0.5 },
        initialBallVelocity: { min: -0.1, max: 0.5 },
        initialBeamAngle: { min: -0.2, max: 0.2 },
        initialBeamAngularVelocity: { min: -2, max: 2 },
        duration: { min: 0.1, max: 5, inputStep: 0.1 },
        step: { min: 0.005, max: 0.05, inputStep: 0.001 },
        slowdownMs: { min: 0, max: 1000, inputStep: 1 },
      }}
      languageStrings={t}
    />
  )
}

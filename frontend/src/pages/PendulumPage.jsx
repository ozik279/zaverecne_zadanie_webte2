import SimulationPage from '../components/SimulationPage'
import { translations } from '../i18n'

export default function PendulumPage({ language }) {
  const t = translations[language]

  return (
    <SimulationPage
      title={t.pendulum}
      simulation={t.pendulumSimulation}
      simulationKey="inverted-pendulum"
      endpoint="/simulations/inverted-pendulum"
      initialForm={{
        reference: '0.2',
        initialPosition: '0',
        initialVelocity: '0',
        initialAngle: '0',
        initialAngularVelocity: '0',
        duration: '10',
        step: '0.05',
        slowdownMs: '0',
      }}
      fieldConstraints={{
        reference: { min: -0.5, max: 0.5 },
        initialPosition: { min: -0.5, max: 0.5 },
        initialVelocity: { min: -0.5, max: 0.5 },
        initialAngle: { min: -0.2, max: 0.2 },
        initialAngularVelocity: { min: -1, max: 1 },
        duration: { min: 0.5, max: 10, inputStep: 0.5 },
        step: { min: 0.01, max: 0.1, inputStep: 0.01 },
        slowdownMs: { min: 0, max: 5000, inputStep: 1 },
      }}
      languageStrings={t}
    />
  )
}

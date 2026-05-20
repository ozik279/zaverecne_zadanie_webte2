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
      description={t.pendulumDescription}
      initialForm={{
        reference: '0.2',
        initialPosition: '0',
        initialAngle: '0',
        duration: '10',
        step: '0.05',
        slowdownMs: '0',
      }}
      languageStrings={t}
    />
  )
}

import { useEffect, useMemo, useState } from 'react'

const COLORS = ['#ffb703', '#8ecae6', '#fb8500', '#219ebc']

export default function SimulationViz({ simulation, frames, time, series = [], title, languageStrings, playing, onTogglePlaying, playbackDelayMs = 80 }) {
  const [index, setIndex] = useState(0)

  useEffect(() => {
    setIndex(0)
  }, [frames])

  useEffect(() => {
    if (!playing || frames.length <= 1) {
      return undefined
    }

    const delay = Math.max(40, Number(playbackDelayMs) || 80)
    const timer = window.setInterval(() => {
      setIndex((current) => (current >= frames.length - 1 ? current : current + 1))
    }, delay)

    return () => window.clearInterval(timer)
  }, [frames.length, playbackDelayMs, playing])

  const currentFrame = frames[index] || null
  const availableSeries = useMemo(() => series.filter((item) => Array.isArray(item.values) && item.values.length > 0), [series])

  const svgWidth = 640
  const svgHeight = 240

  return (
    <section className="viz-card">
      <div className="viz-header">
        <div>
          <p className="eyebrow">{title}</p>
          <h2>{simulation}</h2>
        </div>
        <button className="ghost-button" type="button" onClick={onTogglePlaying}>
          {playing ? languageStrings.pause : languageStrings.play}
        </button>
      </div>

      <div className="viz-layout">
        <div className="animation-panel">
          <div className="panel-label">{languageStrings.animation}</div>
          <svg viewBox="0 0 800 300" className="animation-svg" role="img" aria-label={`${simulation} animation`}>
            <rect x="0" y="250" width="800" height="50" fill="#112240" />
            {simulation === 'inverted-pendulum' ? (
              <InvertedPendulumScene frame={currentFrame} />
            ) : (
              <BallBeamScene frame={currentFrame} frames={frames} index={index} />
            )}
          </svg>
          <input
            className="range"
            type="range"
            min="0"
            max={Math.max(0, frames.length - 1)}
            value={Math.min(index, Math.max(0, frames.length - 1))}
            onChange={(event) => setIndex(Number(event.target.value))}
          />
          <div className="range-caption">
            <span>{languageStrings.frame} {index + 1} {languageStrings.of} {Math.max(frames.length, 1)}</span>
            <span>t = {formatNumber(currentFrame?.time ?? time[index] ?? 0)}</span>
          </div>
        </div>

        <div className="graph-panel">
          <div className="panel-label">{languageStrings.graph}</div>
          <svg viewBox={`0 0 ${svgWidth} ${svgHeight}`} className="graph-svg" role="img" aria-label={`${simulation} graph`}>
            <rect x="0" y="0" width={svgWidth} height={svgHeight} rx="18" fill="#0f172a" />
            <line x1="48" y1="200" x2="610" y2="200" stroke="#274060" strokeWidth="2" />
            <line x1="48" y1="20" x2="48" y2="200" stroke="#274060" strokeWidth="2" />
            {availableSeries.map((item, seriesIndex) => {
              const points = item.values.map((value, pointIndex) => {
                const x = 48 + (pointIndex / Math.max(1, item.values.length - 1)) * 562
                const y = scaleSeriesValue(value, item.values, 30, 170, simulation, item.name)
                return `${x},${y}`
              }).join(' ')

              const currentX = 48 + (index / Math.max(1, item.values.length - 1)) * 562
              const currentY = scaleSeriesValue(item.values[index] ?? item.values[item.values.length - 1] ?? 0, item.values, 30, 170, simulation, item.name)

              return (
                <g key={item.name}>
                  <polyline
                    points={points}
                    fill="none"
                    stroke={COLORS[seriesIndex % COLORS.length]}
                    strokeWidth="3"
                    strokeLinejoin="round"
                    strokeLinecap="round"
                  />
                  <circle cx={currentX} cy={currentY} r="5" fill={COLORS[seriesIndex % COLORS.length]} />
                </g>
              )
            })}
          </svg>
          <div className="series-list">
            {availableSeries.map((item, seriesIndex) => (
              <div key={item.name} className="series-item">
                <span className="series-dot" style={{ background: COLORS[seriesIndex % COLORS.length] }} />
                <span>{labelForSeries(item.name, languageStrings)}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  )
}

function InvertedPendulumScene({ frame }) {
  const cartX = clamp(180 + (finiteNumber(frame?.cartX, 0) * 260), 124, 676)
  const rawPendulumTipX = 180 + (finiteNumber(frame?.pendulumTipX, 0) * 260)

  // Inverted pendulum is drawn upwards from the cart. In SVG, smaller Y means higher.
  const pendulumTipY = clamp(180 + (finiteNumber(frame?.pendulumTipY, -0.3) * 200), 64, 238)
  const referenceX = clamp(180 + (finiteNumber(frame?.reference, 0) * 260), 70, 730)
  const pendulumTipX = clamp(rawPendulumTipX, 70, 730)

  return (
    <g>
      <line x1={referenceX} y1="60" x2={referenceX} y2="240" stroke="#fb8500" strokeDasharray="8 8" strokeWidth="3" />
      <line x1="70" y1="220" x2="650" y2="220" stroke="#94a3b8" strokeWidth="4" />
      <rect x={cartX - 58} y="176" width="116" height="46" rx="11" fill="#e2e8f0" />
      <circle cx={cartX - 32} cy="230" r="12" fill="#94a3b8" />
      <circle cx={cartX + 32} cy="230" r="12" fill="#94a3b8" />
      <line x1={cartX} y1="176" x2={pendulumTipX} y2={pendulumTipY} stroke="#ffb703" strokeWidth="10" strokeLinecap="round" />
      <circle cx={pendulumTipX} cy={pendulumTipY} r="18" fill="#fb8500" />
    </g>
  )
}

function BallBeamScene({ frame, frames, index }) {
  const reference = finiteNumber(frame?.reference, 0.25)
  const position = getSmoothedFrameValue(frames, index, getBallPosition, getBallPosition(frame))

  const pivotX = 560
  const pivotY = 145
  const beamLength = 430
  const ballRadius = 20

  // CTMS diagram: the beam is attached on the right side to the lever/gear.
  // Ball coordinate r is drawn from the right side of the beam toward the ball.
  // Therefore r = 0 starts near the right pivot and larger r moves left along the beam.
  const maxCoordinate = Math.max(1.0, reference, ...frames.map(getBallPosition).filter(Number.isFinite))
  const coordinateToPx = (value) => clamp(value / maxCoordinate, 0, 1) * (beamLength - ballRadius * 2 - 12)

  // x(:,3) from the Octave model is the beam angle alpha. It is physically very small,
  // so it is visually amplified but still clamped and smoothed to avoid artificial jumps.
  const rawAngle = getSmoothedFrameValue(frames, index, getRawBeamAngle, getRawBeamAngle(frame))
  const modelAngle = clamp(-rawAngle * 35, -0.16, 0.16)

  const beamDirX = -Math.cos(modelAngle)
  const beamDirY = -Math.sin(modelAngle)
  const beamPerpX = Math.sin(modelAngle)
  const beamPerpY = -Math.cos(modelAngle)

  const ballDistanceFromPivot = coordinateToPx(position) + ballRadius + 8
  const referenceDistanceFromPivot = coordinateToPx(reference) + ballRadius + 8

  const beamEndX = pivotX + beamDirX * beamLength
  const beamEndY = pivotY + beamDirY * beamLength

  const ballCenterX = pivotX + beamDirX * ballDistanceFromPivot + beamPerpX * (ballRadius + 8)
  const ballCenterY = pivotY + beamDirY * ballDistanceFromPivot + beamPerpY * (ballRadius + 8)

  const referenceX = pivotX + beamDirX * referenceDistanceFromPivot
  const referenceY = pivotY + beamDirY * referenceDistanceFromPivot

  const gearCenterX = pivotX
  const gearCenterY = 246

  return (
    <g>
      <line x1="110" y1="248" x2="705" y2="248" stroke="#94a3b8" strokeWidth="4" />

      <circle cx={gearCenterX} cy={gearCenterY} r="58" fill="#8b5e34" stroke="#4b2f17" strokeWidth="6" />
      <circle cx={gearCenterX} cy={gearCenterY} r="14" fill="#d6a57b" stroke="#4b2f17" strokeWidth="3" />
      <line x1={gearCenterX} y1={gearCenterY} x2={gearCenterX + 50} y2={gearCenterY - 25} stroke="#4b2f17" strokeWidth="5" strokeLinecap="round" />
      <circle cx={gearCenterX + 50} cy={gearCenterY - 25} r="8" fill="#c48d4d" />

      <line x1={pivotX} y1={pivotY} x2={gearCenterX} y2={gearCenterY - 58} stroke="#d4af37" strokeWidth="8" strokeLinecap="round" />
      <circle cx={pivotX} cy={pivotY} r="10" fill="#f59e0b" />

      <line x1={beamEndX} y1={beamEndY} x2={pivotX} y2={pivotY} stroke="#cbd5e1" strokeWidth="16" strokeLinecap="round" />
      <line x1={beamEndX} y1={beamEndY + 10} x2={pivotX} y2={pivotY + 10} stroke="#64748b" strokeWidth="5" strokeLinecap="round" />

      <line
        x1={referenceX}
        y1={referenceY - 30}
        x2={referenceX}
        y2={referenceY + 30}
        stroke="#fb8500"
        strokeDasharray="7 7"
        strokeWidth="4"
      />

      <circle
        cx={ballCenterX}
        cy={ballCenterY}
        r={ballRadius}
        fill="#8ecae6"
        stroke="#1d4ed8"
        strokeWidth="4"
      />
      <circle cx={ballCenterX - 6} cy={ballCenterY - 6} r="5" fill="#dbeafe" />

      <line x1="110" y1="248" x2={gearCenterX} y2="248" stroke="#7c8ca6" strokeWidth="2" strokeDasharray="4 8" />
    </g>
  )
}

function getBallPosition(frame) {
  return finiteNumber(
    frame?.ballPosition ??
    frame?.ball_position ??
    frame?.position ??
    frame?.output ??
    frame?.state_1 ??
    frame?.state?.[0],
    0,
  )
}

function getRawBeamAngle(frame) {
  return finiteNumber(
    frame?.beamAngle ??
    frame?.beam_angle ??
    frame?.alpha ??
    frame?.angle ??
    frame?.state_3 ??
    frame?.state?.[2],
    0,
  )
}

function getVisualBeamAngle(frame) {
  return getRawBeamAngle(frame)
}

function getSmoothedFrameValue(frames, index, getter, fallback = 0) {
  if (!Array.isArray(frames) || frames.length === 0) {
    return finiteNumber(fallback, 0)
  }

  const currentIndex = clamp(Math.round(Number(index) || 0), 0, frames.length - 1)
  const weights = [1, 2, 4, 2, 1]
  const offsets = [-2, -1, 0, 1, 2]
  let total = 0
  let weightTotal = 0

  offsets.forEach((offset, i) => {
    const frameIndex = clamp(currentIndex + offset, 0, frames.length - 1)
    const value = finiteNumber(getter(frames[frameIndex]), NaN)

    if (Number.isFinite(value)) {
      total += value * weights[i]
      weightTotal += weights[i]
    }
  })

  return weightTotal > 0 ? total / weightTotal : finiteNumber(fallback, 0)
}

function labelForSeries(name, languageStrings) {
  return languageStrings.stateLabels?.[name] || name
}

function scaleSeriesValue(value, values, top, bottom, simulation, name = '') {
  const numericValues = values.map(Number).filter(Number.isFinite)

  if (simulation === 'ball-beam' && /ball|position|output|y/i.test(name)) {
    const min = 0
    const max = Math.max(0.5, ...numericValues) || 1
    return bottom - ((Number(value) - min) / (max - min || 1)) * (bottom - top)
  }

  return scaleValue(value, numericValues.length ? numericValues : [0], top, bottom)
}

function scaleValue(value, values, top, bottom) {
  const min = Math.min(...values)
  const max = Math.max(...values)
  const range = max - min || 1
  return bottom - ((Number(value) - min) / range) * (bottom - top)
}

function formatNumber(value) {
  return Number(value).toFixed(3)
}

function finiteNumber(value, fallback = 0) {
  const numeric = Number(value)
  return Number.isFinite(numeric) ? numeric : fallback
}

function clamp(value, min, max) {
  return Math.max(min, Math.min(max, value))
}

function radiansToDegrees(value) {
  return value * 180 / Math.PI
}

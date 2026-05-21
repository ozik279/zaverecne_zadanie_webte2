import { useEffect, useMemo, useRef, useState } from 'react'

const COLORS = ['#ffb703', '#8ecae6', '#fb8500', '#219ebc']
const SMOOTH_FRAME_DELAY_MS = 1000 / 60

export default function SimulationViz({ simulation, frames, time, series = [], languageStrings, playing, onTogglePlaying, onResetPlayback, onPlaybackEnd, playbackDelayMs = 0 }) {
  const [framePosition, setFramePosition] = useState(0)
  const framePositionRef = useRef(0)
  const lastTimestampRef = useRef(null)
  const playbackEndRef = useRef(onPlaybackEnd)

  useEffect(() => {
    playbackEndRef.current = onPlaybackEnd
  }, [onPlaybackEnd])

  useEffect(() => {
    if (!playing || frames.length <= 1) {
      lastTimestampRef.current = null
      return undefined
    }

    let animationFrameId = null
    let cancelled = false
    const lastFrameIndex = frames.length - 1
    const frameDelay = resolveFrameDelayMs(playbackDelayMs)

    function tick(timestamp) {
      if (cancelled) {
        return
      }

      if (lastTimestampRef.current === null) {
        lastTimestampRef.current = timestamp
      }

      const elapsedMs = timestamp - lastTimestampRef.current
      lastTimestampRef.current = timestamp
      const nextPosition = Math.min(lastFrameIndex, framePositionRef.current + elapsedMs / frameDelay)

      framePositionRef.current = nextPosition
      setFramePosition(nextPosition)

      if (nextPosition >= lastFrameIndex) {
        lastTimestampRef.current = null
        playbackEndRef.current?.()
        return
      }

      animationFrameId = window.requestAnimationFrame(tick)
    }

    animationFrameId = window.requestAnimationFrame(tick)

    return () => {
      cancelled = true
      lastTimestampRef.current = null
      if (animationFrameId !== null) {
        window.cancelAnimationFrame(animationFrameId)
      }
    }
  }, [frames.length, playbackDelayMs, playing])

  const lastFrameIndex = Math.max(0, frames.length - 1)
  const currentFramePosition = clamp(framePosition, 0, lastFrameIndex)
  const currentFrame = interpolateFrame(frames, currentFramePosition)
  const currentFrameIndex = Math.min(Math.floor(currentFramePosition), lastFrameIndex)
  const currentTime = currentFrame?.time ?? interpolateSeriesValue(time, currentFramePosition, 0)
  const availableSeries = useMemo(() => series.filter((item) => Array.isArray(item.values) && item.values.length > 0), [series])
  const animationMetrics = buildAnimationMetrics(simulation, currentFrame, currentTime, languageStrings)
  const graphMetrics = availableSeries.map((item, seriesIndex) => ({
    color: COLORS[seriesIndex % COLORS.length],
    label: labelForSeries(item.name, languageStrings),
    value: interpolateSeriesValue(item.values, currentFramePosition, item.values[item.values.length - 1] ?? 0),
  }))

  const svgWidth = 640
  const svgHeight = 240

  function updateFramePosition(value) {
    const boundedValue = clamp(Number(value) || 0, 0, lastFrameIndex)
    framePositionRef.current = boundedValue
    setFramePosition(boundedValue)
  }

  function resetPlayback() {
    updateFramePosition(0)
    onResetPlayback?.()
  }

  return (
    <section className="simulation-viz">
      <div className="viz-header">
        <div className="button-row viz-actions">
          <button className="ghost-button" type="button" onClick={resetPlayback}>
            {languageStrings.resetAnimation}
          </button>
          <button className="ghost-button" type="button" onClick={onTogglePlaying}>
            {playing ? languageStrings.pause : languageStrings.play}
          </button>
        </div>
      </div>

      <div className="viz-layout">
        <div className="animation-panel">
          <div className="panel-label">{languageStrings.animation}</div>
          <svg viewBox="0 0 800 300" className="animation-svg" role="img" aria-label={`${simulation} animation`}>
            <rect x="0" y="250" width="800" height="50" fill="#112240" />
            {simulation === 'inverted-pendulum' ? (
              <InvertedPendulumScene frame={currentFrame} />
            ) : (
              <BallBeamScene frame={currentFrame} frames={frames} framePosition={currentFramePosition} />
            )}
          </svg>
          <input
            className="range"
            type="range"
            min="0"
            max={lastFrameIndex}
            step="any"
            value={currentFramePosition}
            onChange={(event) => updateFramePosition(event.target.value)}
          />
          <div className="range-caption">
            <span>{languageStrings.frame} {currentFrameIndex + 1} {languageStrings.of} {Math.max(frames.length, 1)}</span>
            <span>t = {formatNumber(currentTime)}</span>
          </div>
          <InfoPanel title={languageStrings.currentSimulationValues} metrics={animationMetrics} />
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

              const seriesPosition = clamp(currentFramePosition, 0, item.values.length - 1)
              const currentX = 48 + (seriesPosition / Math.max(1, item.values.length - 1)) * 562
              const currentY = scaleSeriesValue(interpolateSeriesValue(item.values, seriesPosition, item.values[item.values.length - 1] ?? 0), item.values, 30, 170, simulation, item.name)

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
          <InfoPanel title={languageStrings.currentGraphValues} metrics={graphMetrics} />
        </div>
      </div>
    </section>
  )
}

function InfoPanel({ title, metrics }) {
  if (!metrics.length) {
    return null
  }

  return (
    <div className="info-panel">
      <div className="info-title">{title}</div>
      <div className="info-grid">
        {metrics.map((metric) => (
          <div key={metric.label} className="info-value">
            <span>
              {metric.color ? <i className="info-dot" style={{ background: metric.color }} /> : null}
              {metric.label}
            </span>
            <strong>{formatNumber(metric.value)}</strong>
          </div>
        ))}
      </div>
    </div>
  )
}

function InvertedPendulumScene({ frame }) {
  const cartX = clamp(180 + (finiteNumber(frame?.cartX, 0) * 260), 124, 676)
  const cartTopY = 170
  const cartBottomY = 222
  const rodLength = 132
  const angle = finiteNumber(frame?.pendulum_angle ?? frame?.angle, 0)

  // The simulated pendulum is 0.3 m long. The drawing uses a larger pixel length
  // so small model angles stay visible in the frontend animation.
  const pendulumTipX = clamp(cartX + rodLength * Math.sin(angle), 70, 730)
  const pendulumTipY = clamp(cartTopY - rodLength * Math.cos(angle), 28, 236)
  const referenceX = clamp(180 + (finiteNumber(frame?.reference, 0) * 260), 70, 730)

  return (
    <g>
      <line x1={referenceX} y1="36" x2={referenceX} y2="240" stroke="#fb8500" strokeDasharray="8 8" strokeWidth="3" />
      <line x1="70" y1="220" x2="650" y2="220" stroke="#94a3b8" strokeWidth="4" />
      <rect x={cartX - 58} y={cartTopY} width="116" height={cartBottomY - cartTopY} rx="11" fill="#e2e8f0" />
      <circle cx={cartX - 32} cy="230" r="12" fill="#94a3b8" />
      <circle cx={cartX + 32} cy="230" r="12" fill="#94a3b8" />
      <line x1={cartX} y1={cartTopY} x2={pendulumTipX} y2={pendulumTipY} stroke="#ffb703" strokeWidth="9" strokeLinecap="round" />
      <circle cx={cartX} cy={cartTopY} r="8" fill="#0f172a" stroke="#ffb703" strokeWidth="4" />
      <circle cx={pendulumTipX} cy={pendulumTipY} r="18" fill="#fb8500" />
    </g>
  )
}

function BallBeamScene({ frame, frames, framePosition }) {
  const reference = finiteNumber(frame?.reference, 0.25)
  const position = interpolateFrameValue(frames, framePosition, getBallPosition, getBallPosition(frame))

  const pivotX = 560
  const pivotY = 145
  const beamLength = 430
  const ballRadius = 20

  // CTMS diagram: the beam is attached on the right side to the lever/gear.
  // Ball coordinate r is drawn from the right side of the beam toward the ball.
  // Therefore r = 0 starts near the right pivot and larger r moves left along the beam.
  const maxCoordinate = Math.max(1.0, reference, ...frames.map(getBallPosition).filter(Number.isFinite))
  const coordinateToPx = (value) => clamp(value / maxCoordinate, 0, 1) * (beamLength - ballRadius * 2 - 12)

  // x(:,3) from the Octave model is the beam angle alpha in radians. Keep the
  // animation in model scale and bound only enough to keep the beam inside the scene.
  const rawAngle = interpolateFrameValue(frames, framePosition, getRawBeamAngle, getRawBeamAngle(frame))
  const modelAngle = clamp(rawAngle, -0.2, 0.2)

  const beamDirX = -Math.cos(modelAngle)
  const beamDirY = -Math.sin(modelAngle)
  const beamPerpX = Math.sin(modelAngle)
  const beamPerpY = -Math.cos(modelAngle)

  const ballDistanceFromPivot = coordinateToPx(position) + ballRadius + 8
  const referenceDistanceFromPivot = coordinateToPx(reference) + ballRadius + 8

  const beamEndX = pivotX + beamDirX * beamLength
  const beamEndY = pivotY + beamDirY * beamLength
  const lowerRailOffset = 12
  const lowerBeamEndX = beamEndX - beamPerpX * lowerRailOffset
  const lowerBeamEndY = beamEndY - beamPerpY * lowerRailOffset
  const lowerPivotX = pivotX - beamPerpX * lowerRailOffset
  const lowerPivotY = pivotY - beamPerpY * lowerRailOffset

  const ballCenterX = pivotX + beamDirX * ballDistanceFromPivot + beamPerpX * (ballRadius + 8)
  const ballCenterY = pivotY + beamDirY * ballDistanceFromPivot + beamPerpY * (ballRadius + 8)

  const referenceX = pivotX + beamDirX * referenceDistanceFromPivot
  const referenceY = pivotY + beamDirY * referenceDistanceFromPivot
  const markerHalfLength = 30
  const referenceStartX = referenceX - beamPerpX * markerHalfLength
  const referenceStartY = referenceY - beamPerpY * markerHalfLength
  const referenceEndX = referenceX + beamPerpX * markerHalfLength
  const referenceEndY = referenceY + beamPerpY * markerHalfLength

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
      <line x1={lowerBeamEndX} y1={lowerBeamEndY} x2={lowerPivotX} y2={lowerPivotY} stroke="#64748b" strokeWidth="5" strokeLinecap="round" />

      <line
        x1={referenceStartX}
        y1={referenceStartY}
        x2={referenceEndX}
        y2={referenceEndY}
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

function labelForSeries(name, languageStrings) {
  return languageStrings.stateLabels?.[name] || name
}

function buildAnimationMetrics(simulation, frame, currentTime, languageStrings) {
  const commonMetrics = [
    { label: languageStrings.simulationTime, value: currentTime },
    { label: languageStrings.targetValue, value: finiteNumber(frame?.reference, NaN) },
  ]

  const stateKeys = simulation === 'inverted-pendulum'
    ? ['cart_position', 'velocity', 'pendulum_angle', 'angular_velocity']
    : ['ball_position', 'state_2', 'beam_angle', 'state_4']

  return [
    ...commonMetrics,
    ...stateKeys.map((key) => ({
      label: labelForSeries(key, languageStrings),
      value: finiteNumber(frame?.[key], NaN),
    })),
  ].filter((metric) => Number.isFinite(metric.value))
}

function resolveFrameDelayMs(value) {
  const numericValue = Number(value)

  if (!Number.isFinite(numericValue) || numericValue <= 0) {
    return SMOOTH_FRAME_DELAY_MS
  }

  return Math.max(8, numericValue)
}

function interpolateFrame(frames, position) {
  if (!Array.isArray(frames) || frames.length === 0) {
    return null
  }

  const lowerIndex = clamp(Math.floor(Number(position) || 0), 0, frames.length - 1)
  const upperIndex = clamp(lowerIndex + 1, 0, frames.length - 1)
  const ratio = clamp((Number(position) || 0) - lowerIndex, 0, 1)

  return interpolateValue(frames[lowerIndex], frames[upperIndex], ratio)
}

function interpolateFrameValue(frames, position, getter, fallback = 0) {
  if (!Array.isArray(frames) || frames.length === 0) {
    return finiteNumber(fallback, 0)
  }

  const lowerIndex = clamp(Math.floor(Number(position) || 0), 0, frames.length - 1)
  const upperIndex = clamp(lowerIndex + 1, 0, frames.length - 1)
  const ratio = clamp((Number(position) || 0) - lowerIndex, 0, 1)
  const lowerValue = finiteNumber(getter(frames[lowerIndex]), fallback)
  const upperValue = finiteNumber(getter(frames[upperIndex]), lowerValue)

  return interpolateNumber(lowerValue, upperValue, ratio)
}

function interpolateSeriesValue(values, position, fallback = 0) {
  if (!Array.isArray(values) || values.length === 0) {
    return finiteNumber(fallback, 0)
  }

  const lowerIndex = clamp(Math.floor(Number(position) || 0), 0, values.length - 1)
  const upperIndex = clamp(lowerIndex + 1, 0, values.length - 1)
  const ratio = clamp((Number(position) || 0) - lowerIndex, 0, 1)
  const lowerValue = finiteNumber(values[lowerIndex], fallback)
  const upperValue = finiteNumber(values[upperIndex], lowerValue)

  return interpolateNumber(lowerValue, upperValue, ratio)
}

function interpolateValue(start, end, ratio) {
  if (Array.isArray(start) && Array.isArray(end)) {
    return start.map((value, index) => interpolateValue(value, end[index], ratio))
  }

  if (isPlainObject(start) && isPlainObject(end)) {
    const keys = new Set([...Object.keys(start), ...Object.keys(end)])
    const result = {}

    keys.forEach((key) => {
      result[key] = interpolateValue(start[key], end[key], ratio)
    })

    return result
  }

  const startNumber = Number(start)
  const endNumber = Number(end)

  if (Number.isFinite(startNumber) && Number.isFinite(endNumber)) {
    return interpolateNumber(startNumber, endNumber, ratio)
  }

  return ratio < 1 ? start : end
}

function interpolateNumber(start, end, ratio) {
  return start + (end - start) * ratio
}

function isPlainObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
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

import type { TripSummary } from '../../lib/api'

const MODE_ICON: Record<string, string> = {
  aereo: '✈️',
  treno: '🚆',
  auto: '🚗',
  nave: '🚢',
}

export default function TripSummaryCard({ trip }: { trip: TripSummary }) {
  return (
    <div className="ml-0 max-w-[80%] rounded-2xl border border-orizon-200 bg-orizon-50 p-4 text-sm shadow-sm">
      <div className="mb-2 flex items-center gap-2 font-semibold text-orizon-800">
        <span className="text-lg">{MODE_ICON[trip.transport_mode] ?? '🌍'}</span>
        <span className="capitalize">{trip.transport_mode}</span>
        <span className="text-orizon-400">·</span>
        <span>
          {trip.origin} → {trip.destination}
        </span>
      </div>
      <div className="grid grid-cols-2 gap-2 text-orizon-900">
        <div>
          <div className="text-xs uppercase tracking-wide text-orizon-500">Peso bagagli</div>
          <div className="font-medium">{trip.weight_kg} kg</div>
        </div>
        <div>
          <div className="text-xs uppercase tracking-wide text-orizon-500">CO2 stimata</div>
          <div className="font-medium">
            {trip.co2_kg !== null ? `${trip.co2_kg.toFixed(1)} kg` : 'n/d'}
          </div>
        </div>
      </div>
    </div>
  )
}

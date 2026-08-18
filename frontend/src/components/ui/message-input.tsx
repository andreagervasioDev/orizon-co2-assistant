import { useState, type FormEvent } from 'react'

interface Props {
  disabled: boolean
  onSend: (message: string) => void
}

export default function MessageInput({ disabled, onSend }: Props) {
  const [value, setValue] = useState('')

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    const trimmed = value.trim()
    if (trimmed === '' || disabled) return
    onSend(trimmed)
    setValue('')
  }

  return (
    <form onSubmit={handleSubmit} className="flex gap-2 border-t border-gray-200 bg-white p-3">
      <input
        type="text"
        value={value}
        onChange={(e) => setValue(e.target.value)}
        placeholder="Descrivi il tuo viaggio, es: 'Vado in treno da Roma a Milano con 15kg di bagaglio'"
        disabled={disabled}
        className="flex-1 rounded-full border border-gray-300 px-4 py-2.5 text-sm outline-none focus:border-orizon-500 focus:ring-1 focus:ring-orizon-500 disabled:opacity-60"
      />
      <button
        type="submit"
        disabled={disabled || value.trim() === ''}
        className="rounded-full bg-orizon-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-orizon-700 disabled:cursor-not-allowed disabled:opacity-50"
      >
        Invia
      </button>
    </form>
  )
}

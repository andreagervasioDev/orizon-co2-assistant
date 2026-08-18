import type { ConversationSummary } from '../lib/api'

interface Props {
  conversations: ConversationSummary[]
  activeId: string | null
  onSelect: (id: string) => void
  onNew: () => void
  onDelete: (id: string) => void
}

export default function ConversationSidebar({ conversations, activeId, onSelect, onNew, onDelete }: Props) {
  return (
    <aside className="flex w-64 shrink-0 flex-col border-r border-gray-200 bg-white">
      <div className="p-3">
        <button
          onClick={onNew}
          className="w-full rounded-lg bg-orizon-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-orizon-700"
        >
          + Nuova conversazione
        </button>
      </div>
      <nav className="flex-1 space-y-1 overflow-y-auto px-2 pb-3">
        {conversations.length === 0 && (
          <p className="px-2 py-4 text-xs text-gray-400">Nessuna conversazione salvata.</p>
        )}
        {conversations.map((conversation) => (
          <div
            key={conversation.id}
            className={`group flex items-center justify-between rounded-lg px-2 py-2 text-sm ${
              conversation.id === activeId
                ? 'bg-orizon-50 text-orizon-800'
                : 'text-gray-600 hover:bg-gray-50'
            }`}
          >
            <button
              onClick={() => onSelect(conversation.id)}
              className="flex-1 truncate text-left"
              title={conversation.title ?? 'Senza titolo'}
            >
              {conversation.title || 'Senza titolo'}
            </button>
            <button
              onClick={() => onDelete(conversation.id)}
              className="ml-1 hidden shrink-0 rounded px-1 text-gray-400 hover:text-red-500 group-hover:block"
              aria-label="Elimina conversazione"
            >
              ✕
            </button>
          </div>
        ))}
      </nav>
    </aside>
  )
}

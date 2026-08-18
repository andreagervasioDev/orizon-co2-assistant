import { useEffect, useRef } from 'react'
import type { ChatMessage, TripSummary } from '../../lib/api'
import ChatMessageBubble from './chat-message'
import TripSummaryCard from './trip-summary-card'
import TypingIndicator from './typing-indicator'

export type DisplayMessage = ChatMessage & { tripSummary?: TripSummary | null }

interface Props {
  messages: DisplayMessage[]
  loading: boolean
}

export default function MessageList({ messages, loading }: Props) {
  const endRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages, loading])

  return (
    <div className="flex-1 space-y-3 overflow-y-auto px-4 py-4">
      {messages.map((message, index) => (
        <div key={index} className="space-y-2">
          <ChatMessageBubble message={message} />
          {message.tripSummary && <TripSummaryCard trip={message.tripSummary} />}
        </div>
      ))}
      {loading && <TypingIndicator />}
      <div ref={endRef} />
    </div>
  )
}

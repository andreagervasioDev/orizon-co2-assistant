import { useCallback, useEffect, useState } from 'react'
import {
  deleteConversation as deleteConversationRequest,
  fetchConversation,
  listConversations,
  sendMessage,
  type ChatMessage,
  type ConversationSummary,
  type TripSummary,
} from '../lib/api'

export type DisplayMessage = ChatMessage & { tripSummary?: TripSummary | null }

const STORAGE_KEY = 'orizon_conversation_id'

const WELCOME_MESSAGE: DisplayMessage = {
  role: 'assistant',
  content:
    "Ciao! Sono l'assistente Orizon per la stima dell'impatto ambientale dei viaggi. " +
    'Raccontami come viaggi: mezzo di trasporto, peso dei bagagli, origine e destinazione, ' +
    'e calcolerò per te una stima delle emissioni di CO2.',
}

export function useOrizonChat() {
  const [conversationId, setConversationId] = useState<string | null>(null)
  const [messages, setMessages] = useState<DisplayMessage[]>([WELCOME_MESSAGE])
  const [conversations, setConversations] = useState<ConversationSummary[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const refreshConversations = useCallback(() => {
    listConversations()
      .then((data) => setConversations(data.conversations))
      .catch(() => {})
  }, [])

  useEffect(() => {
    refreshConversations()

    const stored = localStorage.getItem(STORAGE_KEY)
    if (!stored) return

    fetchConversation(stored)
      .then((data) => {
        if (data.messages.length > 0) {
          setConversationId(data.conversation_id)
          setMessages([WELCOME_MESSAGE, ...data.messages])
        }
      })
      .catch(() => {
        localStorage.removeItem(STORAGE_KEY)
      })
  }, [refreshConversations])

  async function handleSend(text: string) {
    setError(null)
    setMessages((prev) => [...prev, { role: 'user', content: text }])
    setLoading(true)

    try {
      const response = await sendMessage(conversationId, text)
      setConversationId(response.conversation_id)
      localStorage.setItem(STORAGE_KEY, response.conversation_id)
      setMessages((prev) => [
        ...prev,
        { role: 'assistant', content: response.reply, tripSummary: response.trip_summary },
      ])
      refreshConversations()
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Errore imprevisto')
    } finally {
      setLoading(false)
    }
  }

  async function selectConversation(id: string) {
    if (id === conversationId) return
    setError(null)
    try {
      const data = await fetchConversation(id)
      setConversationId(data.conversation_id)
      localStorage.setItem(STORAGE_KEY, data.conversation_id)
      setMessages([WELCOME_MESSAGE, ...data.messages])
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Errore imprevisto')
    }
  }

  function startNewConversation() {
    setConversationId(null)
    setMessages([WELCOME_MESSAGE])
    setError(null)
    localStorage.removeItem(STORAGE_KEY)
  }

  async function removeConversation(id: string) {
    try {
      await deleteConversationRequest(id)
      if (id === conversationId) {
        startNewConversation()
      }
      refreshConversations()
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Errore imprevisto')
    }
  }

  return {
    conversationId,
    messages,
    conversations,
    loading,
    error,
    handleSend,
    selectConversation,
    startNewConversation,
    removeConversation,
  }
}

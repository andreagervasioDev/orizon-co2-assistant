export interface TripSummary {
  transport_mode: string
  weight_kg: number
  origin: string
  destination: string
  co2_kg: number | null
}

export interface ChatMessage {
  role: 'user' | 'assistant'
  content: string
  created_at?: string
}

export interface ChatResponse {
  conversation_id: string
  reply: string
  trip_summary: TripSummary | null
}

export interface ConversationSummary {
  id: string
  title: string | null
  updated_at: string
}

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000'

async function parseJsonOrThrow(response: Response) {
  const data = await response.json().catch(() => null)
  if (!response.ok) {
    const message = data?.error || `Errore HTTP ${response.status}`
    throw new Error(message)
  }
  return data
}

export async function sendMessage(
  conversationId: string | null,
  message: string,
): Promise<ChatResponse> {
  const response = await fetch(`${API_URL}/api/chat`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ conversation_id: conversationId, message }),
  })
  return parseJsonOrThrow(response)
}

export async function fetchConversation(
  conversationId: string,
): Promise<{ conversation_id: string; messages: ChatMessage[] }> {
  const response = await fetch(`${API_URL}/api/conversations/${conversationId}`)
  return parseJsonOrThrow(response)
}

export async function listConversations(): Promise<{ conversations: ConversationSummary[] }> {
  const response = await fetch(`${API_URL}/api/conversations`)
  return parseJsonOrThrow(response)
}

export async function deleteConversation(conversationId: string): Promise<{ ok: true }> {
  const response = await fetch(`${API_URL}/api/conversations/${conversationId}`, {
    method: 'DELETE',
  })
  return parseJsonOrThrow(response)
}

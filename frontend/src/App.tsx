import ConversationSidebar from './components/conversation-sidebar'
import MessageInput from './components/ui/message-input'
import MessageList from './components/ui/message-list'
import { useOrizonChat } from './hooks/use-orizon-chat'

function App() {
  const {
    conversationId,
    messages,
    conversations,
    loading,
    error,
    handleSend,
    selectConversation,
    startNewConversation,
    removeConversation,
  } = useOrizonChat()

  return (
    <div className="flex h-full bg-gray-50">
      <ConversationSidebar
        conversations={conversations}
        activeId={conversationId}
        onSelect={selectConversation}
        onNew={startNewConversation}
        onDelete={removeConversation}
      />

      <div className="flex h-full flex-1 flex-col">
        <header className="border-b border-gray-200 bg-white px-4 py-3 shadow-sm">
          <h1 className="text-lg font-semibold text-orizon-800">Orizon · Impatto CO2 dei viaggi</h1>
          <p className="text-xs text-gray-500">
            Descrivi il tuo viaggio e ricevi una stima delle emissioni di CO2
          </p>
        </header>

        <MessageList messages={messages} loading={loading} />

        {error && (
          <div className="mx-4 mb-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">
            {error}
          </div>
        )}

        <MessageInput disabled={loading} onSend={handleSend} />
      </div>
    </div>
  )
}

export default App

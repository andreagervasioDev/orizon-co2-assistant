# Orizon — Assistente stima impatto CO2 dei viaggi

Assistente conversazionale che stima le emissioni di CO2 di un viaggio a partire da una descrizione in linguaggio naturale (mezzo di trasporto, peso dei bagagli, origine, destinazione).

## Architettura

- **Frontend**: React + TypeScript + Vite + Tailwind CSS (`frontend/`) — interfaccia chat
- **Backend**: PHP puro, nessun framework/Composer (`backend/`), architettura a layer (Controllers/Services/Repositories/Clients) — endpoint `POST /api/chat` che orchestra LLM + tool + persistenza, più `/api/conversations` per lo storico chat
- **LLM**: OpenAI Chat Completions API con tool/function calling
- **Tool esterno**: [EcoFreight](https://ecofreight.co) — API di calcolo emissioni CO2, invocata dal backend come function-calling tool del modello
- **Persistenza**: SQLite (file locale, via PDO)

Flusso di una richiesta:

```
Frontend --POST /api/chat--> Backend --system prompt + storico + tool--> OpenAI
                                  |                                        |
                                  |<--- richiesta tool "calculate_co2_emissions"
                                  |
                                  +--> EcoFreightClient --> API EcoFreight
                                  |<-- risultato emissioni
                                  |
                                  +--tool result--> OpenAI --> risposta finale
                                  |
                                  +--salva tutto in SQLite
Frontend <--reply + trip_summary--
```

## Scelte di design importanti

- **EcoFreight è un'API per il freight (cargo), non per passeggeri.** Viene usata trattando il bagaglio del viaggiatore come "cargo" — è esattamente il modello richiesto ("quanto pesa ciò che viene trasportato"). Il backend applica un `vessel_type` di default per ciascun mezzo (es. `aereo → belly_cargo`, il bagaglio nella stiva di un volo passeggeri), perché sono dettagli tecnici che l'utente/LLM non deve conoscere. Vedi `backend/src/EcoFreightClient.php`.
- **Peso minimo 50kg**: l'API EcoFreight accetta pesi da 50kg a 100.000kg. Se l'utente indica un bagaglio più leggero, il backend usa 50kg come base di calcolo e lo segnala in modo trasparente nella risposta (nessun'invenzione di numeri).
- **Estrazione del valore CO2 per la card riepilogativa** (`ChatService::extractCo2Kg`): non avendo una chiave EcoFreight reale in fase di sviluppo, il nome esatto del campo con il totale delle emissioni nella risposta JSON è stato dedotto dalla documentazione pubblica (non testato con una risposta reale). Se la card mostra "n/d" per la CO2 pur avendo una chiave valida, controllate una risposta reale di `/calculate` e aggiornate l'elenco di chiavi candidate in quel metodo — la risposta testuale dell'assistente resterà comunque corretta perché il modello legge l'intero JSON.
- **Nessuna chiave API inclusa**: dovete procurarvi le vostre chiavi OpenAI ed EcoFreight (vedi sotto).

## Setup locale

### Backend

Richiede PHP 8.1+ con estensione `pdo_sqlite` (inclusa di default nella maggior parte delle installazioni), oppure Docker.

1. Copiate `backend/.env.example` in `backend/.env` e compilate:
   - `OPENAI_API_KEY` — da https://platform.openai.com/api-keys
   - `ECOFREIGHT_API_KEY` — registratevi su https://ecofreight.co (dashboard → API keys, piano free: 1000 richieste/mese)
2. Avvio con PHP nativo:
   ```
   php -S localhost:8000 -t backend/public
   ```
   Oppure con Docker:
   ```
   docker build -t orizon-backend ./backend
   docker run -p 8000:8080 --env-file backend/.env orizon-backend
   ```
3. Verifica: `curl http://localhost:8000/api/health` deve rispondere `{"status":"ok"}`

**Test**: `php backend/tests/run.php` — copre la logica pura (senza I/O) di `ChatService::extractCo2Kg` e `EcoFreightClient::resolveMode`/`normalizeWeight`. Runner minimale scritto a mano, niente PHPUnit/Composer (coerente con la scelta "PHP puro" del progetto).

Il database SQLite (`backend/database/data.sqlite`) viene creato automaticamente al primo avvio.

### Frontend

1. `cd frontend && npm install`
2. Copiate `frontend/.env.example` in `frontend/.env` e impostate `VITE_API_URL` (es. `http://localhost:8000` in locale)
3. `npm run dev` e aprite l'URL mostrato (di solito http://localhost:5173)

## Deploy su piattaforme gratuite

### Backend → Render (Web Service, piano Free)

1. Create un repository Git con questo progetto e collegatelo a Render (New → Web Service → Build and deploy from a Git repository)
2. Root directory: `backend`, Runtime: Docker (Render userà automaticamente `backend/Dockerfile`)
3. Impostate le variabili d'ambiente nel dashboard Render: `OPENAI_API_KEY`, `OPENAI_MODEL`, `ECOFREIGHT_API_KEY`, `ALLOWED_ORIGIN` (impostatela poi all'URL Netlify del frontend, es. `https://orizon.netlify.app`)
4. **Nota**: sul piano free di Render il filesystem è effimero — i dati SQLite si perdono a ogni redeploy/riavvio del servizio. Va bene per una demo; per produzione servirebbe un database gestito esterno (es. Turso, Postgres/MySQL managed).

### Frontend → Netlify

1. New site from Git, root directory `frontend` (usa la configurazione già presente in `frontend/netlify.toml`)
2. Impostate la variabile d'ambiente `VITE_API_URL` all'URL del backend su Render
3. Deploy

## Struttura del progetto

```
backend/
  public/index.php               # front controller: bootstrap, autoloader, CORS, routing
  src/
    Config.php                   # variabili d'ambiente (.env)
    Controllers/                 # ChatController, ConversationsController, HealthController
    Services/ChatService.php     # loop LLM + function calling
    Repositories/ConversationRepository.php  # memoria conversazionale (storico + sidebar)
    Clients/                     # OpenAIClient, EcoFreightClient (chiamate HTTP esterne)
    Prompts/SystemPrompt.php     # system prompt e definizione del tool
    Core/Database.php            # connessione PDO SQLite
    Http/JsonResponse.php        # helper risposta JSON
    Http/RateLimiter.php         # rate limit minimo per IP
  tests/                        # test runner + test della logica pura (vedi sezione Test)
  schema.sql
  Dockerfile
frontend/
  src/components/ui/       # ChatMessageBubble, MessageList, MessageInput, TypingIndicator, TripSummaryCard
  src/components/conversation-sidebar.tsx  # storico chat (ultime 30)
  src/hooks/use-orizon-chat.ts             # stato chat + storico + localStorage
  src/lib/api.ts
  src/App.tsx
```

## API del backend

| Metodo | Endpoint | Body / Parametri | Risposta |
|---|---|---|---|
| `POST` | `/api/chat` | `{"message": "...", "conversation_id": "uuid \| null"}` | `{"conversation_id": "...", "reply": "...", "trip_summary": {...} \| null}` |
| `GET` | `/api/conversations` | — | `{"conversations": [{id, title, updated_at}]}` (ultime 30, per la sidebar) |
| `GET` | `/api/conversations/{id}` | — | `{"conversation_id": "...", "messages": [{role, content, created_at}]}` |
| `PATCH` | `/api/conversations/{id}` | `{"title": "..."}` | `{"ok": true}` |
| `DELETE` | `/api/conversations/{id}` | — | `{"ok": true}` |
| `GET` | `/api/health` | — | `{"status": "ok"}` |

Lo storico è limitato alle **ultime 30 conversazioni**: alla creazione di una nuova chat, le più vecchie oltre il limite vengono eliminate automaticamente (messaggi inclusi).

## Limiti noti

- **Nessuna autenticazione**: `/api/chat` e `/api/conversations` sono pubblici. Chiunque conosca l'URL del backend può usarli — va bene per una demo, non per un'esposizione pubblica prolungata senza controllo dei costi.
- **Rate limit minimo per IP** (`App\Http\RateLimiter`): max 10 richieste/minuto per IP su `/api/chat`, oltre risponde `429`. Protegge dall'abuso banale da un singolo host (script/loop), **non** da un attacker distribuito su più IP — non è un sostituto di un vero sistema di autenticazione/quota se il progetto va oltre la fase demo.
- **CORS (`ALLOWED_ORIGIN`) non è una misura di sicurezza contro l'abuso**: blocca solo le chiamate da JavaScript eseguito in un browser su un'origine diversa. Non impedisce a uno script, `curl` o un bot di chiamare direttamente l'API.
- **SQLite su Render (piano free) è effimero**: si perde ad ogni redeploy/riavvio (vedi sopra).

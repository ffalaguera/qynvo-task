# Qynvo Task — Travel Tips API

Laravel API that takes a traveller's itinerary and returns 3 personalised tips powered by Claude AI.

## How It Works

Send a POST request with destination, dates and activities → the API validates the input, calls Claude, and returns 3 tips as JSON. If the same itinerary is sent again, tips come from cache instead of making another API call.

```
POST /api/travel-tips → Validation → Controller → ClaudeService → Claude API → JSON Response
```

## Tech Stack

- PHP 8.x / Laravel 13
- Anthropic Claude API (claude-sonnet-4-6)
- SQLite
- Laravel Cache (file driver)

## Project Structure

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── TravelTipsController.php    # Handles the request and response
│   └── Requests/
│       └── ItineraryRequest.php        # Input validation
└── Services/
    └── ClaudeService.php               # Claude API logic, prompt, parsing, cache
```

I kept it simple on purpose — three files, each doing one thing. The controller doesn't know how Claude works, the service doesn't know about HTTP responses, and the validation runs before anything else touches the data.

This is similar to how we structured things at DISID using hexagonal architecture: keep layers independent so you can change one without breaking the others.

## Setup

```bash
git clone https://github.com/ffalaguera/qynvo-task.git
cd qynvo-task
composer install
cp .env.example .env
php artisan key:generate
```

Add your Anthropic API key to `.env`:
```
ANTHROPIC_API_KEY=your-key-here
```

Then:
```bash
php artisan migrate
php artisan serve
```

## API Usage

### POST /api/travel-tips

**Request:**
```json
{
    "destination": "Tokyo",
    "start_date": "2026-07-15",
    "end_date": "2026-07-20",
    "activities": ["visit temples", "try sushi", "explore Shibuya"]
}
```

**Success (200):**
```json
{
    "success": true,
    "data": [
        {
            "title": "Beat the Crowds at Senso-ji Temple",
            "description": "July is peak tourist season in Tokyo, so arrive at Senso-ji before 7am..."
        },
        {
            "title": "Navigate Tsukiji Outer Market for the Freshest Sushi",
            "description": "Head to Tsukiji Outer Market early morning around 8-9am..."
        },
        {
            "title": "Time Your Shibuya Crossing Visit Strategically",
            "description": "Plan your Shibuya Crossing visit for evening hours around 8-10pm..."
        }
    ],
    "cached": false
}
```

**Validation Error (422):**
```json
{
    "success": false,
    "message": "Validation failed.",
    "errors": {
        "destination": ["The destination field is required."]
    }
}
```

**Server Error (500):**
```json
{
    "success": false,
    "message": "Failed to generate travel tips.",
    "error": "Claude API returned status: 401"
}
```

## Design Decisions

**Why three separate files instead of putting everything in the controller?**

I wanted each piece to have a clear job. ItineraryRequest only validates — if something's wrong with the input, the request gets rejected before the controller even runs. The controller just connects things together. And ClaudeService handles everything related to Claude: building the prompt, making the HTTP call, parsing the response, and caching.

The practical benefit is that if Qynvo ever switches from Claude to another LLM, you only touch ClaudeService. The rest of the app doesn't care.

**How I handled errors:**

I tried to make sure the API never returns a raw Laravel error page. Every possible failure has a clean JSON response:

- Missing or invalid fields → 422 with specific error messages
- Missing API key → clear exception before wasting an API call
- Claude API down or timeout → 500 with a descriptive message
- Claude returns unexpected format → caught during parsing

During my internship at DISID I learned that clear error messages save a lot of debugging time, so I tried to apply that here.

**Why cache?**

The cache uses an MD5 hash of the full itinerary as the key. Same trip = same hash = same tips without another API call. I set it to 24 hours, which seemed reasonable — travel tips don't go stale that fast, and it saves API quota.

The response includes a `cached` field so you can tell whether tips are fresh or from cache.

**About the prompt:**

I asked Claude to respond strictly in JSON with a specific structure (title + description). This way parsing is predictable. I also told it to give destination-specific tips instead of generic advice like "bring your passport" — that's not useful for anyone.

## What I'd Add Next

- **Rate limiting** to prevent abuse
- **Logging** each API call (destination, response time, success/failure) for monitoring
- **Configurable tip count** — let the client request 1 to 5 tips
- **Multi-language support** — accept a `language` parameter so Claude responds in the traveller's language
- **Database storage** — save tips linked to itineraries for analytics

## How I'd Build the Conversational Chatbot

Right now this is a one-shot endpoint: you send an itinerary, you get tips, done. There's no memory between requests. To turn this into a real chatbot that can answer questions about a specific traveller's trip, here's how I'd think about it:

**The core idea is RAG (Retrieval-Augmented Generation).** Instead of Claude only using its general knowledge, you feed it the traveller's specific data before each response.

**Step 1 — Store the itinerary as a knowledge base.** When an itinerary is created, save everything: flights, hotels, activities, documents. This is the traveller's personal context.

**Step 2 — New chat endpoint.** Something like `POST /api/chat` that takes an `itinerary_id` and a `message`. The traveller asks "What time is my flight back?" and the system knows which itinerary to look at.

**Step 3 — Retrieve relevant context before calling Claude.** Before each API call, query the database for information related to that itinerary. For simple cases a direct database query works. For larger itineraries with lots of documents, a vector database (like pgvector) could find the most relevant pieces based on what the traveller is asking about.

**Step 4 — Build the prompt with context.** Combine the system instructions + retrieved itinerary data + conversation history + the new question. This way Claude can answer things like "Are there vegetarian restaurants near my hotel?" because it knows where the hotel is.

**Step 5 — Keep conversation history.** Store messages in a `conversations` table. Include the last N messages in each prompt so Claude remembers what was discussed. This enables natural follow-ups without repeating context.

**Step 6 — Function calling for live data.** Use Claude's function calling to fetch real-time info when needed — weather API for "Will it rain tomorrow?", maps API for "How far is the beach from my hotel?". The chatbot decides which function to call based on the question.

This would turn the current tip generator into a travel assistant that actually knows your trip and can have a real conversation about it.

## Author

**Fernando Falaguera Blasco**
- GitHub: [github.com/ffalaguera](https://github.com/ffalaguera)
- LinkedIn: [linkedin.com/in/fernando-falaguera](https://linkedin.com/in/fernando-falaguera)

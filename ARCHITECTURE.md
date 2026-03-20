# Architecture: laravel-debugbar

## Purpose
A Laravel debug toolbar powered by PHP DebugBar. Collects and displays runtime data — SQL queries, cache operations, HTTP client calls, queued jobs, events, gate checks, view rendering, session, and more.

## Directory Structure
```
src/
  Laravel_Debugbar.php              # Core class — extends php-debugbar's DebugBar; manages collectors
  Service_Provider.php              # Laravel service provider — wires everything into the container
  Facades/Debugbar.php              # Laravel facade
  DataCollector/
    Query_Collector.php             # SQL queries + EXPLAIN support
    Cache_Collector.php             # Cache reads, writes, hits, misses
    Event_Collector.php             # Laravel events fired during the request
    Gate_Collector.php              # Authorization gate checks
    Http_Client_Collector.php       # Laravel HTTP client outgoing requests
    Route_Collector.php             # Current route, middleware, parameters
    Request_Collector.php           # Full HTTP request data
    Session_Collector.php           # Session contents
    View_Collector.php              # Rendered views and their data
    Logs_Collector.php              # Laravel log entries
    Multi_Auth_Collector.php        # Multi-guard authentication state
    Livewire_Collector.php / Inertia_Collector.php / Pennant_Collector.php
  CollectorProviders/
    *_Collector_Provider.php        # Per-collector registration and boot logic (20+ providers)
  Middleware/
    Debugbar_Enabled.php            # Injects the DebugBar JS/CSS and data into HTML responses
  Controllers/
    Asset_Controller.php            # Serves DebugBar JS/CSS assets
    Open_Handler_Controller.php     # Ajax: fetch stored request data
    Queries_Controller.php          # Ajax: EXPLAIN a stored query
    Cache_Controller.php            # Ajax: cache management
  Support/
    Explain.php                     # SQL EXPLAIN query runner
    Clockwork/                      # Clockwork compatibility layer
    Octane/Reset_Debugbar.php       # Resets state between Octane requests
  Twig/Extension/                   # Twig stopwatch, debug, dump extensions
```

## Key Design Decisions
- **Collector provider pattern** — each data source (queries, cache, etc.) is encapsulated in a dedicated `*_Collector_Provider` that registers the collector and attaches Laravel event listeners. This makes individual collectors optional and testable.
- **Response injection middleware** — `Debugbar_Enabled` detects HTML responses and injects the DebugBar toolbar JS snippet before `</body>`, transparently adding the toolbar without route changes.
- **Server-side storage** — request data is serialized to storage (filesystem by default) and fetched via Ajax by the toolbar's Open Handler, enabling data persistence across redirects.
- **Octane support** — `Reset_Debugbar` hooks into Octane's request lifecycle to clear collector state between persistent-process requests.

## Extension Points
- Implement a custom php-debugbar `DataCollector` and register it via `Debugbar::addCollector()`.
- Create a custom `Collector_Provider` to encapsulate registration and event binding for a new collector.
- Use the `Debugbar` facade or `debug()` helper to add custom messages: `Debugbar::info('data')`.

## Dependency Flow
```
HTTP Request
  └─ Debugbar_Enabled middleware (on response)
       └─ Laravel_Debugbar::collect()
            └─ [each DataCollector]::collect() → data
       └─ DebugBar::render() → JS/CSS snippet injected into HTML response
```

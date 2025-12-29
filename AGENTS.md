# PROJECT KNOWLEDGE BASE

**Generated:** 2025-12-28  
**Status:** Greenfield (pre-implementation)  
**Repo:** https://github.com/goldenpathdigital/laravel-claude

## OVERVIEW

Laravel wrapper for official Anthropic PHP SDK. First Laravel package with MCP connector client support. Wraps `anthropic-ai/sdk` with Laravel facades, config, service provider, and Claude-specific optimizations.

## STRUCTURE

```
laravel-claude-sdk/
├── laravel-claude-sdk-spec.md   # Full technical specification (READ FIRST)
├── .notes/                      # System directory (notes MCP server)
└── AGENTS.md                    # This file
```

**Target structure (to be created):**
```
src/
├── ClaudeServiceProvider.php    # Service provider
├── Facades/Claude.php           # Main facade
├── ClaudeManager.php            # Core manager class
├── Conversation/                # Fluent conversation builder
├── Tools/                       # Tool definition system
├── MCP/                         # MCP connector integration
├── Events/                      # Stream events
├── Jobs/                        # Queue integration
└── Testing/                     # Fake & assertions
config/
└── claude.php                   # Package config
tests/
├── Feature/
└── Unit/
```

## WHERE TO LOOK

| Task | Location | Notes |
|------|----------|-------|
| Understand requirements | `laravel-claude-sdk-spec.md` | Complete spec with API examples |
| Check existing ecosystem | Spec "Market Landscape" section | Competitor analysis |
| MCP connector details | Spec "MCP Connector Deep Dive" | Key differentiator |
| Implementation phases | Spec "Implementation Roadmap" | 7-week plan |

## SPEC HIGHLIGHTS

### Key Dependencies

```json
{
    "require": {
        "php": "^8.1",
        "anthropic-ai/sdk": "^0.4",
        "illuminate/support": "^10.0|^11.0|^12.0"
    }
}
```

### Core API Design

```php
// Direct SDK pass-through
Claude::messages()->create([...]);

// Fluent conversation
Claude::conversation()
    ->model('claude-sonnet-4-5-20250929')
    ->system('...')
    ->user('...')
    ->send();

// MCP connector (DIFFERENTIATOR)
Claude::conversation()
    ->mcp([McpServer::url($url)->name('zapier')])
    ->user('...')
    ->send();
```

### Beta Features to Support

- `mcp-client-2025-11-20` - MCP connector API
- Extended thinking with budget tokens
- Prompt caching with `cache_control`
- Structured outputs with JSON schema

## CONVENTIONS

- **Thin wrapper** - Don't reimplement HTTP; wrap official SDK
- **Laravel-native** - Facades, config, service provider, events, queues
- **Claude-optimized** - Not a generic multi-provider abstraction
- **MCP-first** - First-class MCP connector support

## ANTI-PATTERNS (THIS PROJECT)

- **Own HTTP client** - Use `anthropic-ai/sdk`, never roll your own
- **Multi-provider abstraction** - This is Claude-specific; Prism exists for multi-provider
- **MCP server building** - This is an MCP CLIENT; `laravel/mcp` handles servers

## COMMANDS

```bash
# Initialize package (when ready)
composer init --name="goldenpathdigital/laravel-claude"

# Install official SDK
composer require anthropic-ai/sdk

# Dev dependencies
composer require --dev orchestra/testbench pestphp/pest
```

## DEVELOPMENT CONTEXT

- **Author:** Aaron (Cimarron Lumber Company)
- **PHP:** 8.3.6 available
- **Laravel:** 12.x target (10+ support)
- **MCP expertise:** Author has production MCP servers (The Librarian, The Astronomer, The Ferryman)

## NOTES

- Official `anthropic-ai/sdk` is in beta (v0.4)
- MCP connector header: `mcp-client-2025-11-20`
- No existing Laravel package wraps official SDK with MCP support
- Competitor `mozex/anthropic-laravel` uses own HTTP client (not official SDK)

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-12-29

### Added

- **Architecture Improvements**
  - `PayloadBuilder` class - Single source of truth for API payload construction
  - `ToolExecutor` class - Tool execution with validation, timeouts, and logging
  - `StreamHandler` class - Streaming orchestration with injectable event dispatcher

- **Tool Enhancements**
  - `Tool::validator()` for custom input validation callbacks
  - `Tool::timeout()` for per-tool execution time limits
  - Required parameter validation during tool execution

- **New Exceptions**
  - `ToolExecutionException` with tool name and input context
  - `ValidationException` with field and value context  
  - `ApiException` with API error type
  - `RateLimitException` with retry-after seconds

- **Testing**
  - `FakeScope` for scoped test isolation (prevents test pollution)
  - `Claude::fakeScoped()` method for automatic cleanup
  - 38 new unit tests (175 → 213 total)

- **Dependency Injection**
  - `withLogger()` for custom PSR-3 logger injection
  - `withEventDispatcher()` for custom event dispatcher injection
  - `isValidModel(strict: bool)` for configurable model validation

### Fixed

- **Security**: MCP tokens now redacted from `var_dump()` and logs via `__debugInfo()`

### Changed

- `ConversationBuilder` reduced from 682 to 483 lines (-29%) through extraction
- `ProcessConversation` job now uses `PayloadBuilder` (eliminates payload duplication)

## [1.0.2] - 2025-12-29

### Added

- **Cost Estimation**
  - `Claude::estimateCost()` for calculating API costs based on token usage
  - `Claude::getPricingForModel()` for retrieving model-specific pricing
  - `TokenCost` value object with formatted output and detailed breakdowns
  - Configurable pricing in `config/claude.php`

- **Configuration Options**
  - `base_url` for custom API endpoints (proxies, enterprise deployments)
  - `auth_token` for OAuth/bearer token authentication

- **Parameter Validation**
  - `temperature()` validates range 0.0-1.0
  - `topP()` validates range 0.0-1.0
  - `topK()` validates minimum of 1
  - `maxTokens()` validates minimum of 1
  - `maxSteps()` validates minimum of 1
  - `extendedThinking()` validates budget minimum of 1024 tokens

### Fixed

- MCP connector now uses correct `mcp-client-2025-11-20` API format with `mcp_toolset` entries
- Potential uninitialized variable in `ConversationBuilder::send()` tool loop
- `CachedContent::cache()` now returns immutable cloned instance
- `FakeResponse::withToolUse()` now correctly creates `ToolUseBlock` content
- `clearFake()` properly resets auto-reset flag across test suites

## [1.0.1] - 2025-12-29

### Fixed

- CI workflow and Laravel 10 compatibility

## [1.0.0] - 2025-12-29

### Added

- **Core Features**
  - Laravel wrapper for official Anthropic PHP SDK (`anthropic-ai/sdk`)
  - `Claude` facade with direct SDK access (`Claude::messages()`, `Claude::models()`, etc.)
  - Fluent `ConversationBuilder` for chainable message composition
  - Support for Laravel 10, 11, and 12

- **Full API Coverage**
  - Messages API with streaming support
  - Models API (list, retrieve)
  - Message Batches API (create, list, retrieve, results, cancel, delete)
  - Files API (list, retrieve metadata, delete)
  - Token Counting API

- **Conversation Builder**
  - Multi-turn conversations with automatic history management
  - Image support (base64, URL)
  - PDF document analysis
  - Advanced parameters (temperature, topK, topP, stopSequences, metadata, serviceTier)

- **Tool System**
  - Fluent tool definition with `Tool::make()`
  - Automatic tool execution loop with `maxSteps()`
  - Parameter validation with types, enums, and required flags

- **MCP Connector**
  - First Laravel package with MCP client support
  - `McpServer` class for inline server configuration
  - Config-based server definitions
  - Tool filtering with `allowTools()`

- **Advanced Features**
  - Extended thinking with budget tokens
  - Prompt caching with `CachedContent` value object
  - Structured outputs with JSON schema validation
  - Real-time streaming with callback support and Laravel events

- **Queue Integration**
  - `ProcessConversation` job for background processing
  - `ConversationCallback` interface for result handling
  - Automatic retries with configurable backoff

- **Testing Utilities**
  - `Claude::fake()` for mocking responses
  - `FakeResponse` class for building mock responses
  - Assertion helpers: `assertSent()`, `assertNothingSent()`, `assertSentCount()`
  - Fake services for Models, Batches, and Files APIs

- **Laravel Integration**
  - Service provider with auto-discovery
  - Publishable configuration file
  - Stream events (`StreamChunk`, `StreamComplete`)

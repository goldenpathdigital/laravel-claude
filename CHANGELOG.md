# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

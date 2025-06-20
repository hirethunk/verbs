# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Verbs is a Laravel package that provides event sourcing capabilities. It focuses on developer experience, following
Laravel conventions, and minimizing boilerplate.

## Code Rules

- Never use `private` or `readonly` keywords
- Never use strict types
- Values are `snake_case`
- Anything callable is `camelCase` (even if it's a variable)
- Paths are `kebab-case` (URLs, files, etc)
- Only apply docblocks where they provide useful IDE/static analysis value

## Testing

The project uses Pest PHP for testing. Key testing patterns:

```php
// Use Verbs::fake() to prevent database writes during tests
Verbs::fake();

// Use Verbs::commitImmediately() for integration tests
Verbs::commitImmediately();

// Create test states using factories
CustomerState::factory()->id($id)->create();
```

## High-Level Architecture

### Core Concepts

1. **Events**: Immutable records of what happened in the system
    - Located in `src/Events/`
    - Must extend `Verbs\Event`
    - Can implement `boot()`, `authorize()`, `validate()`, `apply()`, and `handle()` methods

2. **States**: Aggregate event data over time
    - Located in `src/States/`
    - Must extend `Verbs\State`
    - Use `#[StateId]` attribute to specify which event property contains the state ID

3. **Storage**: Three-table structure
    - `verb_events`: All events with metadata
    - `verb_snapshots`: State snapshots for performance
    - `verb_state_events`: Event-to-state mappings

### Key Directories

- `src/`: Main package source code
    - `Attributes/`: PHP 8 attributes for configuration
    - `Commands/`: Artisan commands
    - `Contracts/`: Interfaces
    - `Events/`: Base event classes and utilities
    - `Facades/`: Laravel facades
    - `Models/`: Eloquent models for storage
    - `States/`: Base state classes
    - `Support/`: Utilities and helpers
- `tests/`: Pest tests organized by feature
- `examples/`: Complete example implementations (Bank, Cart, etc.)

### Important Patterns

1. **Event Lifecycle**: boot -> authorize → validate → apply → handle
2. **Attribute Usage**: `#[StateId]`, `#[AppliesToState]`, `#[AppliesToSingletonState]`
3. **Serialization**: Custom normalizers in `src/Support/Normalization/`
4. **Replay Safety**: Use `#[Once]` annotations and `Verbs::unlessReplaying()` for side effects

## Development Guidelines

- Follow Laravel package conventions
- Use Pest for all new tests
- Run `composer format` before committing
- Ensure compatibility with PHP 8.1+ and Laravel 10.x, 11.x, 12.x
- Test against SQLite, MySQL, and PostgreSQL when modifying storage logic

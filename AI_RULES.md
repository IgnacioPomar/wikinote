# AI Rules for WikiNote

## Scope
- Apply these rules to any code, schema, installer, and template change in this repository.

## Data Model
- All identifier fields (`id*`, `*Id`, `*Uuid`) must use UUID.
- In table JSON definitions, use `"type": "uuid"` (length can remain 36 for compatibility metadata).
- Do not introduce new auto-increment IDs.

## Installer and Seeds
- Any inserted primary key must be generated in app code with `UUIDv7::generateStd()`.
- Keep installer messages concise and in the existing style.

## Schema Evolution
- Prefer backward-safe migrations.
- If a type change is required for existing tables, document explicit SQL migration steps.

## Code Style
- Keep changes minimal and localized.
- Preserve current naming/style conventions unless a refactor is explicitly requested.
- Avoid adding new dependencies without request.

## Validation
- After schema edits, verify references in related JSON files and insert statements.
- Do not remove safety checks unless explicitly requested.
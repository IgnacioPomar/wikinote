# Authentication & Authorization Specification

## 1. Purpose

This document defines the authentication and authorization model for the PHP backend.

The system provides:

- Stateless authentication using JWT access tokens
- Password-based login
- WebAuthn authentication (passkeys / security keys)
- Authorization via claims embedded in the token

The design prioritizes scalability, security, and future extensibility.

---

## 2. Authentication Architecture

Authentication verifies user identity using one of the following methods:

- password authentication
- WebAuthn challenge–response authentication

After successful authentication, the server issues a JWT access token.

Authorization decisions are performed using token claims.

---

## 3. Data Model Requirements

### 3.1 users

Minimum fields:

- `uuid` (primary identifier)
- `username` (unique)
- `passwordHash`
- `isAdmin` (boolean)
- `isActive` (boolean)
- `tokenVersion` (integer, default `0`)
- `createdAt`
- `updatedAt`

### 3.2 groups

The system must derive user groups at token issuance time.

**Recommended structure**

- `groups(id, name)`
- `userGroups(userUuid, groupId)`

Alternative:

- `users.groupsJson`

The token must contain:

```
groups: string[]
```

### 3.3 webAuthnCredentials

- `id`
- `userUuid`
- `credentialId` (base64url, unique)
- `publicKey`
- `signCount`
- `transports` (optional)
- `aaguid` (optional)
- `createdAt`
- `lastUsedAt`

---

## 4. JWT Access Token

### 4.1 Token Properties

- Type: access token
- Lifetime: **15 minutes**
- Stateless signature validation
- One lightweight database lookup is required to validate `ver` vs `users.tokenVersion`

### 4.2 Signing Algorithm

Recommended:

- RS256

Alternative:

- HS256 (single-service deployments)

---

### 4.3 Claims

#### Standard claims

| Claim | Description |
|--------|------------|
| `iss` | token issuer |
| `aud` | intended audience |
| `iat` | issued at |
| `exp` | expiration timestamp |
| `jti` | unique token identifier |

> The `jti` claim enables future token revocation.

#### Custom claims

| Claim | Type | Description |
|--------|------|------------|
| `userUuid` | string | user identifier |
| `username` | string | username |
| `groups` | string[] | group membership |
| `isAdmin` | boolean | administrative privileges |
| `ver` | integer | user token version, must match `users.tokenVersion` |

---

### 4.4 Example Payload

```json
{
  "iss": "https://api.example.com",
  "aud": "example-api",
  "iat": 1760000000,
  "exp": 1760000900,
  "jti": "5f8c2e7a-9c2e-4c35-8d2b-8a6a5a8a1234",
  "userUuid": "a1b2c3d4-....",
  "username": "ignacio",
  "groups": ["finance", "ops"],
  "isAdmin": false,
  "ver": 3
}
```

---

## 5. Token Lifetime & Session Strategy

### Current strategy

- Access tokens expire after **60 minutes**
- No refresh tokens yet
- Users re-authenticate after expiration

This minimizes stale authorization data while keeping the system stateless.

### Authorization freshness

Changes to:

- group membership
- administrative privileges
- account activation

may take up to **60 minutes** to propagate.

This is acceptable for the current phase.

---

## 6. Future Token Revocation & Redis Invalidation

The system includes the `jti` claim to support future revocation.

### Planned Redis denylist

Revoked tokens can be stored in Redis:

```
revokedTokens:{jti} -> expiresAt
```

Validation flow:

1. validate JWT signature and expiration
2. check Redis for `jti`
3. reject token if present

### User-wide invalidation (mandatory): token versioning

`tokenVersion` is required in `users` and `ver` is required in JWT.

Validation flow must include:

1. load `users.tokenVersion` for `userUuid` in token
2. compare token `ver` with database `tokenVersion`
3. reject token if values differ

Revocation events must increment `users.tokenVersion`:

- password change
- account disable/enable transitions
- explicit "logout all sessions"

This instantly invalidates all previously issued access tokens for that user.

---

## 7. Authorization Model

Protected resources require a valid JWT.

### Access rules

- `isAdmin == true` grants administrative privileges
- `groups` defines authorization scopes

Typical patterns:

- admin-only operations
- group-restricted resources
- step-up authentication for sensitive actions

---

## 8. Token Validation

For each request:

1. extract bearer token
2. verify signature
3. validate `exp`, `iss`, `aud`
4. extract claims
5. compare `ver` with `users.tokenVersion`
6. (future) check Redis denylist

One database lookup is required to validate `tokenVersion`.

---

## 9. Security Requirements

### Password security

- use argon2id (preferred) or bcrypt
- apply rate limiting
- use constant-time comparisons

### WebAuthn security

- enforce HTTPS
- validate origin and RP ID
- verify and update signCount

### JWT handling

- keep tokens short-lived
- never log tokens
- store securely on the client

### Transport security

- TLS required
- enforce security headers at gateway/web server

---

## 10. Evolution Path

The architecture supports future enhancements:

- refresh tokens
- Redis-based token revocation
- immediate permission invalidation
- Federation authentication
- expanded multi-factor authentication

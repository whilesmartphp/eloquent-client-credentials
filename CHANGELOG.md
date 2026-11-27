# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-11-27

### Added
- Authorization code grant with PKCE (S256 only), dynamic client registration, rotating refresh tokens and discovery metadata, so users can authorize apps such as MCP clients. Off by default.
- Users approve on the host's consent page through the consent routes, and can list and disconnect the apps they authorized
- `oauth` guard that authenticates the subject behind an authorization code access token
- `AuthorizationClaimsResolver` for adding host claims, such as a workspace, to access tokens
- `client-credentials:oauth-keys` command to create the signing key pair

### Changed
- Client secrets are optional, so public clients can register without one
- A client with recorded grant types can only use those grants. Existing clients have none recorded and keep working with the client credentials grant and middleware.

## [0.2.0] - 2026-11-17

### Added
- Clients have an optional `type` and an `ofType` scope, so one table can hold several kinds of credentials
- Clients record `last_used_at` when they are issued or refresh a token, or pass a package middleware, and `markUsed()` records it from your own authentication

## [0.1.0] - 2026-06-12

### Added
- `HasClientCredentials` trait for adding client credentials to any Eloquent model
- Default `Client` model with sluggable support and polymorphic ownership
- `AccessToken` model for OAuth2 bearer token authentication
- `RefreshToken` model with token rotation support
- OAuth2 client credentials grant flow via `TokenController`
- Token revocation endpoint
- Three authentication middlewares:
  - `AuthenticateBearerToken` - Bearer token authentication with scope support
  - `AuthenticateBasicAuth` - HTTP Basic authentication
  - `AuthenticateClient` - Header-based authentication (X-Client-ID, X-Client-Secret)
- Hook system with `HasMiddlewareHooks` trait and `MiddlewareHookInterface`
- Configurable token lifetimes and refresh token support
- Optional route registration
- `client-credentials.uuids` config option (env `CLIENT_CREDENTIALS_UUIDS`, default `true`) to choose UUID or auto-incrementing integer primary keys for clients, access tokens, and refresh tokens, via the `HasConfigurableIds` trait
- Full test suite

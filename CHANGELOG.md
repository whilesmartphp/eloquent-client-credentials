# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

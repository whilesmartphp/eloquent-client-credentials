<?php

namespace Whilesmart\EloquentClientCredentials\Documentation;

use OpenApi\Attributes as OA;

/**
 * OpenAPI Documentation for Eloquent Client Credentials Package
 *
 * This class contains all the API documentation that can be published
 * to your application for OpenAPI spec generation without needing
 * to publish the actual controllers.
 */
#[OA\Tag(name: 'OAuth', description: 'OAuth2 token endpoints')]
#[OA\Tag(name: 'Clients', description: 'Client management endpoints')]
class ClientCredentialsOpenApiDocs
{
    // ==================== OAuth Endpoints ====================

    #[OA\Post(
        path: '/oauth/token',
        summary: 'Issue access token',
        description: 'Issues an access token using client credentials or refresh token grant.',
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['grant_type'],
                properties: [
                    new OA\Property(property: 'grant_type', type: 'string', enum: ['client_credentials', 'refresh_token']),
                    new OA\Property(property: 'client_id', type: 'string', format: 'uuid', description: 'Required for client_credentials grant'),
                    new OA\Property(property: 'client_secret', type: 'string', description: 'Required for client_credentials grant'),
                    new OA\Property(property: 'refresh_token', type: 'string', description: 'Required for refresh_token grant'),
                    new OA\Property(property: 'scope', type: 'string', description: 'Space-separated scopes (optional)'),
                ]
            )
        ),
        tags: ['OAuth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token issued successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'access_token', type: 'string'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                        new OA\Property(property: 'scope', type: 'string'),
                        new OA\Property(property: 'refresh_token', type: 'string', description: 'Only when refresh tokens enabled'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Refresh tokens disabled'),
            new OA\Response(response: 401, description: 'Invalid credentials or refresh token'),
            new OA\Response(response: 403, description: 'Client revoked'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function issueToken() {}

    #[OA\Post(
        path: '/oauth/revoke',
        summary: 'Revoke access token',
        description: 'Revokes the provided access token and its associated refresh token.',
        security: [['bearerAuth' => []]],
        tags: ['OAuth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token revoked successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Missing or invalid token'),
        ]
    )]
    public function revokeToken() {}

    // ==================== Client Management Endpoints ====================

    #[OA\Get(
        path: '/clients',
        summary: 'List clients',
        description: 'Returns a paginated list of clients owned by the authenticated user.',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Clients retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer'),
                                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Client')),
                                new OA\Property(property: 'per_page', type: 'integer'),
                                new OA\Property(property: 'total', type: 'integer'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function listClients() {}

    #[OA\Post(
        path: '/clients',
        summary: 'Create client',
        description: 'Creates a new client. The secret is only returned once.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'description', type: 'string'),
                ]
            )
        ),
        tags: ['Clients'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Client created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'client', ref: '#/components/schemas/Client'),
                                new OA\Property(property: 'secret', type: 'string', description: 'Plain text secret - only shown once'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function createClient() {}

    #[OA\Get(
        path: '/clients/{slug}',
        summary: 'Get client',
        description: 'Returns a single client by slug.',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Client retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Client'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Client not found'),
        ]
    )]
    public function getClient() {}

    #[OA\Put(
        path: '/clients/{slug}',
        summary: 'Update client',
        description: 'Updates an existing client.',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'description', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Client updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Client'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Client revoked'),
            new OA\Response(response: 404, description: 'Client not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateClient() {}

    #[OA\Delete(
        path: '/clients/{slug}',
        summary: 'Delete client',
        description: 'Deletes a client and all associated tokens.',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Client deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Client not found'),
        ]
    )]
    public function deleteClient() {}

    #[OA\Post(
        path: '/clients/{slug}/regenerate-secret',
        summary: 'Regenerate client secret',
        description: 'Generates a new secret for the client. The new secret is only returned once.',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Secret regenerated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'client', ref: '#/components/schemas/Client'),
                                new OA\Property(property: 'secret', type: 'string', description: 'New plain text secret - only shown once'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Client not found'),
        ]
    )]
    public function regenerateSecret() {}
}

// ==================== Schema Definitions ====================

#[OA\Schema(
    schema: 'Client',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'slug', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'revoked', type: 'boolean'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class ClientSchema {}

#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    description: 'OAuth2 access token'
)]
class BearerAuthScheme {}

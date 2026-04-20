# OpenAPI/Swagger Documentation Guide

## Overview

This guide shows how to document your API endpoints using OpenAPI 3.0 annotations with L5-Swagger.

## Basic Endpoint Documentation

### Simple GET Endpoint

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Get all clients
     *
     * @OA\Get(
     *     path="/api/clients",
     *     summary="Get all clients",
     *     description="Retrieve a paginated list of all clients",
     *     operationId="getClients",
     *     tags={"Clients"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Client")
     *             ),
     *             @OA\Property(
     *                 property="links",
     *                 type="object"
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $clients = Client::paginate($request->get('per_page', 15));
        return response()->json($clients);
    }
}
```

### POST Endpoint with Request Body

```php
/**
 * Create a new client
 *
 * @OA\Post(
 *     path="/api/clients",
 *     summary="Create a new client",
 *     description="Create a new client in the system",
 *     operationId="createClient",
 *     tags={"Clients"},
 *     security={{"sanctum":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Client data",
 *         @OA\JsonContent(
 *             required={"name", "email"},
 *             @OA\Property(
 *                 property="name",
 *                 type="string",
 *                 example="John Doe"
 *             ),
 *             @OA\Property(
 *                 property="email",
 *                 type="string",
 *                 format="email",
 *                 example="john@example.com"
 *             ),
 *             @OA\Property(
 *                 property="phone",
 *                 type="string",
 *                 example="+1234567890"
 *             ),
 *             @OA\Property(
 *                 property="address",
 *                 type="string",
 *                 example="123 Main St"
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Client created successfully",
 *         @OA\JsonContent(ref="#/components/schemas/Client")
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error"
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthorized"
 *     )
 * )
 */
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:clients',
        'phone' => 'nullable|string',
        'address' => 'nullable|string',
    ]);

    $client = Client::create($validated);
    return response()->json($client, 201);
}
```

## Schema Definition

Define reusable schemas for your models:

```php
/**
 * @OA\Schema(
 *     schema="Client",
 *     title="Client",
 *     description="Client model",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         format="int64",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         example="John Doe"
 *     ),
 *     @OA\Property(
 *         property="email",
 *         type="string",
 *         format="email",
 *         example="john@example.com"
 *     ),
 *     @OA\Property(
 *         property="phone",
 *         type="string",
 *         example="+1234567890"
 *     ),
 *     @OA\Property(
 *         property="address",
 *         type="string",
 *         example="123 Main St"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time"
 *     )
 * )
 */
class Client extends Model
{
    // ...
}
```

## Authentication

Document protected endpoints with security requirements:

```php
/**
 * @OA\Get(
 *     path="/api/clients/{id}",
 *     summary="Get a specific client",
 *     tags={"Clients"},
 *     security={{"sanctum":{}}},  // This is your security scheme
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Client found",
 *         @OA\JsonContent(ref="#/components/schemas/Client")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Client not found"
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     )
 * )
 */
public function show(Client $client)
{
    return response()->json($client);
}
```

## Error Responses

Define common error schemas:

```php
/**
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="The given data was invalid."
 *     ),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         @OA\Property(
 *             property="email",
 *             type="array",
 *             @OA\Items(type="string", example="The email has already been taken.")
 *         )
 *     )
 * )
 */

/**
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(
 *         property="error",
 *         type="string",
 *         example="Unauthorized"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="Invalid credentials"
 *     )
 * )
 */
```

## Generate Documentation

After documenting your endpoints:

```bash
# Generate Swagger/OpenAPI documentation
php artisan l5-swagger:generate

# Or with Docker
docker-compose exec app php artisan l5-swagger:generate
```

Then view at: `http://localhost:8000/api/documentation`

## Best Practices

1. **Use descriptive summaries and descriptions**
   - Summary: Brief one-liner
   - Description: Detailed explanation

2. **Document all parameters and request bodies**
   - Include examples
   - Specify required vs optional
   - Use correct data types

3. **Document all response codes**
   - 200: Success
   - 201: Created
   - 400: Bad Request
   - 401: Unauthorized
   - 404: Not Found
   - 422: Validation Error
   - 500: Server Error

4. **Use schemas for reusability**
   - Define models once
   - Reference them with `ref="#/components/schemas/ModelName"`

5. **Group related endpoints with tags**
   - Use consistent tag names
   - Example: `tags={"Clients"}` or `tags={"Invoices"}`

6. **Include authentication requirements**
   - `security={{"sanctum":{}}}`
   - Or `security={}` for public endpoints

## Useful Links

- [OpenAPI 3.0 Specification](https://spec.openapis.org/oas/v3.0.3)
- [L5-Swagger Documentation](https://github.com/DarkaOnline/L5-Swagger)
- [Swagger Editor](https://editor.swagger.io/) - Test your annotations

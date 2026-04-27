<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="BSERP API Documentation",
 *      description="Complete Business Server ERP API with Swagger/OpenAPI documentation",
 *      @OA\Contact(
 *          email="contact@bserp.com"
 *      ),
 *      @OA\License(
 *          name="MIT",
 *          url="https://opensource.org/licenses/MIT"
 *      )
 * )
 *
 * @OA\Server(
 *      url=L5_SWAGGER_CONST_HOST,
 *      description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *      type="apiKey",
 *      description="Login with username and password to get the authentication token",
 *      name="Authorization",
 *      in="header",
 *      scheme="bearer",
 *      bearerFormat="JWT",
 *      securityScheme="sanctum",
 * )
 */
abstract class Controller
{
    /**
     * @OA\Get(
     *      path="/api/ping",
     *      operationId="ping",
     *      tags={"System"},
     *      summary="Check system status",
     *      description="Returns system status",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="string", example="ok")
     *          )
     *      )
     * )
     */
    public function ping()
    {
        return response()->json(['status' => 'ok']);
    }
}


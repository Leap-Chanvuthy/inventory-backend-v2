<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;


/**
 * @OA\Info(
 *      title="Inventory Management System API Documentation (Version 2)",
 *      version="1.0.0",
 *      description="API documentation for the Inventory Management System built with Laravel and documented using OpenAPI (Swagger). This documentation provides details about the available endpoints, request/response formats, authentication methods, and other relevant information for developers integrating with the IMS API.",
 * ),
 * @OA\SecurityScheme(
 *      securityScheme="Bearer",
 *      type="http",
 *      scheme="bearer",
 *      bearerFormat="JWT"
 * )
 */


class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}

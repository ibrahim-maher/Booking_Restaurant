<?php

namespace App;

/**
 * @OA\Info(
 *     title="Restaurant Booking API",
 *     version="1.0.0",
 *     description="API Documentation for Restaurant Booking System"
 * )
 * 
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class SwaggerAnnotations {}
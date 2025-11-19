<?php

namespace App\Helpers;

class ResponseHelper
{
    /**
     * Success response
     */
    public static function success($data = [], $message = 'Success', $code = 200)
    {
        return response()->json([
            'status' => true,
            'message' => $message, 
            'data' => $data,
        ], $code);
    }

    /**
     * Error response
     */
    public static function error($message = 'Something went wrong', $code = 500, $errors = [])
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    /**
     * Validation error response
     */
    public static function validation($errors, $message = 'Validation failed')
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }
}

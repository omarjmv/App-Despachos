<?php

namespace App\Exceptions;

use Exception;

class InvalidStateTransitionException extends Exception
{
    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\OpenApi\OpenApiPdf;
use App\Services\OpenApi\OpenApiSpec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class OpenApiController extends Controller
{
    public function json(OpenApiSpec $spec): JsonResponse
    {
        return response()->json($spec->build());
    }

    public function pdf(OpenApiSpec $spec, OpenApiPdf $pdf): Response
    {
        return response($pdf->build($spec->build()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="webte2-cas-api.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }
}

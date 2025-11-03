<?php

namespace Sayed\Payment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sayed\Payment\Factory\WebhookFactory;
use Exception;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            $payload = $request->getContent();
            $headers = $request->headers->all();
            
            $flatHeaders = [];
            foreach ($headers as $key => $value) {
                $flatHeaders[$key] = is_array($value) ? $value[0] : $value;
            }

            $handler = WebhookFactory::createAdapter($flatHeaders);
            $result = $handler->process($payload, $flatHeaders);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}

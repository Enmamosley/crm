<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentControl;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function status(Request $request)
    {
        $channel = $request->input('channel', 'general');
        $isPaused = AgentControl::isAgentPaused($channel);

        return response()->json([
            'success' => true,
            'data' => [
                'channel' => $channel,
                'is_paused' => $isPaused,
                'status' => $isPaused ? 'paused' : 'active',
            ],
        ]);
    }
}

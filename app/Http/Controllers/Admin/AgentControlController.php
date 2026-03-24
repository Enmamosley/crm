<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentControl;
use Illuminate\Http\Request;

class AgentControlController extends Controller
{
    public function index(Request $request)
    {
        $channel = $request->input('channel', 'general');
        $isPaused = AgentControl::isAgentPaused($channel);
        $history = AgentControl::with('user')->latest()->paginate(20);

        return view('admin.agent.index', compact('isPaused', 'history', 'channel'));
    }

    public function pause(Request $request)
    {
        $request->validate(['reason' => 'nullable|string|max:255']);

        AgentControl::pauseAgent(
            $request->input('channel', 'general'),
            $request->reason,
            auth()->id()
        );

        return redirect()->route('admin.agent.index')
            ->with('success', 'Agente pausado.');
    }

    public function reactivate(Request $request)
    {
        $request->validate(['reason' => 'nullable|string|max:255']);

        AgentControl::reactivateAgent(
            $request->input('channel', 'general'),
            $request->reason,
            auth()->id()
        );

        return redirect()->route('admin.agent.index')
            ->with('success', 'Agente reactivado.');
    }
}

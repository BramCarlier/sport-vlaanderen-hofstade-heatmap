<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        return view('heatmap');
    }

    public function list(): JsonResponse
    {
        return response()->json(
            Event::query()
                ->latest()
                ->get(['id', 'name', 'latitude', 'longitude', 'weight', 'notes', 'created_at'])
        );
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'weight' => ['nullable', 'integer', 'min:1', 'max:10'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $event = Event::create([
            ...$validated,
            'weight' => $validated['weight'] ?? 1,
        ]);

        if ($request->expectsJson()) {
            return response()->json($event, 201);
        }

        return redirect()->route('heatmap.index');
    }

    public function destroy(Event $event): JsonResponse|RedirectResponse
    {
        $event->delete();

        if (request()->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()->route('heatmap.index');
    }
}

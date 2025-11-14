<?php

namespace App\Http\Controllers;

use App\Models\Background;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminController extends Controller
{
    //
    public function index(): JsonResponse
    {
        // Optional caching for 60 seconds to reduce DB hits
        $result = Cache::remember('backgrounds_payload', 60, function () {
            // Select only needed columns and order by type then name
            $rows = Background::select('type', 'name', 'background')
                ->orderBy('type')
                ->orderBy('name')
                ->get();

            // group by 'type' and map to desired shape
            $grouped = $rows->groupBy('type')->map(function ($items) {
                return $items->map(function ($item) {
                    return [
                        'name'     => $item->name,
                        'url'      => $item->background,
                        'selected' => false,
                    ];
                })->values()->all();
            })->toArray();

            // Ensure all types exist (so front-end can expect keys)
            $types = ['live', 'image', 'gradient', 'solid'];
            $payload = [];
            foreach ($types as $t) {
                $payload[$t] = $grouped[$t] ?? [];
            }

            return $payload;
        });

        return response()->json($result);
    }

    public function notifications(Request $request)
    {
        // Fetch only the most recent notification
        $latest = Notification::orderByDesc('created_at')
            ->first(['id', 'title', 'description', 'admin_name', 'created_at']);

        if (!$latest) {
            // No notifications found — return empty
            return response()->json([
                'success' => true,
                'count' => 0,
                'notifications' => [],
            ], 200);
        }

        // Format and return single notification
        return response()->json([
            'success' => true,
            'count' => 1,
            'notifications' => [[
                'id' => $latest->id,
                'title' => $latest->title,
                'description' => $latest->description,
                'admin_name' => 'URL Manager Team',
                'time_ago' => $latest->created_at->diffForHumans(),
                'created_at' => $latest->created_at->toDateTimeString(),
            ]],
        ], 200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\UserTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class UserTagController extends Controller
{
    // Maximum tags per user
    protected int $maxTags = 10;

    /**
     * GET /api/user/tags
     * Return all tags for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // if no authenticated user (guest)
        if (!$user) {
            return response()->json([
                'success' => true,
                'data' => [], // no tags for guests
            ]);
        }

        // include icon field for logged-in users
        $tags = $user->tags()
            ->orderBy('created_at', 'desc')
            ->get(['id', 'tag', 'icon', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $tags,
        ]);
    }

    /**
     * POST /api/user/tags
     * Add one or multiple tags.
     * Accepts: { "tag": "react" } or { "tags": ["react","node"] }
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // validation: allow icon as string but limit to 191 chars (safe for utf8mb4)
        $data = $request->validate([
            'tag'  => 'required|string|max:100',
            'icon' => 'nullable|string|max:191',
        ]);

        $tagValue = trim($data['tag']);
        if ($tagValue === '') {
            return response()->json(['success' => false, 'message' => 'Tag cannot be empty.'], 422);
        }

        // Optionally normalize Unicode form if ext-intl not available, you can skip
        // if (!empty($tagValue) && class_exists('Normalizer')) {
        //     $tagValue = \Normalizer::normalize($tagValue, \Normalizer::FORM_C);
        // }

        // sanitize icon: keep first few characters (safer than accepting huge strings)
        $iconRaw = $data['icon'] ?? null;
        if ($iconRaw) {
            // prefer grapheme_substr if available for proper emoji clusters:
            if (function_exists('grapheme_substr')) {
                $icon = grapheme_substr($iconRaw, 0, 2); // keep up to 2 grapheme clusters
            } else {
                // fallback: mb_substr - not perfect for combined emoji but workable
                $icon = mb_substr($iconRaw, 0, 4);
            }
        } else {
            $icon = '😊';
        }

        // enforce tag count + create inside transaction and handle duplicate-key race
        try {
            return DB::transaction(function () use ($user, $tagValue, $icon) {
                $currentCount = $user->tags()->count();

                if ($currentCount >= $this->maxTags) {
                    return response()->json([
                        'success' => false,
                        'message' => "You already have {$this->maxTags} tags.",
                        'current_count' => $currentCount,
                    ], 422);
                }

                // case-insensitive duplicate check (works in most setups)
                $exists = $user->tags()
                    ->whereRaw('LOWER(tag) = ?', [mb_strtolower($tagValue)])
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tag already exists.',
                    ], 422);
                }

                $created = UserTag::create([
                    'user_id' => $user->id,
                    'tag'     => $tagValue,
                    'icon'    => $icon,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Tag added.',
                    'data' => $created,
                ], 201);
            });
        } catch (QueryException $ex) {
            // 23000 is duplicate key SQLSTATE; exact message/code depends on DB
            // best-effort: respond as duplicate to the user
            $sqlState = $ex->errorInfo[0] ?? null;
            if ($sqlState === '23000') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tag already exists (race condition).',
                ], 422);
            }

            // otherwise rethrow or return generic error
            \Log::error('Tag store error', ['error' => $ex->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not add tag.'], 500);
        }
    }

    /**
     * PUT /api/user/tags/{id}
     * Edit a tag (only the owner can edit).
     * Accepts: { "tag": "new-value", "icon": "😊" }
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        // allow updating icon as well (nullable)
        $data = $request->validate([
            'tag'  => 'required|string|max:100',
            'icon' => 'nullable|string|max:191',
        ]);

        $newTag = trim($data['tag']);
        $newIcon = array_key_exists('icon', $data) ? $data['icon'] : null;

        return DB::transaction(function () use ($user, $id, $newTag, $newIcon) {
            $userTag = UserTag::where('id', $id)->where('user_id', $user->id)->first();

            if (! $userTag) {
                return response()->json(['success' => false, 'message' => 'Tag not found.'], 404);
            }

            // if same value (case-insensitive) no-op for tag, but still allow icon update
            if (mb_strtolower($userTag->tag) === mb_strtolower($newTag)) {
                // only update icon if provided and different
                if (!is_null($newIcon) && $newIcon !== $userTag->icon) {
                    $userTag->icon = $newIcon;
                    $userTag->save();
                    return response()->json(['success' => true, 'message' => 'Icon updated.', 'data' => $userTag]);
                }

                return response()->json(['success' => true, 'message' => 'No changes made.', 'data' => $userTag]);
            }

            // Prevent duplicate value for this user
            $exists = UserTag::where('user_id', $user->id)
                ->whereRaw('LOWER(tag) = ?', [mb_strtolower($newTag)])
                ->exists();

            if ($exists) {
                return response()->json(['success' => false, 'message' => 'Tag with this name already exists for the user.'], 422);
            }

            $userTag->tag = $newTag;

            // update icon if provided (nullable)
            if (!is_null($newIcon)) {
                $userTag->icon = $newIcon;
            }

            $userTag->save();

            return response()->json(['success' => true, 'message' => 'Tag updated.', 'data' => $userTag]);
        });
    }

    /**
     * DELETE /api/user/tags/{id}
     * Delete a tag owned by the authenticated user.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $userTag = UserTag::where('id', $id)->where('user_id', $user->id)->first();

        if (! $userTag) {
            return response()->json(['success' => false, 'message' => 'Tag not found.'], 404);
        }

        $userTag->delete();

        return response()->json(['success' => true, 'message' => 'Tag deleted.']);
    }
}

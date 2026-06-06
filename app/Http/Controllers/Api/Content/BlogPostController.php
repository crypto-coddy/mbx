<?php

namespace App\Http\Controllers\Api\Content;

use App\Http\Controllers\Api\ApiController;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogPostController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $posts = BlogPost::query()
            ->published()
            ->orderByDesc('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20);

        return $this->success($posts);
    }

    public function show(string $slug): JsonResponse
    {
        $post = BlogPost::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->success($post);
    }
}

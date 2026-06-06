<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Tests\TestCase;

class BlogPostApiTest extends TestCase
{

    public function test_published_blog_posts_are_listed(): void
    {
        $slug = 'market-update-'.uniqid();

        BlogPost::create([
            'title' => 'Market update',
            'slug' => $slug,
            'body' => '<p>Hello</p>',
            'is_published' => true,
            'published_at' => now()->subHour(),
        ]);

        BlogPost::create([
            'title' => 'Draft',
            'slug' => 'draft-'.uniqid(),
            'body' => '<p>Hidden</p>',
            'is_published' => false,
        ]);

        $response = $this->getJson('/api/v1/blog');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $slugs = collect($response->json('data.data'))->pluck('slug');
        $this->assertTrue($slugs->contains($slug));
        $this->assertFalse($slugs->contains(fn (string $value) => str_starts_with($value, 'draft-')));
    }
}

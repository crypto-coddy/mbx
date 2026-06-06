<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Database\Seeders\BlogPostSeeder;
use Tests\TestCase;

class BlogPostSeederTest extends TestCase
{

    public function test_blog_post_seeder_creates_published_articles(): void
    {
        $this->seed(BlogPostSeeder::class);

        $this->assertGreaterThanOrEqual(5, BlogPost::count());
        $this->assertGreaterThanOrEqual(5, BlogPost::published()->count());

        $this->getJson('/api/v1/blog')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        ['id', 'title', 'slug', 'excerpt', 'published_at'],
                    ],
                ],
            ]);
    }
}

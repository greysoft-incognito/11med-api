<?php

namespace Database\Seeders;

use App\Models\Document;
use Illuminate\Database\Seeder;

/**
 * The app's and the partner portals' terms and privacy policy, from `resources/documents`. A
 * document that already exists is left as the team wrote it, so this is safe to run again.
 */
class DocumentSeeder extends Seeder
{
    public const DOCUMENTS = [
        Document::TERMS => 'Terms of service',
        Document::PRIVACY => 'Privacy policy',
        Document::PARTNER_TERMS => 'Terms of service',
        Document::PARTNER_PRIVACY => 'Privacy policy',
    ];

    public function run(): void
    {
        $sort = 0;

        foreach (self::DOCUMENTS as $slug => $title) {
            $sort++;

            if (Document::query()->where('slug', $slug)->exists()) {
                continue;
            }

            Document::create([
                'slug' => $slug,
                'title' => $title,
                'body' => trim(file_get_contents(resource_path("documents/{$slug}.md"))),
                'sort' => $sort,
            ]);
        }
    }
}

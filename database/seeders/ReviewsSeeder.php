<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReviewsSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::first();
        if (!$org) {
            return;
        }

        $authors = ['Иван П.', 'Мария С.', 'Пётр К.', 'Аноним', 'Алексей', 'Ольга', 'Сергей', 'Дмитрий'];
        $texts = [
            'Отличное место, всё понравилось!',
            'Хорошее заведение, рекомендую.',
            'Были проездом, зашли — приятно удивлены.',
            'Обслуживание на высоте, вернёмся ещё.',
            'Немного шумно, но в целом ок.',
            'Цены средние, качество отличное.',
            'Персонал приветливый, атмосфера уютная.',
            'Кухня на уровне, спасибо!',
        ];

        for ($i = 1; $i <= 120; $i++) {
            Review::updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'external_id'     => 'seed-' . $i,
                ],
                [
                    'author'       => $authors[array_rand($authors)],
                    'published_at' => Carbon::now()->subDays(rand(1, 365))->subMinutes(rand(0, 1440)),
                    'text'         => $texts[array_rand($texts)],
                    'rating'       => rand(3, 5),
                ]
            );
        }
    }
}
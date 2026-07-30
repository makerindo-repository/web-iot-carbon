<?php

namespace Database\Seeders;

use App\Models\AboutCard;
use Illuminate\Database\Seeder;

class AboutCardSeeder extends Seeder
{
    public function run(): void
    {
        $cards = [
            // Feature cards
            [
                'type' => 'feature',
                'title' => 'Pemantauan Presisi',
                'subtitle' => null,
                'description' => 'Pengumpulan data lingkungan secara real-time memastikan Anda selalu mengetahui kondisi lahan kapan saja dan di mana saja.',
                'sort_order' => 1,
            ],
            [
                'type' => 'feature',
                'title' => 'Analisis Kecerdasan Buatan',
                'subtitle' => null,
                'description' => 'AI kami mempelajari pola klimatologi untuk memberikan rekomendasi pemupukan dan peringatan dini cuaca ekstrem.',
                'sort_order' => 2,
            ],
            [
                'type' => 'feature',
                'title' => 'Skalabilitas Tinggi',
                'subtitle' => null,
                'description' => 'Sistem arsitektur yang dirancang untuk mendukung manajemen lahan luas mulai dari perkebunan kecil hingga skala industri.',
                'sort_order' => 3,
            ],
            // Team cards
            [
                'type' => 'team',
                'title' => 'Tim AgriSense',
                'subtitle' => 'Peneliti',
                'description' => 'Pilar utama di balik riset dan pengembangan AgriSense.',
                'image_url' => '/user.png',
                'sort_order' => 1,
            ],
        ];

        foreach ($cards as $card) {
            AboutCard::firstOrCreate(
                ['type' => $card['type'], 'title' => $card['title']],
                array_merge($card, ['is_active' => true])
            );
        }
    }
}

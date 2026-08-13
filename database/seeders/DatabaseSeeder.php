<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed default banners if empty
        if (\App\Models\Banner::count() === 0) {
            \App\Models\Banner::create([
                'title' => 'MEGA SALE MAINAN EDUKASI',
                'subtitle' => 'Diskon hingga 50% untuk koleksi pilihan edisi terbatas!',
                'image' => 'https://images.unsplash.com/photo-1587654780291-39c9404d746b?w=1200&q=80',
                'link' => '/products?category=2',
                'order' => 1,
                'is_active' => true,
            ]);

            \App\Models\Banner::create([
                'title' => 'NEW ARRIVALS: GUNDAM & RC',
                'subtitle' => 'Koleksi model kit Jepang dan mobil balap off-road terlengkap.',
                'image' => 'https://images.unsplash.com/photo-1612036782180-6f0b6cd846fe?w=1200&q=80',
                'link' => '/products',
                'order' => 2,
                'is_active' => true,
            ]);
        }

        // Seed default settings
        \App\Models\Setting::updateOrCreate(
            ['key' => 'store_name'],
            ['value' => 'OMEGA TOYS']
        );
        \App\Models\Setting::updateOrCreate(
            ['key' => 'store_description'],
            ['value' => 'Katalog Mainan Edukasi & Koleksi Terbaik']
        );
        \App\Models\Setting::updateOrCreate(
            ['key' => 'whatsapp_number'],
            ['value' => '6281234567890']
        );
        \App\Models\Setting::updateOrCreate(
            ['key' => 'contact_email'],
            ['value' => 'hello@omegatoys.com']
        );
    }
}

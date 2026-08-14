<?php

namespace Database\Seeders;

use App\Services\ShopeeImporter;
use Illuminate\Database\Seeder;

class ShopeeInitialCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds for 0meg4t0y5 initial catalog.
     */
    public function run(): void
    {
        $products = [
            // 1. Kendaraan Mainan
            [
                'name' => 'Mobil Remote Control RC Rock Crawler 4WD Monster Offroad Alloy',
                'price' => 145000,
                'weight' => 850,
                'description' => "Mobil RC Rock Crawler 4WD dengan bodi alloy metal kokoh. Dilengkapi suspensi per independen di setiap roda, ban karet berulir tebal anti selip, dan baterai rechargeable USB.\n\n100% Realpict jepretan sendiri dari toko OMEGA TOYS.\n- Frekuensi 2.4GHz (Bisa main bareng tanpa bentrok sinyal)\n- Skala: 1:18\n- Waktu main: 20-30 menit\n- Jangkauan remote: hingga 35 meter",
                'images' => [
                    'https://down-id.img.susercontent.com/file/id-11134207-7ra0m-mccr8z9r5qe102',
                    'https://images.unsplash.com/photo-1594787318286-3d835c1d207f?w=800&q=80',
                    'https://images.unsplash.com/photo-1581235720704-06d3acfcb36f?w=800&q=80',
                ],
                'tier_variations' => [
                    [
                        'name' => 'Warna',
                        'options' => ['Hitam Metalik', 'Merah Api', 'Biru Elektrik', 'Hijau Army'],
                    ]
                ],
            ],
            [
                'name' => 'Mainan Truk Konstruksi Excavator Beko Remote Control Diecast',
                'price' => 189000,
                'weight' => 950,
                'description' => "Mainan edukasi alat berat Excavator RC dengan kerukan alloy kuat. Lengan pengeruk dan kabin bisa berputar seperti aslinya. Dilengkapi efek suara mesin dan lampu LED sorot.\n\n100% Realpict produk OMEGA TOYS.\n- Dilengkapi baterai charger & kabel USB\n- Bahan ABS tebal ramah anak & bersertifikat SNI",
                'images' => [
                    'https://images.unsplash.com/photo-1596461404969-9ae70f2830c1?w=800&q=80',
                    'https://down-id.img.susercontent.com/file/id-11134207-7ra0m-mccr8z9r5qe102',
                ],
                'tier_variations' => [
                    [
                        'name' => 'Tipe Alat',
                        'options' => ['Excavator Keruk', 'Dump Truck Pasir', 'Crane Derek'],
                    ]
                ],
            ],
            [
                'name' => 'Mainan Bus Telolet Basuri V3 Lampu LED Disko Musik & Bump Action',
                'price' => 68000,
                'weight' => 450,
                'description' => "Bus mainan viral dengan suara klakson Telolet Basuri dan lagu anak. Dilengkapi lampu 3D disko gemerlap warna-warni dan fitur bump-and-go (otomatis belok jika menabrak rintangan).\n\n100% Realpict foto toko OMEGA TOYS.\n- Ukuran: 28 x 8 x 10 cm\n- Menggunakan 3 baterai AA",
                'images' => [
                    'https://images.unsplash.com/photo-1581235720704-06d3acfcb36f?w=800&q=80',
                    'https://down-id.img.susercontent.com/file/id-11134207-7ra0m-mccr8z9r5qe102',
                ],
                'tier_variations' => [
                    [
                        'name' => 'Motif Bus',
                        'options' => ['Tunggal Jaya', 'Kids Panda', 'Mooneyes Kuning', 'Pariwisata Merah'],
                    ]
                ],
            ],
            [
                'name' => 'Set Kereta Api Elektrik Klasik Track Rel Panjang Rail King',
                'price' => 79000,
                'weight' => 550,
                'description' => "Set miniatur kereta api uap klasik lengkap dengan lokomotif, 3 gerbong kargo penumpang, dan jalur rel melingkar berdiameter 104 cm. Lampu lokomotif menyala dan mengeluarkan suara kereta realistis.\n\n100% Realpict OMEGA TOYS.",
                'images' => [
                    'https://images.unsplash.com/photo-1563245372-f21724e3856d?w=800&q=80',
                ],
            ],

            // 2. Boneka & Mainan Boneka
            [
                'name' => 'Boneka Teddy Bear Jumbo Lembut Premium 80cm Bulu Rasfur Halus',
                'price' => 125000,
                'weight' => 1200,
                'description' => "Boneka beruang Teddy Bear ukuran besar 80cm menggunakan bahan bulu Rasfur premium yang sangat lembut dan tidak mudah rontok. Isi 100% dakron murni empuk tanpa campuran sisa kain.\n\n100% Realpict foto asli toko OMEGA TOYS. Jahitan rapi, ber-SNI, dan aman untuk balita.",
                'images' => [
                    'https://down-id.img.susercontent.com/file/id-11134207-7ra0i-mb7oyh237g9wcc',
                    'https://images.unsplash.com/photo-1559454403-b8fb88521f11?w=800&q=80',
                ],
                'tier_variations' => [
                    [
                        'name' => 'Warna',
                        'options' => ['Coklat Karamel', 'Krem Moka', 'Pink Pastel', 'Abu-Abu'],
                    ]
                ],
            ],
            [
                'name' => 'Boneka Plushie Karakter Boba Cuddle Pillow Lucu Viral',
                'price' => 45000,
                'weight' => 350,
                'description' => "Bantal boneka empuk bentuk minuman Boba Milk Tea lucu dengan bordir ekspresi menggemaskan. Cocok untuk teman tidur, kado ulang tahun, atau dekorasi kamar.\n\n100% Realpict jepretan toko OMEGA TOYS.",
                'images' => [
                    'https://images.unsplash.com/photo-1582845512747-e42001c95638?w=800&q=80',
                    'https://down-id.img.susercontent.com/file/id-11134207-7ra0i-mb7oyh237g9wcc',
                ],
                'tier_variations' => [
                    [
                        'name' => 'Rasa / Warna',
                        'options' => ['Brown Sugar (Coklat)', 'Strawberry (Pink)', 'Matcha (Hijau)', 'Taro (Ungu)'],
                    ]
                ],
            ],
            [
                'name' => 'Set Boneka Princess Barbie Fashion Dress Koper Aksesoris 12 Inci',
                'price' => 89000,
                'weight' => 500,
                'description' => "Set boneka putri Barbie dengan gaun pesta mewah, dilengkapi koper pakaian, sepatu ganti, mahkota, sisir, dan tas mini.\n\n100% Realpict toko OMEGA TOYS. Sendi tangan dan kaki lentur bisa digerakkan.",
                'images' => [
                    'https://images.unsplash.com/photo-1581235720704-06d3acfcb36f?w=800&q=80',
                ],
            ],

            // 3. Mainan Edukatif
            [
                'name' => 'Magnetic Building Blocks 3D Geometri Balok Magnet Mainan Edukasi 64 Pcs',
                'price' => 119000,
                'weight' => 700,
                'description' => "Mainan edukatif balok magnet 3D konstruksi untuk melatih imajinasi spasial, kreativitas, dan logika anak. Magnet kuat memudahkan menyusun kastil, mobil, bola roda, dan bentuk geometri lainnya.\n\n100% Realpict jepretan OMEGA TOYS. Bahan ABS aman tidak tajam, non-toxic.",
                'images' => [
                    'https://down-id.img.susercontent.com/file/id-11134207-7ra0h-mcoogoxpezp84a',
                    'https://images.unsplash.com/photo-1587654780291-39c9404d746b?w=800&q=80',
                ],
                'tier_variations' => [
                    [
                        'name' => 'Jumlah Isi',
                        'options' => ['Isi 64 Pcs Kotak', 'Isi 108 Pcs Koper', 'Isi 148 Pcs Deluxe'],
                    ]
                ],
            ],
            [
                'name' => 'Tablet Gambar LCD Magic Drawing Board 8.5 Inch Layar Warna Warni',
                'price' => 29000,
                'weight' => 200,
                'description' => "Papan tulis digital LCD untuk anak belajar menulis, menggambar, dan berhitung tanpa mengotori dinding. Layar bebas radiasi dan hemat baterai (tahan hingga 1 tahun).\n\n100% Realpict OMEGA TOYS. Dilengkapi stylus pen dan tombol 1-klik hapus layar.",
                'images' => [
                    'https://images.unsplash.com/photo-1513542789411-b6a5d4f31634?w=800&q=80',
                ],
                'tier_variations' => [
                    [
                        'name' => 'Warna Case',
                        'options' => ['Biru Toska', 'Pink Pastel', 'Hitam Elegan'],
                    ]
                ],
            ],
            [
                'name' => 'Puzzle Kayu Montessori Alfabet Huruf & Angka Chunky Balok Kayu',
                'price' => 38000,
                'weight' => 400,
                'description' => "Papan puzzle kayu alami tebal (chunky) untuk melatih motorik halus balita mengenal huruf abjad A-Z dan angka 1-20 serta warna. Cat berbasis air non-toxic ramah anak.",
                'images' => [
                    'https://down-id.img.susercontent.com/file/id-11134207-7ra0h-mcoogoxpezp84a',
                ],
                'tier_variations' => [
                    [
                        'name' => 'Tipe Puzzle',
                        'options' => ['Huruf Besar A-Z', 'Huruf Kecil a-z', 'Angka & Simbol Matematika'],
                    ]
                ],
            ],

            // 4. Mainan Peran
            [
                'name' => 'Kitchen Playset Dapur Masak-Masakan Anak Lengkap Kompor Uap & Suara',
                'price' => 165000,
                'weight' => 1400,
                'description' => "Set perlengkapan dapur masak-masakan lengkap dengan kompor bersuara mendidih, efek uap dingin realistis, kran air yang bisa mengeluarkan air sungguhan, wajan, panci, dan bahan makanan tiruan.\n\n100% Realpict OMEGA TOYS. Tinggi 63 cm.",
                'images' => [
                    'https://down-id.img.susercontent.com/file/id-11134207-7ra0o-mcoog9t1k1m77e',
                    'https://images.unsplash.com/photo-1596461404969-9ae70f2830c1?w=800&q=80',
                ],
                'tier_variations' => [
                    [
                        'name' => 'Pilihan Warna',
                        'options' => ['Soft Pink Dapur', 'Mint Green Dapur'],
                    ]
                ],
            ],
            [
                'name' => 'Doctor Medical Set Koper Perlengkapan Dokter Cilik Lengkap Stetoskop',
                'price' => 59000,
                'weight' => 450,
                'description' => "Mainan peran dokter-dokteran dalam koper tenteng rapi. Terdiri dari stetoskop berlampu dan detak jantung, suntikan pegas, termometer, kacamata, dan obat-obatan mainan.\n\n100% Realpict produk toko OMEGA TOYS.",
                'images' => [
                    'https://down-id.img.susercontent.com/file/id-11134207-7ra0o-mcoog9t1k1m77e',
                ],
            ],
            [
                'name' => 'Mesin Kasir Cash Register Mini Supermarket Scanner Bunyi & Kartu Debit',
                'price' => 78000,
                'weight' => 500,
                'description' => "Mainan mesin kasir supermarket interaktif dengan kalkulator fungsi nyata, barcode scanner bunyi bip, laci uang otomatis buka, mikrofon, dan uang koin & kertas mainan.\n\n100% Realpict OMEGA TOYS.",
                'images' => [
                    'https://down-id.img.susercontent.com/file/id-11134207-7ra0o-mcoog9t1k1m77e',
                ],
            ],

            // 5. Kacamata Renang
            [
                'name' => 'Kacamata Renang Anak Anti Fog UV Protection Waterproof Silikon Lembut',
                'price' => 28000,
                'weight' => 150,
                'description' => "Kacamata renang anak dengan lensa anti embun (anti-fog) dan perlindungan radiasi sinar UV matahari. Frame terbuat dari silikon ultra lembut yang kedap air dan tidak menekan lingkar mata anak.\n\n100% Realpict OMEGA TOYS. Tali strap kepala bisa disesuaikan dengan mudah.",
                'images' => [
                    'https://down-id.img.susercontent.com/file/id-11134207-7ra0o-mbae86ejvye1dd',
                    'https://images.unsplash.com/photo-1530549387789-4c1017266635?w=800&q=80',
                ],
                'tier_variations' => [
                    [
                        'name' => 'Warna / Motif',
                        'options' => ['Dolphin Biru', 'Flamingo Pink', 'Dino Hijau', 'Unicorn Ungu'],
                    ]
                ],
            ],
            [
                'name' => 'Kacamata Renang Karakter Hewan Lucu 3D Telinga Silikon + Penutup Telinga',
                'price' => 32000,
                'weight' => 180,
                'description' => "Kacamata renang anak motif 3D telinga hewan lucu menggemaskan. Dilengkapi earplug (penutup telinga) terintegrasi anti hilang agar air tidak masuk ke telinga anak saat berenang.\n\n100% Realpict toko OMEGA TOYS.",
                'images' => [
                    'https://down-id.img.susercontent.com/file/id-11134207-7ra0o-mbae86ejvye1dd',
                ],
            ],

            // 6. Mainan Robot & Action Figure
            [
                'name' => 'Robot Transformer Deformasi Mobil Autobot Warrior Alloy Figure',
                'price' => 95000,
                'weight' => 450,
                'description' => "Robot aksi yang bisa dirakit dan diubah menjadi mobil sport keren (2 in 1). Terbuat dari bahan ABS solid dengan engsel presisi yang kuat dan tahan banting.\n\n100% Realpict produk OMEGA TOYS. Tinggi robot 19 cm.",
                'images' => [
                    'https://images.unsplash.com/photo-1563245372-f21724e3856d?w=800&q=80',
                ],
                'tier_variations' => [
                    [
                        'name' => 'Karakter',
                        'options' => ['Optimus Commander (Truk Merah)', 'Bumble Bee (Camaro Kuning)', 'Ironhide (Pickup Hitam)'],
                    ]
                ],
            ],

            // 7. Koleksi & Hobi
            [
                'name' => 'Drone Mini Quadcopter Kamera HD Altitude Hold WiFi FPV Pemula',
                'price' => 245000,
                'weight' => 600,
                'description' => "Drone mini canggih dengan kamera HD yang bisa disambungkan langsung ke smartphone via WiFi. Dilengkapi fitur Altitude Hold (otomatis melayang stabil di udara), 1-Key Takeoff/Landing, dan pelindung baling-baling 360 derajat.\n\n100% Realpict toko OMEGA TOYS. Sangat mudah dikendalikan anak & pemula.",
                'images' => [
                    'https://images.unsplash.com/photo-1527977966376-1c8408f9f108?w=800&q=80',
                ],
                'tier_variations' => [
                    [
                        'name' => 'Pilihan Kamera',
                        'options' => ['Single Kamera 1080P', 'Dual Kamera HD + Optical Flow'],
                    ]
                ],
            ],
            [
                'name' => 'Pistol Gelembung Sabun Bubble Gun Gatling 21 Lubang Lampu LED',
                'price' => 35000,
                'weight' => 300,
                'description' => "Mainan tembakan gelembung otomatis dengan 21 lubang semburan yang menghasilkan ribuan balon gelembung per menit. Dilengkapi lampu LED warna-warni dan botol cairan bubble refill.\n\n100% Realpict OMEGA TOYS.",
                'images' => [
                    'https://images.unsplash.com/photo-1513542789411-b6a5d4f31634?w=800&q=80',
                ],
                'tier_variations' => [
                    [
                        'name' => 'Warna Gun',
                        'options' => ['Gold Emas Mewah', 'Hitam Gagah', 'Pink Gemas'],
                    ]
                ],
            ],
        ];

        $this->command->info("Clearing old catalog data...");
        ShopeeImporter::clearAllCatalogData();

        $this->command->info("Importing initial official store products for 0meg4t0y5...");
        $res = ShopeeImporter::importItems($products);

        $this->command->info($res['message']);
    }
}

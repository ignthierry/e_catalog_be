<?php

namespace App\Console\Commands;

use App\Services\ShopeeImporter;
use Illuminate\Console\Command;

class ImportShopeeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee:import {file? : Path to JSON file containing Shopee items} {--clear-only : Only clear database without importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear catalog and import products from Shopee 0meg4t0y5 store';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("=== OMEGA TOYS Catalog Manager ===");

        // 1. Clear database
        $this->warn("Clearing existing products, categories, and orders...");
        ShopeeImporter::clearAllCatalogData();
        $this->info("Catalog database cleared successfully!");

        if ($this->option('clear-only')) {
            $this->info("Done! (Clear only mode)");
            return 0;
        }

        $filePath = $this->argument('file');
        if (!$filePath) {
            $filePath = base_path('../scratch/shopee_all_products.json');
        }

        if (!file_exists($filePath)) {
            $this->warn("No items file found at {$filePath}. Database is now clean and ready for new products.");
            return 0;
        }

        $content = file_get_contents($filePath);
        $json = json_decode($content, true);

        if (!is_array($json)) {
            $this->error("Invalid JSON format in {$filePath}");
            return 1;
        }

        $items = isset($json['items']) ? $json['items'] : (isset($json['data']) ? $json['data'] : $json);

        $this->info("Importing " . count($items) . " items...");
        $res = ShopeeImporter::importItems($items);

        $this->info($res['message']);
        return 0;
    }
}

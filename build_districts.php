<?php
$apiKey = 'sk_9utxs5qa60bmkycnvokiqrnsyjon0dygkkg71f92c2gqvpmkhdjzaisrmvzikl2t';

$start = microtime(true);

// 1. Provinces
$provJson = file_get_contents("https://api.binderbyte.com/wilayah/provinsi?api_key={$apiKey}");
$provData = json_decode($provJson, true)['value'] ?? [];
$provMap = [];
foreach ($provData as $p) {
    $provMap[$p['id']] = $p['name'];
}

// 2. Kabupaten
$mh = curl_multi_init();
$curlHandles = [];
foreach ($provData as $p) {
    $ch = curl_init("https://api.binderbyte.com/wilayah/kabupaten?api_key={$apiKey}&id_provinsi={$p['id']}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_multi_add_handle($mh, $ch);
    $curlHandles[$p['id']] = $ch;
}
$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

$allKab = [];
$kabMap = [];
foreach ($curlHandles as $pId => $ch) {
    $content = curl_multi_getcontent($ch);
    $data = json_decode($content, true)['value'] ?? [];
    foreach ($data as $kab) {
        $allKab[] = $kab;
        $kabMap[$kab['id']] = [
            'name' => $kab['name'],
            'prov_id' => $kab['id_provinsi'],
            'prov_name' => $provMap[$kab['id_provinsi']] ?? '',
        ];
    }
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);

echo "Fetched " . count($allKab) . " kabupaten in " . round(microtime(true) - $start, 2) . "s\n";

// 3. Kecamatan in batches of 40 concurrent requests
$chunks = array_chunk($allKab, 40);
$allDistricts = [];

foreach ($chunks as $chunkIdx => $chunk) {
    $mh = curl_multi_init();
    $handles = [];
    foreach ($chunk as $kab) {
        $ch = curl_init("https://api.binderbyte.com/wilayah/kecamatan?api_key={$apiKey}&id_kabupaten={$kab['id']}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_multi_add_handle($mh, $ch);
        $handles[$kab['id']] = $ch;
    }
    $r = null;
    do {
        curl_multi_exec($mh, $r);
        curl_multi_select($mh);
    } while ($r > 0);

    foreach ($handles as $kabId => $ch) {
        $content = curl_multi_getcontent($ch);
        $data = json_decode($content, true)['value'] ?? [];
        $kInfo = $kabMap[$kabId] ?? ['name' => '', 'prov_name' => ''];
        foreach ($data as $dist) {
            $distId = $dist['id'];
            $distName = $dist['name'];
            $kabName = $kInfo['name'];
            $provName = $kInfo['prov_name'];
            $label = "{$distName}, {$kabName}, {$provName}";

            $allDistricts[] = [
                'id' => "dist_{$distId}",
                'code' => $distId,
                'label' => ucwords(strtolower($label)),
                'district' => ucwords(strtolower($distName)),
                'city' => ucwords(strtolower($kabName)),
                'province' => ucwords(strtolower($provName)),
                'zipCode' => '',
            ];
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    echo "Completed batch " . ($chunkIdx + 1) . "/" . count($chunks) . " (Total so far: " . count($allDistricts) . ")\n";
}

$outputFile = __DIR__ . '/storage/app/binderbyte_districts.json';
@mkdir(dirname($outputFile), 0777, true);
file_put_contents($outputFile, json_encode($allDistricts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo "Successfully generated {$outputFile} with " . count($allDistricts) . " districts in " . round(microtime(true) - $start, 2) . "s\n";

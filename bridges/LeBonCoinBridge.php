// ==== START PATCH: LBC request with headers (403 fix) ====
$payload = json_encode($requestJson, JSON_UNESCAPED_UNICODE);

// Utilise cURL avec des en-têtes "navigateur"
$ch = curl_init('https://api.leboncoin.fr/api/adfinder/v1/search');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Origin: https://www.leboncoin.fr',
        'Referer: https://www.leboncoin.fr/recherche/',
        'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1'
    ],
    CURLOPT_TIMEOUT => 20,
]);
$response = curl_exec($ch);
if ($response === false) {
    throw new \Exception('LeBonCoin API error: '.curl_error($ch));
}
$httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($httpCode >= 400) {
    throw new \Exception('LeBonCoin API HTTP '.$httpCode.' response');
}

$data = json_decode($response, true) ?: [];
$ads  = $data['ads'] ?? $data['data']['ads'] ?? $data['search_result']['ads'] ?? [];
// ==== END PATCH ====
// ==== START PATCH: build items safely (price fix) ====
foreach ($ads as $ad) {
    $title = $ad['subject'] ?? $ad['title'] ?? 'Annonce Leboncoin';
    $url   = $ad['url'] ?? ($ad['body']['url'] ?? null);
    if (!$url && isset($ad['list_id'])) {
        $url = 'https://www.leboncoin.fr/annonce/'.$ad['list_id'];
    }

    $priceVal = null;
    // Plusieurs formats possibles suivant l'API
    if (isset($ad['price']['value'])) {
        $priceVal = $ad['price']['value'];
    } elseif (isset($ad['price'])) {
        $priceVal = is_array($ad['price']) ? ($ad['price']['amount'] ?? null) : $ad['price'];
    }

    $desc = $ad['body'] ?? $ad['description'] ?? '';
    if ($priceVal !== null) {
        $desc = 'Prix: ' . $priceVal . ' € — ' . $desc;
    }

    $item = [];
    $item['title'] = $title;
    $item['uri']   = $url ?: 'https://www.leboncoin.fr';
    $item['content'] = $desc;
    $item['author']  = $ad['owner']['type'] ?? ($ad['owner']['name'] ?? 'LBC');
    $item['enclosures'] = [];

    // image si dispo
    $img = $ad['images']['urls_large'][0] ?? $ad['images']['urls'][0] ?? null;
    if ($img) {
        $item['enclosures'][] = $img;
    }

    // date
    if (!empty($ad['index_date'])) {
        $item['timestamp'] = strtotime($ad['index_date']);
    } elseif (!empty($ad['created_at'])) {
        $item['timestamp'] = strtotime($ad['created_at']);
    }

    $this->items[] = $item;
}
// ==== END PATCH ====

<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['url'])) {
    echo json_encode(['success' => false, 'error' => 'No URL provided']);
    exit;
}

$url = $_POST['url'];

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid URL']);
    exit;
}

/**
 * Recursively resolve redirects (HTTP + JavaScript + Meta + Base64)
 */
function resolveFinalUrl($url, $depth = 0) {
    if ($depth > 10) {
        return ['success' => true, 'destination' => $url, 'warning' => 'Max depth reached'];
    }

    // Step 1: Fetch the page (follow HTTP redirects automatically)
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_TIMEOUT => 30,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => 'cURL error: ' . $error];
    }

    $currentUrl = $info['url'] ?? $url;

    // Step 2: Separate headers and body
    $headerSize = $info['header_size'];
    $body = substr($response, $headerSize);

    // Step 3: Look for JavaScript / Meta redirects
    $patterns = [
        '/window\.location\.replace\s*\(\s*["\']([^"\']+)["\']\s*\)/i',
        '/window\.location\s*=\s*["\']([^"\']+)["\']/i',
        '/top\.location\s*=\s*["\']([^"\']+)["\']/i',
        '/document\.location\s*=\s*["\']([^"\']+)["\']/i',
        '/location\.href\s*=\s*["\']([^"\']+)["\']/i',
        '/window\.location\.href\s*=\s*["\']([^"\']+)["\']/i',
        '/var\s+link\s*=\s*["\']([^"\']+)["\']/i',
        '/var\s+url\s*=\s*["\']([^"\']+)["\']/i',
        '/["\']url["\']\s*:\s*["\']([^"\']+)["\']/i',
        '/["\']link["\']\s*:\s*["\']([^"\']+)["\']/i',
        '/<meta\s+http-equiv=["\']refresh["\']\s+content=["\'][0-9]+;\s*url=([^"\']+)["\']/i',
    ];

    $foundUrl = null;

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $body, $matches)) {
            $foundUrl = trim($matches[1]);
            break;
        }
    }

    if ($foundUrl) {
        // Step 4: Handle Base64 encoding (VPlink often uses this)
        if (preg_match('/^[A-Za-z0-9+\/=]+$/', $foundUrl)) {
            $decoded = base64_decode($foundUrl, true);
            if ($decoded && filter_var($decoded, FILTER_VALIDATE_URL)) {
                $foundUrl = $decoded;
            }
        }

        // Fix relative URLs
        if (strpos($foundUrl, 'http') !== 0) {
            $base = parse_url($currentUrl);
            if (isset($base['scheme']) && isset($base['host'])) {
                $foundUrl = $base['scheme'] . '://' . $base['host'] . ($foundUrl[0] === '/' ? '' : '/') . $foundUrl;
            }
        }

        if (filter_var($foundUrl, FILTER_VALIDATE_URL)) {
            // Recursively resolve this new URL (in case it's another ad link)
            return resolveFinalUrl($foundUrl, $depth + 1);
        }
    }

    // Step 5: If no JS redirect found, return the current URL
    return ['success' => true, 'destination' => $currentUrl];
}

$result = resolveFinalUrl($url);
echo json_encode($result);
?>
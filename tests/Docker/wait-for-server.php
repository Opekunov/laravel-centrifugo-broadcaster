<?php

/**
 * Wait for Centrifugo servers to become available.
 *
 * Usage: php wait-for-server.php http://localhost:8001 http://localhost:8002
 */
$urls = array_slice($argv, 1);

if (empty($urls)) {
    echo "Usage: php wait-for-server.php <url1> [url2] ...\n";
    exit(1);
}

$maxWait = 30;
$interval = 1;

foreach ($urls as $url) {
    $healthUrl = rtrim($url, '/').'/health';
    $start = time();
    $ready = false;

    echo "Waiting for {$url} ...";

    while (time() - $start < $maxWait) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($healthUrl, false, $context);

        if ($result !== false) {
            $ready = true;
            echo ' ready ('.(time() - $start)."s)\n";
            break;
        }

        sleep($interval);
    }

    if (! $ready) {
        echo " TIMEOUT after {$maxWait}s\n";
        exit(1);
    }
}

echo "All servers are ready.\n";

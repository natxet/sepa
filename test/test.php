<?php

require_once __DIR__ . '/../src/Natxet/SEPA/SEPA.php';
$expected_output = file_get_contents( __DIR__ . '/output.txt' );

$sepa   = new \Natxet\SEPA\SEPA( json_decode( file_get_contents( __DIR__ . '/input.json' ) ) );
$output = $sepa->output();

echo '*** Testing SEPA Class ***' . PHP_EOL;

if ($output !== $expected_output) {
    echo "\033[31m" . 'Output is different than expected' . "\033[0m" . PHP_EOL;
    exit( 1 );
} else {
    echo "\033[32m" . 'Output is equal than expected' . "\033[0m" . PHP_EOL;;
    exit( 0 );
}

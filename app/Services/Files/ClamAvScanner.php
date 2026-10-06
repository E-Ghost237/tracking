<?php

namespace App\Services\Files;

use App\Contracts\MalwareScanner;
use RuntimeException;

/**
 * Streams a file to clamd with the INSTREAM command.
 */
class ClamAvScanner implements MalwareScanner
{
    public function __construct(private readonly string $host, private readonly int $port) {}

    public function scan(string $absolutePath): array
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);
        if ($socket === false) {
            throw new RuntimeException('ClamAV unavailable: '.$errstr);
        }

        stream_set_timeout($socket, 30);
        fwrite($socket, "zINSTREAM\0");

        $file = fopen($absolutePath, 'rb');
        while (! feof($file)) {
            $chunk = (string) fread($file, 8192);
            fwrite($socket, pack('N', strlen($chunk)).$chunk);
        }
        fclose($file);
        fwrite($socket, pack('N', 0));

        $reply = trim((string) fgets($socket));
        fclose($socket);

        if (str_ends_with($reply, 'OK')) {
            return ['clean' => true, 'signature' => null];
        }

        if (preg_match('/: (.+) FOUND/', $reply, $m) === 1) {
            return ['clean' => false, 'signature' => $m[1]];
        }

        throw new RuntimeException('Unexpected ClamAV reply: '.$reply);
    }
}

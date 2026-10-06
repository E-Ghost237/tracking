<?php

namespace App\Services\Files;

use App\Contracts\MalwareScanner;

/**
 * Signature-based fallback scanner used when ClamAV is not available. It rejects
 * executables, scripts, the EICAR test file and PDFs with active content.
 *
 * Signatures are long enough that random bytes in compressed image data do not match them.
 */
class HeuristicScanner implements MalwareScanner
{
    private const MAX_BYTES = 16 * 1024 * 1024;

    private const SIGNATURES = [
        'eicar' => 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE',
        'php' => '<?php',
        'script_tag' => '<script',
        'html' => '<!doctype html',
        'iframe' => '<iframe',
        'svg_handler' => 'onload=',
    ];

    private const MAGIC = [
        'pe_executable' => 'MZ',
        'elf_executable' => "\x7fELF",
        'mach_o' => "\xcf\xfa\xed\xfe",
        'shebang' => '#!',
        'zip_archive' => "PK\x03\x04",
    ];

    /**
     * PDF dictionary keys that trigger or embed active content.
     */
    private const PDF_ACTIVE = ['/JavaScript', '/JS', '/Launch', '/EmbeddedFile', '/RichMedia', '/XFA', '/OpenAction', '/AA'];

    public function scan(string $absolutePath): array
    {
        $size = @filesize($absolutePath);
        if ($size === false || $size > self::MAX_BYTES) {
            return ['clean' => false, 'signature' => 'unreadable_or_too_large'];
        }

        $content = (string) file_get_contents($absolutePath);

        foreach (self::MAGIC as $name => $magic) {
            if (str_starts_with($content, $magic)) {
                return ['clean' => false, 'signature' => $name];
            }
        }

        $isPdf = str_starts_with($content, '%PDF-');

        // For PDFs, inspect the object dictionaries only: binary stream bodies are removed first.
        $haystack = $isPdf ? $this->stripPdfStreams($content) : $content;
        $lower = strtolower($haystack);

        foreach (self::SIGNATURES as $name => $signature) {
            if (str_contains($lower, strtolower($signature))) {
                return ['clean' => false, 'signature' => $name];
            }
        }

        if ($isPdf) {
            foreach (self::PDF_ACTIVE as $marker) {
                if (preg_match('#'.preg_quote($marker, '#').'(?![A-Za-z])#', $haystack) === 1) {
                    return ['clean' => false, 'signature' => 'pdf_active_content'];
                }
            }
        }

        return ['clean' => true, 'signature' => null];
    }

    /**
     * Removes binary stream bodies without regular expressions, so large files cannot hit
     * backtracking limits and silently skip the scan.
     */
    private function stripPdfStreams(string $pdf): string
    {
        $result = '';
        $offset = 0;

        while (($start = strpos($pdf, 'stream', $offset)) !== false) {
            // "endstream" also contains "stream": only treat a real "stream" keyword as a start.
            if ($start >= 3 && substr($pdf, $start - 3, 3) === 'end') {
                $result .= substr($pdf, $offset, $start + 6 - $offset);
                $offset = $start + 6;

                continue;
            }

            $end = strpos($pdf, 'endstream', $start + 6);
            $result .= substr($pdf, $offset, $start - $offset).'stream ';

            if ($end === false) {
                // Unterminated stream: scan the remainder rather than skipping it (fail closed).
                return $result.substr($pdf, $start);
            }

            $offset = $end;
        }

        return $result.substr($pdf, $offset);
    }
}

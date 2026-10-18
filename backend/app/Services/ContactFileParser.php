<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ContactFileParser
{
    protected static array $phoneKeywords = [
        'phone', 'mobile', 'tel', 'telephone', 'cell', 'cellular', 'whatsapp', 'number',
        'numbers', 'phone_number', 'phone number', 'phonenumber', 'mobile_number',
        'mobile number', 'recipient', 'recipients', 'contact', 'contacts', 'رقم',
        'هاتف', 'موبايل', 'جوال', 'تليفون', 'واتساب', 'الرقم', 'رقم الهاتف',
        'رقم الجوال', 'رقم الموبايل', 'رقم الواتساب', 'ارقام', 'الأرقام', 'الارقام',
        'جهة الاتصال', 'جهات الاتصال'
    ];

    protected static array $nameKeywords = [
        'name', 'names', 'first_name', 'first name', 'firstname', 'last_name',
        'last name', 'lastname', 'full_name', 'full name', 'fullname', 'contact_name',
        'contact name', 'customer', 'customer_name', 'client', 'client_name',
        'الاسم', 'اسم', 'الاسم الأول', 'الاسم الاول', 'الاسم الأخير', 'الاسم الاخير',
        'الاسم بالكامل', 'اسم العميل', 'العميل'
    ];

    protected static function maxRows(array $options): int
    {
        $value = $options['max_rows'] ?? 5000;
        return is_int($value) && $value > 0 ? $value : 5000;
    }

    protected static function trimResults(array $results, int $maxRows): array
    {
        return array_slice($results, 0, $maxRows);
    }

    /**
     * Parse any supported contact file into an array of ['name' => ..., 'phone' => ...]
     */
    public static function parse($file, array $options = []): array
    {
        $maxRows = self::maxRows($options);

        if ($file instanceof UploadedFile) {
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            $path = $file->getRealPath();
        } elseif (is_string($file)) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $path = $file;
        } else {
            return [];
        }

        if (empty($extension)) {
            $extension = 'csv';
        }

        if (in_array($extension, ['csv', 'txt', 'tsv', 'text'])) {
            return self::parseDelimitedOrText($path, $extension, $maxRows);
        }

        if (in_array($extension, ['xlsx', 'xls'])) {
            return self::parseXlsx($path, $maxRows);
        }

        if (in_array($extension, ['vcf', 'vcard'])) {
            return self::parseVcf($path, $maxRows);
        }

        // Fallback: try delimited parser
        return self::parseDelimitedOrText($path, $extension, $maxRows);
    }

    /**
     * Parse CSV, TSV or plain text file.
     */
    public static function parseDelimitedOrText(string $path, string $extension = 'csv', int $maxRows = 5000): array
    {
        $content = @file_get_contents($path);
        if ($content === false || strlen(trim($content)) === 0) {
            return [];
        }

        // Clean UTF-8 BOM
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Convert encoding if necessary
        $content = self::ensureUtf8($content);

        // Normalize newlines
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $rawLines = explode("\n", $content);

        $lines = [];
        foreach ($rawLines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $lines[] = $trimmed;
            }
        }

        if (empty($lines)) {
            return [];
        }

        // Detect delimiter: check first 5 lines for commas, semicolons, tabs, pipes
        $sample = implode("\n", array_slice($lines, 0, 5));
        $delimiter = self::detectDelimiter($sample);

        // Try reading as CSV structure
        $parsedGrid = [];
        foreach ($lines as $line) {
            $cols = str_getcsv($line, $delimiter, '"', '\\');
            $parsedGrid[] = array_map('trim', $cols);
        }

        if (empty($parsedGrid)) {
            return [];
        }

        // Check if there are columns
        $maxCols = max(array_map('count', $parsedGrid));

        if ($maxCols === 1) {
            // Single column: can be just phone numbers, or lines like "Name: 01012345678" or "01012345678 - Name"
            return self::parseSingleColumnLines($lines);
        }

        // Multi-column CSV: determine if first row is header
        $firstRow = $parsedGrid[0];
        $headersLower = array_map([self::class, 'cleanHeader'], $firstRow);

        $phoneCol = self::findColumnIndex($headersLower, self::$phoneKeywords);
        $nameCol = self::findColumnIndex($headersLower, self::$nameKeywords);

        $hasHeaderRow = ($phoneCol !== null);
        $startIndex = 0;

        if ($hasHeaderRow) {
            $startIndex = 1;
        } else {
            // Auto-detect columns from data (check first 10 rows)
            [$phoneCol, $nameCol] = self::guessColumnsFromData($parsedGrid);
            $startIndex = 0;
        }

        if ($phoneCol === null) {
            // Fallback: try extracting phones from each line
            return self::parseSingleColumnLines($lines, $maxRows);
        }

        $results = [];
        $totalRows = count($parsedGrid);

        for ($i = $startIndex; $i < $totalRows; $i++) {
            $row = $parsedGrid[$i];
            if (empty($row)) continue;

            $phone = $row[$phoneCol] ?? '';
            $name = ($nameCol !== null && isset($row[$nameCol])) ? $row[$nameCol] : '';

            // If phone column looks empty or invalid, check other columns
            if (!self::hasPhoneDigits($phone) && count($row) > 1) {
                foreach ($row as $idx => $val) {
                    if ($idx !== $phoneCol && self::hasPhoneDigits($val)) {
                        $phone = $val;
                        if ($idx === $nameCol) {
                            $name = $row[$phoneCol] ?? '';
                        }
                        break;
                    }
                }
            }

            if (self::hasPhoneDigits($phone)) {
                $results[] = [
                    'phone' => trim($phone),
                    'name' => trim($name),
                ];
            }
        }

        return self::trimResults($results, $maxRows);
    }

    /**
     * Parse single column lines (e.g. phone numbers per line or "Name: Phone").
     */
    protected static function parseSingleColumnLines(array $lines, int $maxRows = 5000): array
    {
        $results = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Skip header if line is just "phone" or "phone_number" or "رقم"
            $lower = mb_strtolower($line);
            if (in_array($lower, ['phone', 'mobile', 'numbers', 'رقم', 'هاتف', 'موبايل', 'جوال'])) {
                continue;
            }

            // Check for separators like ':', '-', '|', ',', ';'
            $separators = [':', '-', '|', ',', ';', "\t"];
            $splitFound = false;

            foreach ($separators as $sep) {
                if (str_contains($line, $sep)) {
                    $parts = explode($sep, $line, 2);
                    $part1 = trim($parts[0]);
                    $part2 = trim($parts[1]);

                    $isPhone1 = self::isLikelyPhone($part1);
                    $isPhone2 = self::isLikelyPhone($part2);

                    if ($isPhone1 && !$isPhone2) {
                        $results[] = ['phone' => $part1, 'name' => $part2];
                        $splitFound = true;
                        break;
                    } elseif ($isPhone2 && !$isPhone1) {
                        $results[] = ['phone' => $part2, 'name' => $part1];
                        $splitFound = true;
                        break;
                    }
                }
            }

            if (!$splitFound) {
                // Try regex extraction of phone number
                if (preg_match('/(\+?[0-9]{7,18})/', preg_replace('/[\s\-()]/', '', $line), $matches)) {
                    $phone = $matches[1];
                    // Name is the rest
                    $name = trim(str_replace($phone, '', $line));
                    $name = trim(preg_replace('/^[\s\-:,|]+|[\s\-:,|]+$/u', '', $name));
                    $results[] = [
                        'phone' => $phone,
                        'name' => $name,
                    ];
                } elseif (self::hasPhoneDigits($line)) {
                    $results[] = [
                        'phone' => $line,
                        'name' => '',
                    ];
                }
            }
        }

        return self::trimResults($results, $maxRows);
    }

    /**
     * Guess which column is phone and which is name by analyzing data cells.
     */
    protected static function guessColumnsFromData(array $grid): array
    {
        $colScores = [];
        $sample = array_slice($grid, 0, 15);

        foreach ($sample as $row) {
            foreach ($row as $colIdx => $val) {
                if (!isset($colScores[$colIdx])) {
                    $colScores[$colIdx] = ['phone_score' => 0, 'text_score' => 0];
                }

                $cleanVal = preg_replace('/[\s\-()]/', '', $val);
                if (self::isLikelyPhone($cleanVal)) {
                    $colScores[$colIdx]['phone_score'] += 2;
                } elseif (self::hasPhoneDigits($cleanVal)) {
                    $colScores[$colIdx]['phone_score'] += 1;
                } elseif (!empty($val) && preg_match('/[\p{L}]/u', $val)) {
                    $colScores[$colIdx]['text_score'] += 2;
                }
            }
        }

        $phoneCol = null;
        $maxPhoneScore = 0;
        foreach ($colScores as $idx => $scores) {
            if ($scores['phone_score'] > $maxPhoneScore) {
                $maxPhoneScore = $scores['phone_score'];
                $phoneCol = $idx;
            }
        }

        $nameCol = null;
        $maxTextScore = 0;
        foreach ($colScores as $idx => $scores) {
            if ($idx !== $phoneCol && $scores['text_score'] > $maxTextScore) {
                $maxTextScore = $scores['text_score'];
                $nameCol = $idx;
            }
        }

        // If no distinct name column, pick another column if exists
        if ($nameCol === null && $phoneCol !== null) {
            foreach ($colScores as $idx => $scores) {
                if ($idx !== $phoneCol) {
                    $nameCol = $idx;
                    break;
                }
            }
        }

        return [$phoneCol, $nameCol];
    }

    /**
     * Parse modern Excel (.xlsx).
     */
    public static function parseXlsx(string $path, int $maxRows = 5000): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        // 1. Load shared strings
        $values = [];
        $sharedXmlContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXmlContent) {
            $sharedXml = @simplexml_load_string($sharedXmlContent);
            if ($sharedXml && isset($sharedXml->si)) {
                foreach ($sharedXml->si as $si) {
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string) $si->t;
                    } elseif (isset($si->r)) {
                        foreach ($si->r as $r) {
                            $text .= (string) ($r->t ?? '');
                        }
                    }
                    $values[] = $text;
                }
            }
        }

        // 2. Find worksheet XML
        $sheetXmlContent = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $filename = $stat['name'] ?? '';
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $filename)) {
                $sheetXmlContent = $zip->getFromIndex($i);
                break;
            }
        }

        $zip->close();

        if (!$sheetXmlContent) {
            return [];
        }

        $sheet = @simplexml_load_string($sheetXmlContent);
        if (!$sheet) {
            return [];
        }

        $ns = $sheet->getNamespaces(true);
        $dataNode = $sheet->sheetData ?? $sheet->children($ns[''] ?? '')->sheetData;
        if (!$dataNode || !isset($dataNode->row)) {
            return [];
        }

        $grid = [];
        foreach ($dataNode->row as $row) {
            $rowData = [];
            foreach ($row->c as $c) {
                $attr = $c->attributes();
                $ref = (string) ($attr['r'] ?? '');
                preg_match('/([A-Z]+)(\d+)/', $ref, $m);
                $col = $m[1] ?? 'A';
                $colIndex = self::columnToIndex($col);

                $type = (string) ($attr['t'] ?? '');
                $val = '';

                if ($type === 's') {
                    $sIndex = (int) $c->v;
                    $val = $values[$sIndex] ?? '';
                } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                    $val = (string) $c->is->t;
                } elseif (isset($c->v)) {
                    $val = (string) $c->v;
                }

                $rowData[$colIndex] = trim($val);
            }

            if (!empty($rowData)) {
                // Fill missing numeric indexes up to max index
                $maxKey = max(array_keys($rowData));
                $normalizedRow = [];
                for ($k = 0; $k <= $maxKey; $k++) {
                    $normalizedRow[$k] = $rowData[$k] ?? '';
                }
                $grid[] = $normalizedRow;
            }
        }

        if (empty($grid)) {
            return [];
        }

        // Multi-column grid processing
        $firstRow = $grid[0];
        $headersLower = array_map([self::class, 'cleanHeader'], $firstRow);

        $phoneCol = self::findColumnIndex($headersLower, self::$phoneKeywords);
        $nameCol = self::findColumnIndex($headersLower, self::$nameKeywords);

        $hasHeader = ($phoneCol !== null);
        $startIndex = 0;

        if ($hasHeader) {
            $startIndex = 1;
        } else {
            [$phoneCol, $nameCol] = self::guessColumnsFromData($grid);
            $startIndex = 0;
        }

        if ($phoneCol === null) {
            $lines = array_map(fn($r) => implode(' ', $r), $grid);
            return self::parseSingleColumnLines($lines, $maxRows);
        }

        $results = [];
        $totalRows = count($grid);

        for ($i = $startIndex; $i < $totalRows; $i++) {
            $row = $grid[$i];
            $phone = $row[$phoneCol] ?? '';
            $name = ($nameCol !== null && isset($row[$nameCol])) ? $row[$nameCol] : '';

            // If phone column is empty or invalid, check others
            if (!self::hasPhoneDigits($phone) && count($row) > 1) {
                foreach ($row as $idx => $val) {
                    if ($idx !== $phoneCol && self::hasPhoneDigits($val)) {
                        $phone = $val;
                        if ($idx === $nameCol) {
                            $name = $row[$phoneCol] ?? '';
                        }
                        break;
                    }
                }
            }

            if (self::hasPhoneDigits($phone)) {
                $results[] = [
                    'phone' => trim($phone),
                    'name' => trim($name),
                ];
            }
        }

        return self::trimResults($results, $maxRows);
    }

    /**
     * Parse vCard (.vcf) contacts.
     */
    public static function parseVcf(string $path, int $maxRows = 5000): array
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $content = self::ensureUtf8($content);
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Unfold continuation lines
        $rawLines = explode("\n", $content);
        $unfolded = [];
        foreach ($rawLines as $line) {
            if ($unfolded === []) {
                $unfolded[] = $line;
            } elseif ($line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
                $unfolded[count($unfolded) - 1] .= substr($line, 1);
            } elseif (substr($unfolded[count($unfolded) - 1] ?? '', -1) === '=') {
                $unfolded[count($unfolded) - 1] = rtrim($unfolded[count($unfolded) - 1], '=') . $line;
            } else {
                $unfolded[] = $line;
            }
        }

        $content = implode("\n", $unfolded);
        $cards = array_map('trim', preg_split('/BEGIN:VCARD/i', $content) ?: []);
        $results = [];

        foreach ($cards as $card) {
            if ($card === '' || !preg_match('/END:VCARD/i', $card)) {
                continue;
            }

            $name = '';
            $family = '';
            $given = '';
            $phones = [];
            $lines = explode("\n", $card);

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strtoupper($line) === 'END:VCARD') {
                    continue;
                }

                [$key, $value] = self::parseVcfLine($line);
                if ($key === 'FN' && $value !== '') {
                    $name = $value;
                } elseif ($key === 'N' && $value !== '') {
                    $parts = explode(';', $value);
                    $family = trim($parts[0] ?? '');
                    $given = trim($parts[1] ?? '');
                } elseif (str_starts_with($key, 'TEL') && $value !== '') {
                    $phones[] = $value;
                }
            }

            if ($name === '') {
                $name = trim(($given ? $given . ' ' : '') . $family);
            }

            foreach ($phones as $phone) {
                if (self::hasPhoneDigits($phone)) {
                    $results[] = [
                        'phone' => $phone,
                        'name' => $name,
                    ];
                }
            }
        }

        return self::trimResults($results, $maxRows);
    }

    protected static function parseVcfLine(string $line): array
    {
        $parts = explode(':', $line, 2);
        $property = $parts[0] ?? '';
        $value = $parts[1] ?? '';

        $params = array_map('strtoupper', explode(';', $property));
        $key = $params[0];

        if (($dot = strpos($key, '.')) !== false) {
            $key = substr($key, $dot + 1);
        }

        $charset = 'UTF-8';
        $encoding = null;
        foreach ($params as $param) {
            if (str_starts_with($param, 'CHARSET=')) {
                $charset = substr($param, 8);
            }
            if (str_starts_with($param, 'ENCODING=')) {
                $encoding = substr($param, 9);
            }
        }

        if (in_array($encoding, ['QUOTED-PRINTABLE', 'Q'])) {
            $value = self::decodeQuotedPrintable($value, $charset);
        } elseif (strtoupper($charset) !== 'UTF-8') {
            $value = self::convertToUtf8($value, $charset);
        }

        return [strtoupper($key), trim($value)];
    }

    protected static function decodeQuotedPrintable(string $value, string $charset): string
    {
        $value = str_replace(["=\r\n", "=\n"], '', $value);
        $decoded = quoted_printable_decode($value);
        return self::convertToUtf8($decoded, $charset);
    }

    protected static function convertToUtf8(string $value, string $charset): string
    {
        $charset = strtoupper(trim($charset));
        if ($charset !== 'UTF-8' && !empty($charset)) {
            if (function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($value, 'UTF-8', $charset);
                if ($converted !== false) return $converted;
            }
            if (function_exists('iconv')) {
                $converted = @iconv($charset, 'UTF-8//IGNORE', $value);
                if ($converted !== false) return $converted;
            }
        }
        return self::ensureUtf8($value);
    }

    protected static function ensureUtf8(string $value): string
    {
        // UTF-16 with BOM (common for Outlook / Windows Contacts exports)
        if (str_starts_with($value, "\xFF\xFE")) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-16LE');
        }
        if (str_starts_with($value, "\xFE\xFF")) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-16BE');
        }

        // UTF-16 without BOM: lots of NUL bytes give it away (LE is the common case)
        $len = strlen($value);
        if ($len > 4 && substr_count($value, "\0") > $len / 4) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-16LE');
            if ($converted !== false) {
                return $converted;
            }
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        // Try Windows-1256 (common for Arabic Excel/Windows files)
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', ['Windows-1256', 'ISO-8859-1', 'ASCII']);
            if ($converted !== false) return $converted;
        }

        return iconv('UTF-8', 'UTF-8//IGNORE', $value) ?: $value;
    }

    protected static function detectDelimiter(string $sample): string
    {
        $delimiters = [
            ',' => substr_count($sample, ','),
            ';' => substr_count($sample, ';'),
            "\t" => substr_count($sample, "\t"),
            '|' => substr_count($sample, '|'),
        ];

        arsort($delimiters);
        $best = array_key_first($delimiters);
        return ($delimiters[$best] > 0) ? $best : ',';
    }

    protected static function cleanHeader(string $header): string
    {
        $header = preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/u', '', $header);
        return mb_strtolower(trim($header));
    }

    protected static function findColumnIndex(array $headers, array $keywords): ?int
    {
        foreach ($headers as $index => $header) {
            foreach ($keywords as $kw) {
                if ($header === $kw || str_contains($header, $kw)) {
                    return $index;
                }
            }
        }
        return null;
    }

    protected static function isLikelyPhone(string $value): bool
    {
        $clean = preg_replace('/[^0-9+]/', '', $value);
        $len = strlen(ltrim($clean, '+'));
        return $len >= 7 && $len <= 16;
    }

    protected static function hasPhoneDigits(string $value): bool
    {
        $clean = preg_replace('/[^0-9]/', '', $value);
        return strlen($clean) >= 7;
    }

    protected static function columnToIndex(string $col): int
    {
        $col = strtoupper($col);
        $index = 0;
        $len = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }
}

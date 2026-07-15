<?php
/**
 * includes/xlsx_reader.php
 * Parser XLSX minimal tanpa dependency Composer/PHPSpreadsheet.
 * XLSX adalah file ZIP berisi XML — kita baca langsung pakai ZipArchive + SimpleXML.
 * Hanya membaca sheet PERTAMA, cukup untuk kebutuhan import mahasiswa sederhana.
 */

/**
 * Baca file XLSX dan kembalikan array baris (tiap baris = array kolom, index 0-based).
 * @param string $filePath Path ke file .xlsx
 * @return array
 * @throws Exception
 */
function readXlsxRows($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception("File tidak ditemukan: {$filePath}");
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new Exception("Gagal membuka file XLSX (bukan file Excel valid).");
    }

    // 1. Baca shared strings (string yang dipakai berulang disimpan terpisah untuk efisiensi)
    $sharedStrings = [];
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedStringsXml !== false) {
        $sst = simplexml_load_string($sharedStringsXml);
        if ($sst !== false) {
            foreach ($sst->si as $si) {
                // Teks bisa langsung di <t> atau tersebar di beberapa <r><t>
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                } elseif (isset($si->r)) {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string) $r->t;
                    }
                    $sharedStrings[] = $text;
                } else {
                    $sharedStrings[] = '';
                }
            }
        }
    }

    // 2. Cari sheet pertama (biasanya xl/worksheets/sheet1.xml)
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        $zip->close();
        throw new Exception("Tidak ditemukan worksheet di dalam file XLSX.");
    }

    $sheet = simplexml_load_string($sheetXml);
    $zip->close();

    if ($sheet === false) {
        throw new Exception("Gagal membaca isi worksheet.");
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $rowData = [];
        $lastColIndex = -1;

        foreach ($row->c as $cell) {
            $ref = (string) $cell['r']; // contoh: "B3"
            $colLetters = preg_replace('/[0-9]/', '', $ref);
            $colIndex = xlsxColumnLetterToIndex($colLetters);

            // Isi kolom yang terlewat (cell kosong) dengan string kosong
            while ($lastColIndex < $colIndex - 1) {
                $lastColIndex++;
                $rowData[$lastColIndex] = '';
            }

            $type = (string) $cell['t'];
            $value = isset($cell->v) ? (string) $cell->v : '';

            if ($type === 's') {
                // shared string -> $value adalah index ke $sharedStrings
                $value = $sharedStrings[(int) $value] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = isset($cell->is->t) ? (string) $cell->is->t : '';
            }

            $rowData[$colIndex] = trim($value);
            $lastColIndex = $colIndex;
        }

        $rows[] = $rowData;
    }

    return $rows;
}

/**
 * Konversi huruf kolom Excel (A, B, ..., Z, AA, AB, ...) jadi index 0-based.
 */
function xlsxColumnLetterToIndex($letters) {
    $letters = strtoupper($letters);
    $index = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

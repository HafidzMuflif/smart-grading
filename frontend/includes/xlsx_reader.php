<?php
/**
 * includes/xlsx_reader.php
 * Parser XLSX minimal tanpa dependency Composer/PHPSpreadsheet.
 * XLSX adalah file ZIP berisi XML — kita baca langsung pakai ZipArchive + SimpleXML.
 */

/**
 * Baca SEMUA sheet dalam file XLSX.
 * @param string $filePath Path ke file .xlsx
 * @return array ['Nama Sheet' => [ [kolom0, kolom1, ...], ... ], ...] — urutan sesuai urutan sheet di file
 * @throws Exception
 */
function readXlsxAllSheets($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception("File tidak ditemukan: {$filePath}");
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new Exception("Gagal membuka file XLSX (bukan file Excel valid).");
    }

    // 1. Baca shared strings
    $sharedStrings = _xlsxReadSharedStrings($zip);

    // 2. Baca daftar sheet (nama + r:id) dari workbook.xml, urut sesuai file
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    if ($workbookXml === false) {
        $zip->close();
        throw new Exception("File XLSX tidak valid: xl/workbook.xml tidak ditemukan.");
    }
    $workbook = simplexml_load_string($workbookXml);
    $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

    $sheetList = []; // [ ['name' => ..., 'rid' => ...], ... ]
    foreach ($workbook->sheets->sheet as $sheet) {
        $attrs = $sheet->attributes('r', true);
        $sheetList[] = [
            'name' => (string) $sheet['name'],
            'rid' => (string) $attrs['id'],
        ];
    }

    // 3. Baca mapping r:id -> file worksheet dari xl/_rels/workbook.xml.rels
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    $ridToFile = [];
    if ($relsXml !== false) {
        $rels = simplexml_load_string($relsXml);
        foreach ($rels->Relationship as $rel) {
            $ridToFile[(string) $rel['Id']] = (string) $rel['Target'];
        }
    }

    // 4. Parse tiap sheet
    $result = [];
    foreach ($sheetList as $sheetInfo) {
        $target = $ridToFile[$sheetInfo['rid']] ?? null;
        if (!$target) {
            continue;
        }
        // Target biasanya "worksheets/sheet1.xml", path lengkap di zip: xl/worksheets/sheet1.xml
        $sheetPath = 'xl/' . ltrim($target, '/');
        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false) {
            continue;
        }

        $result[$sheetInfo['name']] = _xlsxParseSheetXml($sheetXml, $sharedStrings);
    }

    $zip->close();
    return $result;
}

/**
 * Baca file XLSX, kembalikan isi sheet PERTAMA saja (untuk kompatibilitas lama).
 */
function readXlsxRows($filePath) {
    $sheets = readXlsxAllSheets($filePath);
    return reset($sheets) ?: [];
}

function _xlsxReadSharedStrings($zip) {
    $sharedStrings = [];
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedStringsXml === false) {
        return $sharedStrings;
    }
    $sst = simplexml_load_string($sharedStringsXml);
    if ($sst === false) {
        return $sharedStrings;
    }
    foreach ($sst->si as $si) {
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
    return $sharedStrings;
}

function _xlsxParseSheetXml($sheetXml, $sharedStrings) {
    $sheet = simplexml_load_string($sheetXml);
    if ($sheet === false || !isset($sheet->sheetData->row)) {
        return [];
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $rowData = [];
        $lastColIndex = -1;

        foreach ($row->c as $cell) {
            $ref = (string) $cell['r'];
            $colLetters = preg_replace('/[0-9]/', '', $ref);
            $colIndex = xlsxColumnLetterToIndex($colLetters);

            while ($lastColIndex < $colIndex - 1) {
                $lastColIndex++;
                $rowData[$lastColIndex] = '';
            }

            $type = (string) $cell['t'];
            $value = isset($cell->v) ? (string) $cell->v : '';

            if ($type === 's') {
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

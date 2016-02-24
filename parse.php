#!/usr/bin/env php
<?php

function generateId($type, $code)
{
    return hash('sha256', sprintf('%s-%s', $type, $code));
}

function cleanString($string)
{
    return utf8_encode(trim($string));
}

function generateInsertRow($type, $place)
{
    return sprintf("('%s', %s, '%s', '%s', '%s')",
        $place['id'],
        $place['parent_id'] ? "'{$place['parent_id']}'" : 'null',
        $place['code'],
        $type,
        str_replace("'", "\'", $place['name'])
    );
}

// ---------------------------------------------------------------------------------------------------------------------

if ($argc == 1) {
    exit('ERROR: Missing source file path' . "\n");
}

$filePath = $argv[1];
if (!file_exists($filePath)) {
    exit('ERROR: File is missing or is inaccessible' . "\n");
}

$handle = fopen($filePath, 'r');
if (false === $handle) {
    exit('ERROR: Unable to open the source file' . "\n");
}

$regions = [];
$provinces = [];
$cities = [];

$firstRow = true;
while (false !== ($data = fgetcsv($handle, 0, ';'))) {
    if ($firstRow) {
        $firstRow = false;
        continue;
    }

    if (empty($data[0])) {
        continue;
    }

    $regionCode = trim($data[0]);
    if (!isset($regions[$regionCode])) {
        $regions[$regionCode] = [
            'id' => generateId('region', $regionCode),
            'parent_id' => null,
            'code' => $regionCode,
            'name' => cleanString($data[9]),
        ];
    }

    $provinceCode = trim($data[2]);
    if (!isset($provinces[$provinceCode])) {
        $provinceName = cleanString($data[11]);
        if ('-' == $provinceName) {
            $provinceName = cleanString($data[10]);
        }

        $provinces[$provinceCode] = [
            'id' => generateId('province', $provinceCode),
            'parent_id' => $regions[$regionCode]['id'],
            'code' => $provinceCode,
            'name' => $provinceName,
        ];
    }

    $cityCode = trim($data[4]);
    $cities[$cityCode] = [
        'id' => generateId('city', $cityCode),
        'parent_id' => $provinces[$provinceCode]['id'],
        'code' => $cityCode,
        'name' => cleanString($data[5]),
    ];
}
fclose($handle);

$createTableSql = <<<EOT
CREATE TABLE `geographical_places` (
  `id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `parent_id` varchar(255) COLLATE utf8_unicode_ci NULL DEFAULT NULL,
  `code` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  CONSTRAINT pk_id PRIMARY KEY (`id`),
  CONSTRAINT uc_code_type UNIQUE (`code`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOT;

$inserts = [];
foreach ($regions as $place) {
    $inserts[] = generateInsertRow('region', $place);
}
foreach ($provinces as $place) {
    $inserts[] = generateInsertRow('province', $place);
}
foreach ($cities as $place) {
    $inserts[] = generateInsertRow('city', $place);
}

$insertSql = sprintf("INSERT INTO `geographical_places` (`id`, `parent_id`, `code`, `type`, `name`) VALUES\n%s;", implode(",\n", $inserts));

echo $createTableSql . "\n\n";
echo $insertSql . "\n";
exit();

<?php
/*
 * MODX Vapor — port for MODX 3.x / xPDO 3 / PHP 8.2+
 *
 * Based on MODX Vapor, Copyright 2012 by MODX, LLC.
 * https://github.com/MODX-Club/vapor
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 */

/**
 * @var xPDOTransport $transport
 * @var modSystemSetting $object
 * @var array $options
 * @var array $fileMeta
 */
$results = array();
if (isset($fileMeta['classes'])) {
    foreach ($fileMeta['classes'] as $class) {
        $tableName = $transport->xpdo->getTableName($class);
        if (!empty($tableName)) {
            $results[$class] = $transport->xpdo->exec('TRUNCATE TABLE ' . $tableName);
        }
    }
}
$transport->xpdo->log(xPDO::LOG_LEVEL_INFO, "Table truncation results: " . print_r($results, true));
return !array_search(false, $results, true);
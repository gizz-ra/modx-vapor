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
 * @var modMediaSource $object
 * @var array $fileMeta
 */
$resolved = false;
if ($object instanceof \MODX\Revolution\Sources\modFileMediaSource && isset($fileMeta['target']) && !empty($fileMeta['target'])) {
    if ($object->initialize()) {
        $basePath = $fileMeta['target'];
        $properties = $object->getProperties(true);
        if (isset($fileMeta['targetRelative']) && !empty($fileMeta['targetRelative'])) {
            $properties['basePathRelative']['value'] = true;
            $properties['baseUrlRelative']['value'] = true;
            $properties['baseUrl']['value'] = ltrim($basePath, '/');
        }
        if (isset($fileMeta['targetPrepend']) && !empty($fileMeta['targetPrepend'])) {
            $properties['basePath']['value'] = eval($fileMeta['targetPrepend']) . ltrim($basePath, '/');
        } else {
            $properties['basePath']['value'] = $basePath;
        }
        if ($object->setProperties($properties)) {
            $resolved = $object->save();
        } else {
            $transport->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error saving media source properties: " . print_r($object->getPropertyList(), true));
        }
    } else {
        $transport->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error initializing media source!");
    }
} else {
    $transport->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Resolver attached to invalid media source with options: " . print_r($fileMeta, true));
}
return $resolved;
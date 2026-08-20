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
 * @var array $object
 * @var array $fileMeta
 */
$transport->xpdo->loadClass('vapor.vaporVehicle', MODX_CORE_PATH . 'components/vapor/model/', true, true);
return true;
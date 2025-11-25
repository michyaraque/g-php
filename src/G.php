<?php

declare(strict_types=1);

require_once __DIR__ . '/Protocol.php';
require_once __DIR__ . '/Extension.php';
require_once __DIR__ . '/Util/PacketGenerator.php';
require_once __DIR__ . '/Util/Scheduler.php';

if (file_exists(__DIR__ . '/Packets.php')) {
    require_once __DIR__ . '/Packets.php';
}

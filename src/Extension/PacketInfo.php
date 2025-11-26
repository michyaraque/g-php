<?php

declare(strict_types=1);

namespace GPHP\Extension;

use GPHP\Protocol\PacketDirection;

readonly class PacketInfo {
    public function __construct(
        public PacketDirection $destination,
        public int $headerId,
        public string $hash,
        public string $name,
        public string $structure,
        public string $source
    ) {
    }
}

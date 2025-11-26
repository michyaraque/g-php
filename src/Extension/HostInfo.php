<?php

declare(strict_types=1);

namespace GPHP\Extension;

readonly class HostInfo {
    public function __construct(
        public string $host,
        public int $port,
        public string $version,
        public string $clientIdentifier,
        public string $clientType
    ) {
    }
}

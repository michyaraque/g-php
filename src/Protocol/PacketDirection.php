<?php

declare(strict_types=1);

namespace GPHP\Protocol;

enum PacketDirection: string {
    case TO_CLIENT = 'TOCLIENT';
    case TO_SERVER = 'TOSERVER';
}

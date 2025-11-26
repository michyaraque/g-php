<?php

declare(strict_types=1);

namespace GPHP\Protocol;

enum GEarthIncomingPacket: int {
    case ON_DOUBLE_CLICK = 1;
    case INFO_REQUEST = 2;
    case PACKET_INTERCEPT = 3;
    case FLAGS_CHECK = 4;
    case CONNECTION_START = 5;
    case CONNECTION_END = 6;
    case INIT = 7;
    case PACKET_TO_STRING_RESPONSE = 20;
    case STRING_TO_PACKET_RESPONSE = 21;
}

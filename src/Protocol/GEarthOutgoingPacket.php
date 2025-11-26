<?php

declare(strict_types=1);

namespace GPHP\Protocol;

enum GEarthOutgoingPacket: int {
    case INFO = 1;
    case MANIPULATED_PACKET = 2;
    case REQUEST_FLAGS = 3;
    case SEND_MESSAGE = 4;
    case PACKET_TO_STRING = 20;

    case STRING_TO_PACKET = 21;
    case EXTENSION_CONSOLE_LOG = 98;
}

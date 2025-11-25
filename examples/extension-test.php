<?php

require_once __DIR__ . '/../src/G.php';

use GPHP\Extension\{Extension, ExtensionInfo, HostInfo};
use GPHP\Protocol\{HMessage, HPacket};
use GPHP\Packets\Outgoing;

$extInfo = new ExtensionInfo(
    title: "PHP Extension",
    description: "G-PHP Extension",
    version: "0.0.1",
    author: "Cebolla1"
);

$ext = new Extension($extInfo);

$ext->onConnect(function (HostInfo $hostInfo) {
    echo " >> Game Connected: $hostInfo->host\n";
});

$ext->intercept(Outgoing::Chat, function (HMessage $hMessage) use ($ext) {
    $hPacket = $hMessage->getPacket();
    $text = $hPacket->readString();
    $hPacket->read('ii');

    if (str_contains($text, ":walkto")) {
        [$_, $posx, $posy] = explode(" ", $text);
        $hMessage->setBlocked(true);

        $packet = new HPacket(Outgoing::MoveAvatar);
        $packet->appendInt((int) $posx);
        $packet->appendInt((int) $posy);
        $ext->sendPacket($packet);
    }

    if (str_contains($text, ":place")) {
        $hMessage->setBlocked(true);

        \GPHP\Util\Scheduler::run(function () use ($ext) {
            for ($i = 0; $i < 30; $i++) {
                $packet = new HPacket(Outgoing::BuildersClubPlaceRoomItem);
                $packet->appendInt(1838264);
                $packet->appendInt(8722);
                $packet->appendString("");
                $packet->appendInt(3);
                $packet->appendInt(7);
                $packet->appendInt(0);
                $packet->appendBool(false);

                $ext->sendPacket($packet);

                $start = microtime(true);
                while (microtime(true) - $start < 0.9) {
                    \Fiber::suspend();
                }
            }
        });
    }
});

$ext->run();

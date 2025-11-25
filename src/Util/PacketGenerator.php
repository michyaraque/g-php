<?php

declare(strict_types=1);

namespace GPHP\Util;

use GPHP\Extension\PacketInfoManager;
use GPHP\Protocol\PacketDirection;

class PacketGenerator {
    public static function generate(PacketInfoManager $manager): void {
        $incoming = [];
        $outgoing = [];

        foreach ($manager->getAll() as $info) {
            $name = preg_replace('/[^a-zA-Z0-9_]/', '', $info->name);
            if (empty($name) || is_numeric($name[0])) {
                $name = "Packet_" . $name;
            }

            if ($info->destination === PacketDirection::TO_CLIENT) {
                $incoming[$name] = $info->name;
            } else {
                $outgoing[$name] = $info->name;
            }
        }

        $code = "<?php\n\ndeclare(strict_types=1);\n\nnamespace GPHP\\Packets;\n\n";

        $code .= "enum Incoming: string {\n";
        foreach ($incoming as $case => $value) {
            $code .= "    case $case = '$value';\n";
        }
        $code .= "}\n\n";

        $code .= "enum Outgoing: string {\n";
        foreach ($outgoing as $case => $value) {
            $code .= "    case $case = '$value';\n";
        }
        $code .= "}\n";

        $file = __DIR__ . '/../Packets.php';

        file_put_contents($file, $code);
    }
}

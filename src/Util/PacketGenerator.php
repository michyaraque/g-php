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

        self::writeEnum('Incoming', $incoming);
        self::writeEnum('Outgoing', $outgoing);
    }

    private static function writeEnum(string $className, array $cases): void {
        $code = "<?php\n\ndeclare(strict_types=1);\n\nnamespace GPHP\\Packets;\n\n";
        $code .= "enum $className: string {\n";
        foreach ($cases as $case => $value) {
            $code .= "    case $case = '$value';\n";
        }
        $code .= "}\n";

        $file = __DIR__ . "/../Packets/$className.php";
        file_put_contents($file, $code);
    }
}

<?php

namespace App\Support;

final class QueueNames
{
    /**
     * Queue names are a wire-protocol boundary during rolling deployments.
     * Increment the suffix whenever a serialized job payload becomes
     * incompatible with workers from the previous release.
     */
    public const BACKUPS = 'backups-v1';

    public const IMPORTS = 'imports-v3';

    public const EXPORTS = 'exports-v3';

    public const DEFAULT = 'default';

    public static function monitored(): array
    {
        return [self::BACKUPS, self::DEFAULT, self::IMPORTS, self::EXPORTS];
    }
}

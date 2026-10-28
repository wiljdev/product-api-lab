<?php

namespace App\Enums;

enum UserRole: string
{
    case Editor = 'editor';
    case Viewer = 'viewer';

    /**
     * The Sanctum token abilities granted to users with this role.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Editor => [TokenAbility::ProductsWrite->value],
            self::Viewer => [],
        };
    }
}

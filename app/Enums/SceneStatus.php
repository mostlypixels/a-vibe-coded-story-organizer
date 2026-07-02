<?php

namespace App\Enums;

enum SceneStatus: string
{
    case Draft = 'draft';
    case ToProofread = 'to_proofread';
    case ToEdit = 'to_edit';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::ToProofread => 'To Proofread',
            self::ToEdit => 'To Edit',
            self::Final => 'Final',
        };
    }
}

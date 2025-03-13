<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use ilSession;

/**
 * User preferences
 * Currently for all limited media in a session
 */
class PreferencesRepo
{
    /**
     * Get the volume setting for the player (0 to 1)
     */
    public function getVolume(): float
    {
        return (float) (ilSession::get('limply_volume') ?? 0.5);
    }

    /**
     * Update the volume setting of the playwer (0 to 1)
     */
    public function updateVolume(float $volume): void
    {
        ilSession::set('limply_volume', $volume);
    }
}

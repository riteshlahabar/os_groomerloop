<?php

namespace Modules\Reviews\Contracts;

/**
 * Where a review request should point (spec §20), for any module that sends one without
 * knowing how Reviews stores its configuration (D-007). Automation is the first caller.
 */
interface ReviewDestinations
{
    /**
     * The one link an automated review request should carry, or null if the business has not
     * configured any — the message still sends, just without a link, the same honest-gap shape
     * every other unconfigured lookup in this product already has.
     */
    public function primaryUrl(): ?string;
}

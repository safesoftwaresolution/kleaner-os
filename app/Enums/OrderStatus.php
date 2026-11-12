<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Queued = 'queued';
    case Washing = 'washing';
    case Ready = 'ready';
    case Delivered = 'delivered';

    /** The only status an order may move to from this one. */
    public function next(): ?self
    {
        return match ($this) {
            self::Queued => self::Washing,
            self::Washing => self::Ready,
            self::Ready => self::Delivered,
            self::Delivered => null,
        };
    }
}

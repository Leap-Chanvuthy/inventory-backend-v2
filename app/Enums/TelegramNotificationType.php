<?php

namespace App\Enums;

enum TelegramNotificationType: string
{
    case INVENTORY = 'inventory';
    case SALE = 'sale';
    case PURCHASE = 'purchase';

    /**
     * Map enum to database columns
     */
    public function columnName(): string
    {
        return match ($this) {
            self::INVENTORY => 'telegram_inventory_chat_id',
            self::SALE      => 'telegram_sale_chat_id',
            self::PURCHASE  => 'telegram_purchase_chat_id',
        };
    }
}

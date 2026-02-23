<?php 
namespace App\Helpers;

use App\Enums\PaymentMethodEnum;

class GetBankingLabel
{
    public function getPaymentMethodLabel(string $method): string
    {
        return match ($method) {
            PaymentMethodEnum::ABA->value   => "https://yt3.googleusercontent.com/ytc/AIdro_ljV-vXKHv8x9yHY_Z6RuI9jutIh6f8D0O1oYIY43fJiNo=s900-c-k-c0x00ffffff-no-rj",
            PaymentMethodEnum::ACLEDA->value => "https://www.acledabank.com.kh/kh/assets/layout/logo1.png",
            PaymentMethodEnum::WING->value  => "https://play-lh.googleusercontent.com/A8bangMCdTPS1Xa9hbuc4pcXxUspKpJhDHWW3QSw3OB-VMtUv6NCnqAd7pUv2C-2OnjJHn0Xmv1cs6c2hFUZMw",
            PaymentMethodEnum::BAKONG->value  => "https://api.nuget.org/v3-flatcontainer/kh.gov.nbc.bakongkhqr/1.0.0.15/icon",
            default => "",
        };
    }
}
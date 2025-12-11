<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseImageFactory extends Factory
{
    protected $model = \App\Models\WarehouseImage::class;

    public function definition()
    {   
        $randomImageUrl = [
            'https://t3.ftcdn.net/jpg/01/80/89/82/360_F_180898288_sauzWwTvjVdmnUcz6Oy56MFYQBqi5EDn.jpg',
            'https://plus.unsplash.com/premium_photo-1661302828763-4ec9b91d9ce3?fm=jpg&q=60&w=3000&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MXx8aW5kdXN0cmlhbCUyMHdhcmVob3VzZXxlbnwwfHwwfHx8MA%3D%3D',
            'https://images.pexels.com/photos/4483610/pexels-photo-4483610.jpeg?cs=srgb&dl=pexels-tiger-lily-4483610.jpg&fm=jpg',
            'https://www.elementlogic.net/content/uploads/sites/8/2024/10/for-4-main-functions-of-warehouse-1200x649-1.jpg',
            'https://elements-resized.envatousercontent.com/envato-dam-assets-production/EVA/TRX/4b/63/6b/b5/f4/v1_E10/E10A3KMI.jpg?w=500&cf_fit=scale-down&mark-alpha=18&mark=https%3A%2F%2Felements-assets.envato.com%2Fstatic%2Fwatermark4.png&q=85&format=auto&s=758762b2e081699bbad5cf6f6d74df626ada0d423a552efc26a2dbc357dbe84e',
            'https://www.smart-academy.in/wp-content/uploads/2024/06/warehouse-without-title.png',
            'https://www.dachser.com.bd/images/Corporate/DGI_003542_zugeschnitten_rdax_65s.jpg',
            'https://yenaengineering.nl/wp-content/uploads/2022/08/Temporary-warehouse-min-1038x693.jpg',
            'https://ware2go.co/wp-content/uploads/2024/03/image2.png',
            'https://www.dachser.com/en/mediaroom/images/Germany/Warehousing/Exoskelette_im_Warehouse_rdax_65s.jpg',
            'https://www.dachser.com/en/mediaroom/images/Corporate/DACHSER%20eLetter/20230502_providing_aid_in_the_network_me_rdax_65s.jpg',
            'https://cloudfront-eu-central-1.images.arcpublishing.com/madsack/ABDG56I6PMW5TBV4BKPZ4EQUMA.jpg',
            'https://www.haz.de/resizer/v2/V5W2X6AB2HNHQPFESNX6Z444EA.jpg?auth=5769addd44417324f5e40ecbe00bf74e1661e4a5f008792b9e2b26783b902e7c&quality=70&width=428',
        ];

        return [
            'image' => $this->faker->randomElement($randomImageUrl),
        ];
    }
}

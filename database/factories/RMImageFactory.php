<?php

namespace Database\Factories;

use App\Models\RMImage;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RMImage>
 */
class RMImageFactory extends Factory
{
    protected $model = RMImage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // NOTE: rm_images.image is a VARCHAR (string) column. Keep URLs short to avoid SQLSTATE[22001].
        $seed = $this->faker->numberBetween(1, 9999);
        $randomImageUrl = [
            "https://api.dicebear.com/9.x/shapes/svg?seed={$seed}",
            "https://api.dicebear.com/9.x/identicon/svg?seed={$seed}",
            "https://api.dicebear.com/9.x/adventurer/svg?seed={$seed}",
            "https://api.dicebear.com/9.x/bottts/svg?seed={$seed}",
        ];

        $rawMaterialId = RawMaterial::query()->inRandomOrder()->value('id')
            ?? RawMaterial::factory()->create()->id;

        return [
            'raw_material_id' => $rawMaterialId,
            'image' => $this->faker->randomElement($randomImageUrl),
        ];
    }

    public function forRawMaterialId(int $rawMaterialId): static
    {
        return $this->state(fn () => [
            'raw_material_id' => $rawMaterialId,
        ]);
    }

    /**
     * Ensure a raw material has at least N images.
     * Useful in seeders: RMImageFactory::ensureMinimumForRawMaterial($rm->id, 3);
     */
    public static function ensureMinimumForRawMaterial(int $rawMaterialId, int $minImages = 3): void
    {
        $existing = RMImage::query()->where('raw_material_id', $rawMaterialId)->count();
        $missing = max(0, $minImages - $existing);

        if ($missing > 0) {
            RMImage::factory()->count($missing)->forRawMaterialId($rawMaterialId)->create();
        }
    }
}

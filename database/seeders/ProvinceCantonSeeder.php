<?php

namespace Database\Seeders;

use App\Models\Canton;
use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceCantonSeeder extends Seeder
{
    /**
     * Costa Rica's 7 provinces and their cantons. Idempotent, so it's safe to re-run.
     */
    public function run(): void
    {
        $data = [
            'San José' => [
                'San José', 'Escazú', 'Desamparados', 'Puriscal', 'Tarrazú', 'Aserrí', 'Mora',
                'Goicoechea', 'Santa Ana', 'Alajuelita', 'Vázquez de Coronado', 'Acosta',
                'Tibás', 'Moravia', 'Montes de Oca', 'Turrubares', 'Dota', 'Curridabat',
                'Pérez Zeledón', 'León Cortés Castro',
            ],
            'Alajuela' => [
                'Alajuela', 'San Ramón', 'Grecia', 'San Mateo', 'Atenas', 'Naranjo', 'Palmares',
                'Poás', 'Orotina', 'San Carlos', 'Zarcero', 'Valverde Vega', 'Upala',
                'Los Chiles', 'Guatuso', 'Río Cuarto',
            ],
            'Cartago' => [
                'Cartago', 'Paraíso', 'La Unión', 'Jiménez', 'Turrialba', 'Alvarado', 'Oreamuno',
                'El Guarco',
            ],
            'Heredia' => [
                'Heredia', 'Barva', 'Santo Domingo', 'Santa Bárbara', 'San Rafael', 'San Isidro',
                'Belén', 'Flores', 'San Pablo', 'Sarapiquí',
            ],
            'Guanacaste' => [
                'Liberia', 'Nicoya', 'Santa Cruz', 'Bagaces', 'Carrillo', 'Cañas', 'Abangares',
                'Tilarán', 'Nandayure', 'La Cruz', 'Hojancha',
            ],
            'Puntarenas' => [
                'Puntarenas', 'Esparza', 'Buenos Aires', 'Montes de Oro', 'Osa', 'Aguirre',
                'Golfito', 'Coto Brus', 'Parrita', 'Corredores', 'Garabito', 'Monteverde',
                'Puerto Jiménez',
            ],
            'Limón' => [
                'Limón', 'Pococí', 'Siquirres', 'Talamanca', 'Matina', 'Guácimo',
            ],
        ];

        $provinceOrder = 0;

        foreach ($data as $provinceName => $cantons) {
            $provinceOrder++;

            $province = Province::updateOrCreate(
                ['name' => $provinceName],
                ['display_order' => $provinceOrder],
            );

            foreach (array_values($cantons) as $cantonIndex => $cantonName) {
                Canton::updateOrCreate(
                    ['province_id' => $province->id, 'name' => $cantonName],
                    ['display_order' => $cantonIndex + 1],
                );
            }
        }
    }
}

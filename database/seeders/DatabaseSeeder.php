<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 100% MySQL compatible - uses standard Laravel Eloquent.
     */
    public function run(): void
    {
        // ═══════════════════════════════════════════════════════════
        // ADMIN & STAFF USERS
        // ═══════════════════════════════════════════════════════════
        User::create([
            'name'      => 'Admin',
            'email'     => 'admin@foodhub.com',
            'password'  => Hash::make('admin123'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        User::create([
            'name'      => 'Staff Member',
            'email'     => 'staff@foodhub.com',
            'password'  => Hash::make('staff123'),
            'role'      => 'staff',
            'is_active' => true,
        ]);

        // ═══════════════════════════════════════════════════════════
        // CATEGORIES
        // ═══════════════════════════════════════════════════════════
        $categories = [
            ['name' => 'Burgers',  'icon' => '🍔'],
            ['name' => 'Pizza',    'icon' => '🍕'],
            ['name' => 'Sushi',    'icon' => '🍣'],
            ['name' => 'Salads',   'icon' => '🥗'],
            ['name' => 'Desserts', 'icon' => '🍰'],
            ['name' => 'Drinks',   'icon' => '🥤'],
        ];

        $categoryModels = [];
        foreach ($categories as $cat) {
            $categoryModels[] = Category::create($cat);
        }

        // ═══════════════════════════════════════════════════════════
        // PRODUCTS (14 items across 6 categories)
        // ═══════════════════════════════════════════════════════════
        $products = [
            // BURGERS
            [
                'cat'         => 0,
                'name'        => 'Classic Cheeseburger',
                'price'       => 350,
                'popular'     => true,
                'prep'        => 15,
                'cal'         => 540,
                'ingredients' => ['Beef patty', 'Cheddar', 'Lettuce', 'Tomato', 'Pickles', 'Special sauce']
            ],
            [
                'cat'         => 0,
                'name'        => 'Double Bacon Burger',
                'price'       => 520,
                'popular'     => false,
                'prep'        => 18,
                'cal'         => 820,
                'ingredients' => ['Double beef', 'Bacon', 'American cheese', 'Onion rings', 'BBQ sauce']
            ],
            [
                'cat'         => 0,
                'name'        => 'Veggie Burger',
                'price'       => 280,
                'popular'     => true,
                'prep'        => 12,
                'cal'         => 390,
                'ingredients' => ['Veggie patty', 'Lettuce', 'Tomato', 'Avocado', 'Pesto']
            ],

            // PIZZA
            [
                'cat'         => 1,
                'name'        => 'Margherita Pizza',
                'price'       => 420,
                'popular'     => true,
                'prep'        => 20,
                'cal'         => 680,
                'ingredients' => ['Mozzarella', 'Tomato sauce', 'Fresh basil', 'Olive oil']
            ],
            [
                'cat'         => 1,
                'name'        => 'Pepperoni Pizza',
                'price'       => 580,
                'popular'     => true,
                'prep'        => 22,
                'cal'         => 900,
                'ingredients' => ['Pepperoni', 'Mozzarella', 'Tomato sauce', 'Oregano']
            ],
            [
                'cat'         => 1,
                'name'        => 'Chicken BBQ Pizza',
                'price'       => 620,
                'popular'     => false,
                'prep'        => 25,
                'cal'         => 850,
                'ingredients' => ['Grilled chicken', 'BBQ sauce', 'Red onion', 'Mozzarella', 'Cilantro']
            ],

            // SUSHI
            [
                'cat'         => 2,
                'name'        => 'Salmon Nigiri (6 pcs)',
                'price'       => 680,
                'popular'     => true,
                'prep'        => 10,
                'cal'         => 420,
                'ingredients' => ['Fresh salmon', 'Sushi rice', 'Wasabi', 'Nori']
            ],
            [
                'cat'         => 2,
                'name'        => 'Dragon Roll (8 pcs)',
                'price'       => 750,
                'popular'     => false,
                'prep'        => 12,
                'cal'         => 510,
                'ingredients' => ['Shrimp', 'Avocado', 'Cucumber', 'Unagi', 'Sushi rice']
            ],

            // SALADS
            [
                'cat'         => 3,
                'name'        => 'Grilled Chicken Salad',
                'price'       => 320,
                'popular'     => true,
                'prep'        => 8,
                'cal'         => 280,
                'ingredients' => ['Chicken breast', 'Mixed greens', 'Cherry tomatoes', 'Croutons', 'Caesar dressing']
            ],
            [
                'cat'         => 3,
                'name'        => 'Caesar Salad',
                'price'       => 260,
                'popular'     => false,
                'prep'        => 5,
                'cal'         => 220,
                'ingredients' => ['Romaine', 'Parmesan', 'Croutons', 'Caesar dressing', 'Lemon']
            ],

            // DESSERTS
            [
                'cat'         => 4,
                'name'        => 'Chocolate Lava Cake',
                'price'       => 290,
                'popular'     => true,
                'prep'        => 15,
                'cal'         => 480,
                'ingredients' => ['Dark chocolate', 'Butter', 'Eggs', 'Sugar', 'Vanilla']
            ],
            [
                'cat'         => 4,
                'name'        => 'Tiramisu',
                'price'       => 270,
                'popular'     => false,
                'prep'        => 5,
                'cal'         => 350,
                'ingredients' => ['Mascarpone', 'Espresso', 'Ladyfingers', 'Cocoa', 'Eggs']
            ],

            // DRINKS
            [
                'cat'         => 5,
                'name'        => 'Fresh Mango Juice',
                'price'       => 120,
                'popular'     => true,
                'prep'        => 3,
                'cal'         => 95,
                'ingredients' => ['Fresh mango', 'Water', 'Sugar', 'Lemon']
            ],
            [
                'cat'         => 5,
                'name'        => 'Iced Latte',
                'price'       => 160,
                'popular'     => false,
                'prep'        => 5,
                'cal'         => 180,
                'ingredients' => ['Espresso', 'Milk', 'Ice', 'Vanilla syrup']
            ],
        ];

        // Create all products
        foreach ($products as $p) {
            Product::create([
                'category_id'      => $categoryModels[$p['cat']]->id,
                'name'             => $p['name'],
                'description'      => "Delicious {$p['name']} prepared fresh daily.",
                'price'            => $p['price'],
                'image_url'        => null,
                'preparation_time' => $p['prep'],
                'calories'         => $p['cal'],
                'ingredients'      => json_encode($p['ingredients']), // MySQL stores as JSON
                'is_popular'       => $p['popular'],
                'is_available'     => true,
            ]);
        }

        $this->command->info('✅ Database seeded successfully!');
        $this->command->info('✅ Created: 2 admin users, 6 categories, 14 products');
        $this->command->info('');
        $this->command->info('🔑 Login Credentials:');
        $this->command->info('   Admin: admin@foodhub.com / admin123');
        $this->command->info('   Staff: staff@foodhub.com / staff123');
    }
}
<?php

use App\Modules\Blogs\Database\Seeders\BlogsSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendPageSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendSectionSeeder;
use Database\Seeders\DatabaseSeeder;

it('includes SaaS-safe frontend and blog seeders in the main database seeder', function (): void {
    $seeder = new class extends DatabaseSeeder
    {
        /** @var array<int, string> */
        public array $calledSeeders = [];

        public function call($class, $silent = false, array $parameters = []): static
        {
            $this->calledSeeders = is_array($class) ? $class : [$class];

            return $this;
        }
    };

    $seeder->run();

    expect($seeder->calledSeeders)->toContain(BlogsSeeder::class, FrontendSectionSeeder::class, FrontendPageSeeder::class);
});

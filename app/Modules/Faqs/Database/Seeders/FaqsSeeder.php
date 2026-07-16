<?php

namespace App\Modules\Faqs\Database\Seeders;

use App\Modules\Faqs\Models\Faq;
use Illuminate\Database\Seeder;

class FaqsSeeder extends Seeder
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'question' => 'How do you structure a new project kickoff?',
                'answer' => 'We begin with a discovery sprint to align on goals, constraints, users, and delivery scope. That sprint ends with a prioritized roadmap, milestones, and a shared definition of done.',
                'sort_order' => 1,
            ],
            [
                'question' => 'How often will we see progress during delivery?',
                'answer' => 'Most engagements run in one or two week sprints with regular demos, written updates, and direct access to the people building the work so feedback stays fast and concrete.',
                'sort_order' => 2,
            ],
            [
                'question' => 'Do you work on fixed scope or monthly retainers?',
                'answer' => 'We support both. Fixed scope works well when deliverables are well defined, while retainers fit ongoing product, design, or growth work that benefits from continuous iteration.',
                'sort_order' => 3,
            ],
            [
                'question' => 'Can you help us estimate budget before a full engagement?',
                'answer' => 'Yes. We can start with a short scoping engagement or advisory workshop to clarify complexity, timeline, and likely budget ranges before committing to a larger build.',
                'sort_order' => 4,
            ],
            [
                'question' => 'Who owns the code, designs, and deliverables after launch?',
                'answer' => 'You do. Once the work is delivered and paid for, your team retains ownership of the agreed deliverables, including repositories, assets, and documentation.',
                'sort_order' => 5,
            ],
            [
                'question' => 'Do you provide post-launch support?',
                'answer' => 'Yes. We can provide a structured support window after launch and longer-term retainers for maintenance, improvements, analytics, and roadmap execution.',
                'sort_order' => 6,
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $definition) {
            Faq::query()->updateOrCreate(
                [
                    'question' => $definition['question'],
                ],
                [
                    'answer' => $definition['answer'],
                    'sort_order' => $definition['sort_order'],
                    'active' => true,
                    'status' => 'published',
                    'published_at' => now(),
                ]
            );
        }
    }
}

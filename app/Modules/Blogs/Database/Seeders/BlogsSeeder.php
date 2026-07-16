<?php

namespace App\Modules\Blogs\Database\Seeders;

use App\Modules\Blogs\Models\Blog;
use App\Modules\Blogs\Models\BlogCategory;
use Illuminate\Database\Seeder;

class BlogsSeeder extends Seeder
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function categoryDefinitions(): array
    {
        return [
            [
                'name' => 'Automation',
                'slug' => 'automation',
                'description' => 'Practical guides for WhatsApp automation, routing, and team workflows.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'description' => 'Broadcast campaigns, segmentation, and customer lifecycle messaging.',
                'sort_order' => 2,
            ],
            [
                'name' => 'Product',
                'slug' => 'product',
                'description' => 'CRM dashboards, reporting, and product strategy for WhatsApp teams.',
                'sort_order' => 3,
            ],
            [
                'name' => 'Support',
                'slug' => 'support',
                'description' => 'Customer support, chatbots, and handoff best practices.',
                'sort_order' => 4,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'title' => 'How WhatsApp Automation Helps SaaS Teams Reply Faster',
                'slug' => 'whatsapp-automation-saas-teams-reply-faster',
                'category_slug' => 'automation',
                'author_name' => 'WaPro Editorial',
                'excerpt' => 'A practical guide to using automation, routing, and saved replies without losing the human feel of customer conversations.',
                'content' => "Fast replies are not only about speed. They are about context, routing, and giving your team the right next action before the customer asks twice.\n\nA strong WhatsApp automation setup starts with clear entry points. Welcome messages, qualification questions, and intent-based routing help every conversation land with the right owner.\n\nFor SaaS teams, the most useful automations are usually small. Trial questions, billing nudges, onboarding reminders, renewal prompts, and support triage can remove hours of repetitive work each week.\n\nThe best systems still leave space for humans. Automation should prepare the conversation, summarize the need, and hand off cleanly when a customer needs a personal answer.",
                'featured_image' => 'assets/images/sections/solutions/1.webp',
                'read_time_minutes' => 5,
                'sort_order' => 1,
                'meta_title' => 'WhatsApp Automation for SaaS Teams - WaPro Blog',
                'meta_description' => 'Learn how SaaS teams can use WhatsApp automation to reply faster while keeping customer conversations personal.',
            ],
            [
                'title' => 'Building Broadcast Campaigns That Customers Actually Read',
                'slug' => 'building-broadcast-campaigns-customers-read',
                'category_slug' => 'growth',
                'author_name' => 'WaPro Growth Team',
                'excerpt' => 'Better segmentation, cleaner copy, and timing can turn broadcast campaigns into useful customer touchpoints.',
                'content' => "Broadcast campaigns work when they feel expected. Customers are more likely to read messages that match their lifecycle stage, recent behavior, and stated interests.\n\nStart with segmentation before writing copy. A campaign for new leads should not sound like a campaign for power users, and win-back messages should not look like product updates.\n\nKeep the message focused on one action. A single call to action gives the recipient less to parse and gives your team cleaner performance data after the send.\n\nFinally, measure replies as carefully as clicks. On WhatsApp, a thoughtful reply can be more valuable than a silent visit to a landing page.",
                'featured_image' => 'assets/images/sections/solutions/2.webp',
                'read_time_minutes' => 4,
                'sort_order' => 2,
                'meta_title' => 'Readable WhatsApp Broadcast Campaigns - WaPro Blog',
                'meta_description' => 'Use segmentation, focused copy, and reply tracking to build WhatsApp broadcast campaigns customers read.',
            ],
            [
                'title' => 'What to Track in a WhatsApp CRM Dashboard',
                'slug' => 'what-to-track-whatsapp-crm-dashboard',
                'category_slug' => 'product',
                'author_name' => 'WaPro Product Team',
                'excerpt' => 'The most useful dashboards combine response speed, pipeline movement, campaign outcomes, and customer health.',
                'content' => "A useful WhatsApp CRM dashboard should answer one question quickly: where does the team need to act now?\n\nResponse metrics show whether conversations are being handled on time. Track first response time, open conversations, overdue replies, and ownership by channel.\n\nPipeline metrics show whether conversations are becoming outcomes. Lead source, stage movement, conversion rate, and follow-up completion reveal the health of your sales motion.\n\nCampaign metrics complete the picture. Delivery, replies, opt-outs, and attributed revenue help you decide which messages deserve to be repeated.",
                'featured_image' => 'assets/images/sections/solutions/03.webp',
                'read_time_minutes' => 6,
                'sort_order' => 3,
                'meta_title' => 'WhatsApp CRM Dashboard Metrics - WaPro Blog',
                'meta_description' => 'See which WhatsApp CRM metrics matter for support, sales, campaigns, and customer health.',
            ],
            [
                'title' => 'Using Chatbots Without Making Support Feel Robotic',
                'slug' => 'using-chatbots-without-robotic-support',
                'category_slug' => 'support',
                'author_name' => 'WaPro Support Team',
                'excerpt' => 'Chatbots work best when they handle repetitive structure and hand over gracefully when the conversation gets nuanced.',
                'content' => "A chatbot does not need to pretend to be human. Customers are comfortable with automation when it is clear, useful, and easy to escape.\n\nBegin with narrow flows. Order status, appointment booking, qualification, and common troubleshooting are good candidates because they have predictable branches.\n\nWrite short prompts and give obvious choices. Long bot messages feel heavy inside chat, especially on mobile screens.\n\nMost importantly, design the handoff. A good bot collects context and passes it to a person with the conversation history intact.",
                'featured_image' => 'assets/images/sections/solutions/04.webp',
                'read_time_minutes' => 4,
                'sort_order' => 4,
                'meta_title' => 'Human-Friendly WhatsApp Chatbots - WaPro Blog',
                'meta_description' => 'Design WhatsApp chatbots that automate repetitive support while keeping handoffs smooth and human.',
            ],
        ];
    }

    public function run(): void
    {
        $categories = collect(self::categoryDefinitions())
            ->mapWithKeys(function (array $definition): array {
                $category = BlogCategory::query()->updateOrCreate(
                    ['slug' => $definition['slug']],
                    [
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'sort_order' => $definition['sort_order'],
                        'active' => true,
                    ]
                );

                return [$category->slug => $category];
            });

        foreach (self::definitions() as $definition) {
            Blog::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'blog_category_id' => $categories->get($definition['category_slug'])?->id,
                    'title' => $definition['title'],
                    'author_name' => $definition['author_name'],
                    'excerpt' => $definition['excerpt'],
                    'content' => $definition['content'],
                    'featured_image' => $definition['featured_image'],
                    'featured_image_media_id' => null,
                    'read_time_minutes' => $definition['read_time_minutes'],
                    'sort_order' => $definition['sort_order'],
                    'active' => true,
                    'status' => 'published',
                    'meta_title' => $definition['meta_title'],
                    'meta_description' => $definition['meta_description'],
                    'published_at' => now()->subDays($definition['sort_order'] * 3),
                ]
            );
        }
    }
}

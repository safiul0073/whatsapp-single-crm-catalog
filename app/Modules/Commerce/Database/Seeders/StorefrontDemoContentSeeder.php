<?php

namespace App\Modules\Commerce\Database\Seeders;

use App\Modules\Commerce\Storefront\Models\StorefrontContent;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Seeder;

class StorefrontDemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $workspaces = Workspace::all();
        if ($workspaces->isEmpty()) {
            return;
        }

        foreach ($workspaces as $workspace) {
            // 1. Ensure Storefront Settings have real store data
            $setting = StorefrontSetting::query()->firstOrCreate(
                ['workspace_id' => $workspace->id],
                ['config' => []]
            );

            $config = array_replace([
                'name' => 'Fashion Fiest',
                'primary' => '#0866FF',
                'hover' => '#0055D4',
                'footer_color' => '#0A2540',
                'contact_email' => 'sales@fashionfiest.com',
                'contact_phone' => '+1 (800) 555-0199',
                'footer_text' => 'Direct from factory wholesale and retail streetwear, premium heavyweight hoodies, tees, and denim.',
            ], $setting->config ?? []);

            $setting->update(['config' => $config]);

            // 2. Seed Demo FAQs
            $faqs = [
                [
                    'slug' => 'faq-shipping-rates-times',
                    'question' => 'How long does standard and express shipping take?',
                    'answer' => '<p>Domestic orders are processed within 24 hours. Standard tracked shipping delivers in <strong>3–5 business days</strong>, while express courier delivery arrives in <strong>1–2 business days</strong>. Orders over $75 qualify for complimentary standard shipping automatically at checkout.</p>',
                ],
                [
                    'slug' => 'faq-order-tracking',
                    'question' => 'How can I track my shipment in real time?',
                    'answer' => '<p>As soon as your package is scanned by our carrier, you will receive an email and WhatsApp message with your tracking link. You can also visit our <a href="/track-order">Track Your Order</a> page anytime and enter your order number or tracking reference.</p>',
                ],
                [
                    'slug' => 'faq-international-shipping',
                    'question' => 'Do you offer international worldwide shipping?',
                    'answer' => '<p>Yes! We ship to over 50 countries worldwide. International shipping typically delivers within <strong>6–10 business days</strong> depending on customs clearance in your country.</p>',
                ],
                [
                    'slug' => 'faq-wholesale-pack-builder',
                    'question' => 'How does the custom wholesale garment pack builder work?',
                    'answer' => '<p>Our proprietary <a href="/packs">Custom Packs Builder</a> lets brand owners, streetwear boutiques, and print shops create bundled garment orders (hoodies, t-shirts, fleece joggers) in quantities of 12, 24, 50, or 100 units at direct-from-factory tiered pricing.</p>',
                ],
                [
                    'slug' => 'faq-mix-and-match-packs',
                    'question' => 'Can I mix and match different sizes and colorways in one pack?',
                    'answer' => '<p>Yes! You can select any combination of sizes (S through 3XL) and seasonal colors within your total pack count to perfectly match your brand\'s inventory demand.</p>',
                ],
                [
                    'slug' => 'faq-fabric-gsm-specs',
                    'question' => 'What fabric weights and materials do you use for your apparel?',
                    'answer' => '<p>We specialize in premium heavyweight streetwear blanks. Our t-shirts range from <strong>180 GSM combed compact cotton</strong> to ultra-dense <strong>240 GSM drop-shoulder tees</strong>. Our signature hoodies and fleece sets are crafted from <strong>400–450 GSM 100% luxury French terry cotton</strong>.</p>',
                ],
                [
                    'slug' => 'faq-return-exchange-policy',
                    'question' => 'What is your return and exchange policy?',
                    'answer' => '<p>We offer a <strong>30-day return and exchange window</strong> from the delivery date for all unwashed, unworn garments with original swing tags attached. Size exchanges include free prepaid return shipping labels.</p>',
                ],
                [
                    'slug' => 'faq-accepted-payment-methods',
                    'question' => 'What payment methods do you accept?',
                    'answer' => '<p>We accept Visa, MasterCard, American Express, Apple Pay, Google Pay, and PayPal. For high-volume wholesale custom packs over $1,500, we also support verified bank wire transfers with instant invoice generation.</p>',
                ],
            ];

            foreach ($faqs as $faq) {
                StorefrontContent::query()->updateOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'type' => 'faq',
                        'slug' => $faq['slug'],
                    ],
                    [
                        'data' => [
                            'question' => $faq['question'],
                            'answer' => $faq['answer'],
                        ],
                        'published_at' => now(),
                    ]
                );
            }

            // 3. Seed Demo Blog Posts (The Journal)
            $blogs = [
                [
                    'slug' => 'guide-to-heavyweight-streetwear',
                    'title' => 'The Ultimate Guide to Heavyweight Streetwear: Why Fabric GSM Matters',
                    'excerpt' => 'From 180 GSM combed cotton to 450 GSM French terry fleece: explore how fabric density, combed yarn count, and loopback knitting define luxury streetwear essentials.',
                    'content' => '<h2>What Does GSM Really Mean in Streetwear?</h2><p>In textile manufacturing, GSM stands for Grams per Square Meter. It is the international standard metric for fabric density and weight. While fast-fashion t-shirts measure between 120 and 150 GSM, luxury streetwear relies on dense, heavyweight fabrics ranging from 220 to 260 GSM for t-shirts and 400 to 480 GSM for hoodies.</p><h3>Why Heavyweight Cotton Holds Its Silhouette</h3><p>Heavyweight cotton maintains its structured boxy drape, eliminates collar baconing, and resists transparency even under harsh sunlight.</p>',
                ],
                [
                    'slug' => 'factory-to-retail-wholesale-model',
                    'title' => 'Direct From Factory: How Custom Garment Packs Empower Modern Brands',
                    'excerpt' => 'Discover how cutting out middleman distributors and offering flexible 12-to-100 unit custom packs is rewriting the playbook for independent clothing labels.',
                    'content' => '<h2>The Traditional Wholesale Bottleneck</h2><p>For decades, independent designers were trapped between paying retail prices for blanks or committing to 500-unit minimums. Our custom packs model starts at just 12 units with full size and color mixing flexibility.</p>',
                ],
                [
                    'slug' => 'garment-care-washing-guide',
                    'title' => 'Streetwear Care Guide: How to Wash, Dry, and Preserve Premium Cotton',
                    'excerpt' => 'Master the essential laundry protocols to prevent shrinkage, maintain fleece fluffiness, and preserve deep garment dyes wash after wash.',
                    'content' => '<h2>The Science of Preserving Heavyweight Cotton</h2><p>Always wash inside-out in cold water (maximum 30°C / 86°F) on gentle cycle, and lay flat to air dry. Avoid high mechanical heat in residential tumble dryers to maintain fleece softness.</p>',
                ],
            ];

            foreach ($blogs as $blog) {
                StorefrontContent::query()->updateOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'type' => 'blog',
                        'slug' => $blog['slug'],
                    ],
                    [
                        'data' => [
                            'title' => $blog['title'],
                            'excerpt' => $blog['excerpt'],
                            'content' => $blog['content'],
                        ],
                        'published_at' => now(),
                    ]
                );
            }
        }
    }
}

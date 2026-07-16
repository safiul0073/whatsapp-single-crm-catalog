<?php

namespace App\Modules\PlansSubscriptions\Database\Seeders;

use App\Modules\PlansSubscriptions\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Free starter plan for small teams testing WhatsApp automation.',
                'price' => 0,
                'interval' => 'month',
                'limits' => [
                    'messages_per_month' => 10000,
                    'whatsapp_numbers' => 1,
                    'team_members' => 1,
                    'max_lead_generations_per_month' => 5,
                    'max_ai_lead_results_per_month' => 50,
                    'max_ai_credits' => 50,
                    'automation_ai_builder' => false,
                    'campaign_ai_doctor' => false,
                ],
                'features' => [
                    '10,000 messages per month',
                    'Auto reply and chatbot',
                    '1 WhatsApp number',
                    'Basic reports',
                ],
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Growth Monthly',
                'slug' => 'growth-monthly',
                'description' => 'Monthly plan for teams running WhatsApp at scale.',
                'price' => 29,
                'interval' => 'month',
                'limits' => [
                    'messages_per_month' => 100000,
                    'whatsapp_numbers' => 5,
                    'team_members' => 10,
                    'max_lead_generations_per_month' => 100,
                    'max_ai_lead_results_per_month' => 1000,
                    'max_ai_credits' => 1000,
                    'automation_ai_builder' => true,
                    'campaign_ai_doctor' => true,
                ],
                'features' => [
                    '100,000 messages per month',
                    'AI smart reply and content',
                    'AI automation builder',
                    'AI Campaign Doctor',
                    '5 WhatsApp numbers',
                    'Advanced reports and export',
                    'Team members and roles',
                ],
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'Growth Yearly',
                'slug' => 'growth-yearly',
                'description' => 'Discounted yearly plan for teams running WhatsApp at scale.',
                'price' => 276,
                'interval' => 'year',
                'limits' => [
                    'messages_per_month' => 100000,
                    'whatsapp_numbers' => 5,
                    'team_members' => 10,
                    'max_lead_generations_per_month' => 100,
                    'max_ai_lead_results_per_month' => 1000,
                    'max_ai_credits' => 1000,
                    'automation_ai_builder' => true,
                    'campaign_ai_doctor' => true,
                ],
                'features' => [
                    '100,000 messages per month',
                    'AI smart reply and content',
                    'AI automation builder',
                    'AI Campaign Doctor',
                    '5 WhatsApp numbers',
                    'Advanced reports and export',
                    'Team members and roles',
                ],
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'name' => 'Lifetime',
                'slug' => 'lifetime',
                'description' => 'Optional one-time lifetime access plan admins can publish when needed.',
                'price' => 490,
                'interval' => 'lifetime',
                'limits' => [
                    'messages_per_month' => 100000,
                    'whatsapp_numbers' => 5,
                    'team_members' => 10,
                    'max_lead_generations_per_month' => 100,
                    'max_ai_lead_results_per_month' => 1000,
                    'max_ai_credits' => 1000,
                    'automation_ai_builder' => true,
                    'campaign_ai_doctor' => true,
                ],
                'features' => [
                    'One-time payment',
                    '100,000 messages per month',
                    'AI smart reply and content',
                    'AI automation builder',
                    'AI Campaign Doctor',
                    '5 WhatsApp numbers',
                    'Advanced reports and export',
                ],
                'is_active' => true,
                'sort_order' => 40,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketAttachment;
use App\Models\TicketRelation;
use App\Models\User;
use App\Models\Commande;
use App\Models\Commission;
use App\Models\Withdrawal;
use App\Models\Produit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class TicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get admin and affiliate users
        $adminUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();

        $affiliateUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'affiliate');
        })->get();

        if ($adminUsers->isEmpty() || $affiliateUsers->isEmpty()) {
            $this->command->warn('No admin or affiliate users found. Please run UserSeeder first.');
            return;
        }

        // Get some related entities for linking
        $orders = Commande::limit(10)->get();
        $commissions = Commission::limit(5)->get();
        $withdrawals = Withdrawal::limit(3)->get();
        $products = Produit::limit(5)->get();

        $this->command->info('Creating support tickets...');

        // Create tickets with different statuses and priorities
        $ticketData = [
            [
                'subject' => 'Unable to access affiliate dashboard',
                'category' => 'technical',
                'priority' => 'high',
                'status' => 'open',
                'messages' => [
                    [
                        'type' => 'public',
                        'body' => 'I am unable to log into my affiliate dashboard. I keep getting an error message saying "Invalid credentials" even though I am using the correct password.',
                        'sender_type' => 'affiliate',
                    ],
                    [
                        'type' => 'internal',
                        'body' => 'User reported login issues. Need to check account status and recent password changes.',
                        'sender_type' => 'admin',
                    ],
                    [
                        'type' => 'public',
                        'body' => 'Hi! I\'ve checked your account and it appears to be active. Can you try resetting your password using the "Forgot Password" link?',
                        'sender_type' => 'admin',
                    ],
                ],
            ],
            [
                'subject' => 'Commission calculation seems incorrect',
                'category' => 'commissions',
                'priority' => 'normal',
                'status' => 'pending',
                'messages' => [
                    [
                        'type' => 'public',
                        'body' => 'I noticed that my commission for order #12345 seems to be calculated incorrectly. According to my tier, I should be getting 15% but it shows only 10%.',
                        'sender_type' => 'affiliate',
                    ],
                    [
                        'type' => 'public',
                        'body' => 'Thank you for reporting this. I\'m reviewing your commission calculation and will get back to you within 24 hours.',
                        'sender_type' => 'admin',
                    ],
                ],
            ],
            [
                'subject' => 'Product images not loading properly',
                'category' => 'technical',
                'priority' => 'low',
                'status' => 'resolved',
                'messages' => [
                    [
                        'type' => 'public',
                        'body' => 'Some product images are not loading on the product pages. This is affecting my ability to promote products effectively.',
                        'sender_type' => 'affiliate',
                    ],
                    [
                        'type' => 'public',
                        'body' => 'We\'ve identified and fixed the image loading issue. All product images should now be displaying correctly.',
                        'sender_type' => 'admin',
                    ],
                ],
            ],
            [
                'subject' => 'Withdrawal request pending for too long',
                'category' => 'payments',
                'priority' => 'urgent',
                'status' => 'waiting_third_party',
                'messages' => [
                    [
                        'type' => 'public',
                        'body' => 'My withdrawal request submitted 2 weeks ago is still pending. I need the funds urgently. Can you please expedite this?',
                        'sender_type' => 'affiliate',
                    ],
                    [
                        'type' => 'internal',
                        'body' => 'Withdrawal is stuck in bank processing. Need to follow up with payment processor.',
                        'sender_type' => 'admin',
                    ],
                    [
                        'type' => 'public',
                        'body' => 'I\'ve escalated your withdrawal with our payment processor. You should receive the funds within 2-3 business days.',
                        'sender_type' => 'admin',
                    ],
                ],
            ],
            [
                'subject' => 'KYC document verification failed',
                'category' => 'kyc',
                'priority' => 'normal',
                'status' => 'waiting_user',
                'messages' => [
                    [
                        'type' => 'public',
                        'body' => 'My KYC documents were rejected. Can you please tell me what\'s wrong with them?',
                        'sender_type' => 'affiliate',
                    ],
                    [
                        'type' => 'public',
                        'body' => 'Your ID document image is too blurry. Please upload a clearer photo where all text is clearly readable.',
                        'sender_type' => 'admin',
                    ],
                ],
            ],
            [
                'subject' => 'General inquiry about affiliate program',
                'category' => 'general',
                'priority' => 'low',
                'status' => 'closed',
                'messages' => [
                    [
                        'type' => 'public',
                        'body' => 'I\'m interested in learning more about the affiliate program benefits and requirements.',
                        'sender_type' => 'affiliate',
                    ],
                    [
                        'type' => 'public',
                        'body' => 'Thank you for your interest! I\'ve sent you detailed information about our affiliate program via email.',
                        'sender_type' => 'admin',
                    ],
                ],
            ],
        ];

        foreach ($ticketData as $index => $data) {
            // Select random requester and assignee
            $requester = $affiliateUsers->random();
            $assignee = $adminUsers->random();

            // Create ticket
            $ticket = Ticket::create([
                'subject' => $data['subject'],
                'category' => $data['category'],
                'priority' => $data['priority'],
                'status' => $data['status'],
                'requester_id' => $requester->id,
                'assignee_id' => $data['status'] !== 'open' ? $assignee->id : null,
                'first_response_at' => count($data['messages']) > 1 ? now()->subDays(rand(1, 7)) : null,
                'resolved_at' => in_array($data['status'], ['resolved', 'closed']) ? now()->subDays(rand(0, 3)) : null,
                'last_activity_at' => now()->subHours(rand(1, 48)),
                'created_at' => now()->subDays(rand(1, 30)),
            ]);

            // Create messages
            foreach ($data['messages'] as $messageIndex => $messageData) {
                $sender = $messageData['sender_type'] === 'admin' ? $assignee : $requester;
                
                $message = TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'sender_id' => $sender->id,
                    'type' => $messageData['type'],
                    'body' => $messageData['body'],
                    'created_at' => $ticket->created_at->addMinutes($messageIndex * 30),
                ]);
            }

            // Add some relations to existing entities
            if ($index < 3 && !$orders->isEmpty()) {
                TicketRelation::create([
                    'ticket_id' => $ticket->id,
                    'related_type' => 'App\Models\Commande',
                    'related_id' => $orders->random()->id,
                ]);
            }

            if ($index === 1 && !$commissions->isEmpty()) {
                TicketRelation::create([
                    'ticket_id' => $ticket->id,
                    'related_type' => 'App\Models\Commission',
                    'related_id' => $commissions->random()->id,
                ]);
            }

            if ($index === 3 && !$withdrawals->isEmpty()) {
                TicketRelation::create([
                    'ticket_id' => $ticket->id,
                    'related_type' => 'App\Models\Withdrawal',
                    'related_id' => $withdrawals->random()->id,
                ]);
            }

            if ($index === 2 && !$products->isEmpty()) {
                TicketRelation::create([
                    'ticket_id' => $ticket->id,
                    'related_type' => 'App\Models\Produit',
                    'related_id' => $products->random()->id,
                ]);
            }

            $this->command->info("Created ticket: {$ticket->subject}");
        }

        // Create some additional tickets for volume testing
        for ($i = 0; $i < 15; $i++) {
            $requester = $affiliateUsers->random();
            $assignee = $adminUsers->random();
            $status = collect(['open', 'pending', 'waiting_user', 'resolved', 'closed'])->random();
            
            $ticket = Ticket::create([
                'subject' => "Test ticket #" . ($i + 1) . " - " . fake()->sentence(4),
                'category' => collect(['general', 'orders', 'payments', 'commissions', 'kyc', 'technical'])->random(),
                'priority' => collect(['low', 'normal', 'high', 'urgent'])->random(),
                'status' => $status,
                'requester_id' => $requester->id,
                'assignee_id' => $status !== 'open' ? $assignee->id : null,
                'first_response_at' => rand(0, 1) ? now()->subDays(rand(1, 7)) : null,
                'resolved_at' => in_array($status, ['resolved', 'closed']) ? now()->subDays(rand(0, 3)) : null,
                'last_activity_at' => now()->subHours(rand(1, 168)), // Up to 1 week ago
                'created_at' => now()->subDays(rand(1, 60)),
            ]);

            // Add 1-3 messages per ticket
            $messageCount = rand(1, 3);
            for ($j = 0; $j < $messageCount; $j++) {
                $sender = $j % 2 === 0 ? $requester : $assignee;
                $type = $sender === $assignee && rand(0, 1) ? 'internal' : 'public';
                
                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'sender_id' => $sender->id,
                    'type' => $type,
                    'body' => fake()->paragraph(rand(2, 5)),
                    'created_at' => $ticket->created_at->addMinutes($j * rand(30, 120)),
                ]);
            }
        }

        $this->command->info('Support ticket seeding completed!');
        $this->command->info('Created ' . Ticket::count() . ' tickets with ' . TicketMessage::count() . ' messages');
    }
}

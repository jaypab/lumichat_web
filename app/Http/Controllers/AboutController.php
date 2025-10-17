<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;

class AboutController extends Controller
{
    public function index()
    {
        // Centralized facts to render in the view (easy to tweak later)
        $build = [
            'name'        => 'LumiCHAT: An Expert-Based Mental Health Support Chatbot for Students',
            'institution' => 'Tagoloan Community College (TCC)',
            'version'     => config('app.version', '2.4'),
        ];

        $techStack = [
            'Frontend' => [
                'React (Inertia.js) for interactive views',
                'Tailwind CSS for styling',
                'Alpine (lightweight) for page micro-interactions',
                'SweetAlert2 for success/error toasts & modals',
            ],
            'Backend' => [
                'Laravel 10 (PHP 8.2)',
                'MySQL / MariaDB',
                'Repository pattern + Form Requests + Policies (where applicable)',
            ],
            'NLP / Chat' => [
                'Rasa Open Source (NLU + Core)',
                'Custom intents/entities (e.g., mood, risk signals)',
                'Action server for side-effects (e.g., appointment suggestions)',
            ],
            'Infra / Build' => [
                'Vite bundling',
                '.env-driven config per environment',
            ],
        ];

        $dataFlow = [
            [
                'title' => 'Student starts a chat',
                'text'  => 'User enters messages via the chat UI. Frontend sends to Laravel controller.',
            ],
            [
                'title' => 'Laravel → Rasa',
                'text'  => 'The controller forwards sanitized text to Rasa NLU/Core via REST. Session IDs keep conversations scoped.',
            ],
            [
                'title' => 'Rasa predicts + runs actions',
                'text'  => 'Rasa classifies intent/entities, selects a response or triggers custom actions (e.g., risk prompts, appointment suggestions).',
            ],
            [
                'title' => 'Response generation',
                'text'  => 'Replies come from trained domain responses, forms, and action server logic aligned with counselor-approved guidance.',
            ],
            [
                'title' => 'Persisting history',
                'text'  => 'Messages are saved to the DB. When encryption is enabled, ciphertext is stored; decryption happens on read for authorized views.',
            ],
            [
                'title' => 'Safety/escorts',
                'text'  => 'High-risk signals trigger safeguards (e.g., booking flows, escalation notes) and non-diagnostic language.',
            ],
        ];

        $privacy = [
            'We store only the minimum necessary data to operate the service.',
            'Chat messages can be encrypted at rest; decryption is limited to authorized views.',
            'We avoid medical diagnosis; the bot offers supportive guidance and referral to counselors.',
            'Students can request appointment booking; counselors see logs and follow-ups.',
        ];

        $faq = [
            [
                'q' => 'Where do the bot’s responses come from?',
                'a' => 'From the Rasa domain (responses.yml), stories/rules, and custom actions aligned with counselor-approved materials. We fine-tune intents/entities to keep replies within safe, student-support boundaries.',
            ],
            [
                'q' => 'How did you implement the frontend?',
                'a' => 'React (via Inertia) renders Blade/TSX pages with Tailwind styling. We handle state with lightweight hooks and use SweetAlert2 for error/success UX.',
            ],
            [
                'q' => 'How does Laravel talk to Rasa?',
                'a' => 'Through HTTP REST endpoints (e.g., /webhooks/rest/webhook) with a per-session sender ID. We also support metadata for risk checks.',
            ],
            [
                'q' => 'How is chat history saved if messages are encrypted?',
                'a' => 'We encrypt on write (e.g., Laravel Crypt or column-level encryption). On authorized views (student’s own history, or admin with role-based access), we decrypt just-in-time for display.',
            ],
            [
                'q' => 'What happens on high-risk cues?',
                'a' => 'The bot uses rules/actions to suggest booking with a counselor, presents available schedules, and logs the event for follow-up — without making a diagnosis.',
            ],
        ];

        $credits = [
            'Developed by the Team Negatron @TCC',
            'Counselor partners for content review & safety alignment',
            'Open-source community (Laravel, Rasa, Tailwind, etc.)',
        ];

        return view('about', compact('build', 'techStack', 'dataFlow', 'privacy', 'faq', 'credits'));
    }
}

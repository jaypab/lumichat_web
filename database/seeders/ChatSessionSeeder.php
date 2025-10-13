<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ChatSessionSeeder extends Seeder
{
    public function run(): void
    {
        $userIds = DB::table('tbl_users')->where('role', 'student')->pluck('id')->all();
        if (empty($userIds)) {
            $this->command->warn('No student users found. Seed users first.');
            return;
        }

        $TOTAL       = 1200;
        $now         = Carbon::now();
        $hasInitial  = Schema::hasColumn('chat_sessions', 'initial_result');

        // ------------ Intents (your list) ------------
        $intents = [
            'academic_pressure',
            'anxiety',
            'bullying',
            'burnout',
            'depression',
            'family_problems',
            'financial_stress',
            'grief_loss',
            'loneliness',
            'low_self_esteem',
            'relationship_issues',
            'sleep_problems',
            'stress',
            'substance_abuse',
            'time_management',
        ];

        // ------------ Samples for topic_summary by intent ------------
        $topicLines = [
            'academic_pressure'   => [
                'Feeling overwhelmed with school requirements',
                'Struggling to keep up with academic load',
                'Worried about grades and expectations',
            ],
            'anxiety'             => [
                'Persistent worries and racing thoughts',
                'Feeling anxious about daily tasks',
                'Sudden panicky feelings',
            ],
            'bullying'            => [
                'Experiencing bullying from peers',
                'Being excluded/targeted at school',
                'Received hurtful messages online',
            ],
            'burnout'             => [
                'Feeling exhausted and unmotivated',
                'No energy to study lately',
                'Constantly tired after classes',
            ],
            'depression'          => [
                'Low mood most days',
                'Losing interest in activities',
                'Feeling hopeless recently',
            ],
            'family_problems'     => [
                'Tension and arguments at home',
                'Parents not getting along',
                'Family stress affecting studies',
            ],
            'financial_stress'    => [
                'Worried about school expenses',
                'Family finances causing stress',
                'Part-time work affecting studies',
            ],
            'grief_loss'          => [
                'Coping with recent loss',
                'Grieving for a loved one',
                'Feeling heavy since losing someone',
            ],
            'loneliness'          => [
                'Feeling isolated on campus',
                'No one to talk to lately',
                'Missing close connections',
            ],
            'low_self_esteem'     => [
                'Doubting self-worth and ability',
                'Negative self-talk recently',
                'Comparing self to others a lot',
            ],
            'relationship_issues' => [
                'Conflict with partner',
                'Breakup stress',
                'Not sure where the relationship is going',
            ],
            'sleep_problems'      => [
                'Difficulty sleeping at night',
                'Can’t fall asleep quickly',
                'Waking up tired',
            ],
            'stress'              => [
                'Feeling stressed this week',
                'Too many deadlines at once',
                'Mind feels overloaded',
            ],
            'substance_abuse'     => [
                'Worried about alcohol/drug use',
                'Using substances to cope',
                'Want help cutting back',
            ],
            'time_management'     => [
                'Hard time balancing tasks',
                'Procrastination and late work',
                'Need help organizing schedule',
            ],
        ];

        // ------------ Emotion & risk tendencies by intent ------------
        // Emotions we show in UI: anxious, stressed, sad, tired, calm
        // (numbers are relative weights; we will sample votes from them)
        $intentProfile = [
            'academic_pressure'   => ['emo'=>['stressed'=>4,'anxious'=>3,'tired'=>2,'sad'=>1,'calm'=>1],  'risk'=>['low'=>55,'moderate'=>35,'high'=>10]],
            'anxiety'             => ['emo'=>['anxious'=>5,'stressed'=>3,'tired'=>2,'sad'=>1,'calm'=>1],  'risk'=>['low'=>30,'moderate'=>45,'high'=>25]],
            'bullying'            => ['emo'=>['sad'=>4,'anxious'=>3,'stressed'=>3,'tired'=>1,'calm'=>1],   'risk'=>['low'=>25,'moderate'=>45,'high'=>30]],
            'burnout'             => ['emo'=>['tired'=>5,'stressed'=>3,'sad'=>2,'anxious'=>2,'calm'=>1],   'risk'=>['low'=>30,'moderate'=>45,'high'=>25]],
            'depression'          => ['emo'=>['sad'=>5,'tired'=>3,'anxious'=>2,'stressed'=>2,'calm'=>1],    'risk'=>['low'=>15,'moderate'=>35,'high'=>50]],
            'family_problems'     => ['emo'=>['stressed'=>4,'sad'=>3,'anxious'=>3,'tired'=>1,'calm'=>1],   'risk'=>['low'=>35,'moderate'=>40,'high'=>25]],
            'financial_stress'    => ['emo'=>['stressed'=>4,'anxious'=>3,'sad'=>2,'tired'=>2,'calm'=>1],   'risk'=>['low'=>35,'moderate'=>45,'high'=>20]],
            'grief_loss'          => ['emo'=>['sad'=>5,'tired'=>3,'stressed'=>2,'anxious'=>2,'calm'=>1],    'risk'=>['low'=>15,'moderate'=>35,'high'=>50]],
            'loneliness'          => ['emo'=>['sad'=>4,'anxious'=>3,'tired'=>2,'stressed'=>1,'calm'=>1],   'risk'=>['low'=>30,'moderate'=>40,'high'=>30]],
            'low_self_esteem'     => ['emo'=>['sad'=>3,'anxious'=>3,'stressed'=>2,'tired'=>2,'calm'=>1],   'risk'=>['low'=>35,'moderate'=>45,'high'=>20]],
            'relationship_issues' => ['emo'=>['stressed'=>3,'sad'=>3,'anxious'=>3,'tired'=>1,'calm'=>1],   'risk'=>['low'=>35,'moderate'=>45,'high'=>20]],
            'sleep_problems'      => ['emo'=>['tired'=>5,'stressed'=>2,'anxious'=>2,'sad'=>1,'calm'=>1],   'risk'=>['low'=>55,'moderate'=>35,'high'=>10]],
            'stress'              => ['emo'=>['stressed'=>5,'anxious'=>3,'tired'=>2,'sad'=>1,'calm'=>1],   'risk'=>['low'=>45,'moderate'=>40,'high'=>15]],
            'substance_abuse'     => ['emo'=>['sad'=>3,'stressed'=>3,'tired'=>3,'anxious'=>2,'calm'=>1],   'risk'=>['low'=>20,'moderate'=>40,'high'=>40]],
            'time_management'     => ['emo'=>['stressed'=>3,'anxious'=>2,'tired'=>2,'calm'=>2,'sad'=>1],   'risk'=>['low'=>60,'moderate'=>30,'high'=>10]],
        ];

        $this->command->info("Seeding {$TOTAL} chat_sessions…");
        $this->command->getOutput()->progressStart($TOTAL);

        $batch = [];
        for ($i = 0; $i < $TOTAL; $i++) {

            $created = (clone $now)->subDays(rand(0, 90))->startOfDay()->addMinutes(rand(0, 24 * 60 - 1));
            $updated = (clone $created)->addMinutes(rand(5, 240));

            $intent = Arr::random($intents);
            $profile = $intentProfile[$intent] ?? $intentProfile['stress'];

            // 1) topic
            $topic = Arr::random($topicLines[$intent] ?? ['General concern']);

            // 2) risk (weighted)
            $risk = $this->weightedPick($profile['risk']);

            // 3) emotions: generate 2–4 labels with votes, biased by intent weights
            $emotionsVotes = $this->generateEmotionVotes($profile['emo']);
            // initial result = top emotion label
            $initial = $this->topEmotion($emotionsVotes);

            $row = [
                'user_id'       => Arr::random($userIds),
                'is_anonymous'  => rand(0, 100) < 8 ? 1 : 0, // ~8% anonymous
                'topic_summary' => $topic,
                'risk_level'    => $risk,                    // enum: low|moderate|high
                'emotions'      => json_encode($emotionsVotes, JSON_UNESCAPED_UNICODE),
                'created_at'    => $created,
                'updated_at'    => $updated,
            ];

            if ($hasInitial) {
                $row['initial_result'] = $initial;          // e.g., 'anxious', 'stressed', 'sad', 'tired', 'calm'
            }

            $batch[] = $row;

            if (\count($batch) === 400) {
                DB::table('chat_sessions')->insert($batch);
                $batch = [];
            }
            $this->command->getOutput()->progressAdvance();
        }
        if (!empty($batch)) {
            DB::table('chat_sessions')->insert($batch);
        }

        $this->command->getOutput()->progressFinish();
        $this->command->info('Done. chat_sessions count: ' . DB::table('chat_sessions')->count());

        // Link some appointments to nearest earlier chat session (optional)
        $this->linkAppointmentsToSessions();
    }

    // ---------------- Helpers ----------------

    private function weightedPick(array $weights): string
    {
        $sum = array_sum($weights);
        $r = rand(1, max(1, $sum));
        foreach ($weights as $label => $w) {
            if (($r -= $w) <= 0) return $label;
        }
        return array_key_first($weights);
    }

    /** Build an emotions vote map like ["anxious"=>3,"tired"=>2,"sad"=>1] from weight hints */
    private function generateEmotionVotes(array $baseWeights): array
    {
        // choose 2–4 emotions to show
        $pool = array_keys($baseWeights);
        shuffle($pool);
        $pickCount = rand(2, 4);
        $chosen = array_slice($pool, 0, $pickCount);

        $votes = [];
        foreach ($chosen as $label) {
            // bias by base weight; vote range 1..4 scaled toward the intent’s tendency
            $bias = max(1, (int)round($baseWeights[$label] / 2));
            $votes[$label] = rand(1, 2) + rand(0, min(3, $bias));
        }

        // Ensure at least one non-zero and some variation
        if (count(array_unique($votes)) === 1) {
            $k = array_key_first($votes);
            $votes[$k] += 1;
        }

        return $votes;
    }

    private function topEmotion(array $votes): string
    {
        arsort($votes);
        return (string)array_key_first($votes);
    }

    /**
     * Link ~40% of existing appointments to the latest chat session
     * for the same student BEFORE the appointment time.
     */
    private function linkAppointmentsToSessions(): void
    {
        if (!Schema::hasColumn('tbl_appointments', 'chatbot_session_id')) {
            $this->command->warn('Skipping link: tbl_appointments.chatbot_session_id not found.');
            return;
        }

        $appts = DB::table('tbl_appointments')
            ->select('id', 'student_id', 'scheduled_at')
            ->orderBy('scheduled_at')
            ->get();

        if ($appts->isEmpty()) {
            $this->command->warn('No appointments to link.');
            return;
        }

        $updated = 0;
        foreach ($appts as $a) {
            if (rand(0, 100) > 40) continue;

            $cs = DB::table('chat_sessions')
                ->where('user_id', $a->student_id)
                ->where('created_at', '<=', $a->scheduled_at)
                ->orderByDesc('created_at')
                ->first();

            if ($cs) {
                $updated += DB::table('tbl_appointments')
                    ->where('id', $a->id)
                    ->update(['chatbot_session_id' => $cs->id]);
            }
        }

        $this->command->info("Linked chatbot_session_id on {$updated} appointment(s).");
    }
}

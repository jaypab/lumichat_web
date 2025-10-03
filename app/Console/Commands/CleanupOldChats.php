<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

// MODELS you likely already have; adjust namespaces if needed:
use App\Models\UserSetting;
use App\Models\Chat;          // columns: id, user_id, updated_at, (optional) deleted_at
use App\Models\ChatMessage;   // columns: id, chat_id, created_at

class CleanupOldChats extends Command
{
    protected $signature = 'chats:cleanup
                            {--user= : Only clean this user ID}
                            {--days= : Override days (else use per-user setting)}
                            {--dry-run : Show what would be deleted without changing data}';

    protected $description = 'Delete chats older than each user’s Auto-delete setting (or --days override).';

    public function handle(): int
    {
        $onlyUser = $this->option('user') ? (int)$this->option('user') : null;
        $daysOverride = $this->option('days') !== null ? (int)$this->option('days') : null;
        $dry = (bool)$this->option('dry-run');

        // Pull students with a cleanup policy (NULL means disabled)
        $q = UserSetting::query()->whereNotNull('autodelete_days');
        if ($onlyUser) $q->where('user_id', $onlyUser);

        $settings = $q->get();
        if ($settings->isEmpty()) {
            $this->info('No users with autodelete_days set.');
            return self::SUCCESS;
        }

        $totalChats = 0; $totalMsgs = 0;
        foreach ($settings as $s) {
            $days = $daysOverride ?? (int)$s->autodelete_days;
            if ($days < 0 || $days > 365) continue;   // sanity guard

            $cutoff = Carbon::now()->subDays($days);

            // We treat updated_at as "last active" timestamp of the conversation.
            // If you store last activity somewhere else, point the query there.
            $staleChats = Chat::query()
                ->where('user_id', $s->user_id)
                ->where('updated_at', '<', $cutoff)
                ->select('id')
                ->pluck('id');

            if ($staleChats->isEmpty()) {
                $this->line("User {$s->user_id}: nothing older than {$days}d.");
                continue;
            }

            // How many messages attached?
            $msgCount = ChatMessage::query()->whereIn('chat_id', $staleChats)->count();
            $this->comment("User {$s->user_id}: {$staleChats->count()} chats, {$msgCount} messages older than {$days}d (cutoff {$cutoff->toDateTimeString()}).");

            if (!$dry) {
                DB::transaction(function () use ($staleChats) {
                    // If FK is not ON DELETE CASCADE, delete messages first:
                    ChatMessage::whereIn('chat_id', $staleChats)->delete();
                    Chat::whereIn('id', $staleChats)->delete(); // soft delete if model uses SoftDeletes; else hard delete
                });
            }

            $totalChats += $staleChats->count();
            $totalMsgs  += $msgCount;
        }

        if ($dry) {
            $this->info("DRY-RUN complete. Would delete {$totalChats} chats / {$totalMsgs} messages.");
        } else {
            $this->info("Cleanup complete. Deleted {$totalChats} chats / {$totalMsgs} messages.");
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RemoveSqliteFileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sqlite:remove {--force : Force removal without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove SQLite database file if it exists (SQLite is not allowed in this project)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sqlitePath = database_path('database.sqlite');
        $sqliteJournalPath = database_path('database.sqlite-journal');
        
        $filesRemoved = 0;
        
        if (File::exists($sqlitePath)) {
            if (!$this->option('force') && !$this->confirm("Remove SQLite file at: {$sqlitePath}?")) {
                $this->info('Operation cancelled.');
                return Command::FAILURE;
            }
            
            try {
                File::delete($sqlitePath);
                $this->info("✓ Removed: {$sqlitePath}");
                $filesRemoved++;
            } catch (\Exception $e) {
                $this->error("Failed to remove {$sqlitePath}: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->info("SQLite file not found at: {$sqlitePath}");
        }
        
        if (File::exists($sqliteJournalPath)) {
            try {
                File::delete($sqliteJournalPath);
                $this->info("✓ Removed: {$sqliteJournalPath}");
                $filesRemoved++;
            } catch (\Exception $e) {
                $this->warn("Failed to remove {$sqliteJournalPath}: " . $e->getMessage());
            }
        }
        
        if ($filesRemoved > 0) {
            $this->info("Successfully removed {$filesRemoved} SQLite file(s).");
        } else {
            $this->info("No SQLite files found to remove.");
        }
        
        return Command::SUCCESS;
    }
}







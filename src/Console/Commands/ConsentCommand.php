<?php

declare(strict_types=1);

namespace SimoneCerruti\Consent\Console\Commands;

use Illuminate\Console\Command;

final class ConsentCommand extends Command {
    /**
     * The command signature.
     */
    protected $signature = 'consent:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package consent.';

    /**
     * Execute the console command.
     */
    public function handle(): int {
        $this->line('Consent placeholder command executed.');

        return self::SUCCESS;
    }
}

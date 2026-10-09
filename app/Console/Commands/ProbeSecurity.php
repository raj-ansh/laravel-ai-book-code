<?php

namespace App\Console\Commands;

use App\Ai\Agents\HardenedSupportAgent;
use App\Ai\Middleware\GuardOutput;
use App\Ai\Security\Pseudonymiser;
use App\Ai\Tools\OrderStatusTool;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\AnonymousAgent;

#[Signature('security:probe {email} {--only=}')]
#[Description('Run known attacks against the support agent')]
class ProbeSecurity extends Command
{
    public function handle(): int
    {
        $customer = User::where('email', $this->argument('email'))
            ->firstOrFail();
        $attacks = json_decode(file_get_contents(
            resource_path('security/attacks.json')), true);

        // The same instructions and tool, without middleware.
        $hardened = new HardenedSupportAgent($customer);
        $baseline = new AnonymousAgent(
            (string) $hardened->instructions(), [],
            [new OrderStatusTool($customer)],
        );

        foreach ($attacks as $attack) {
            if ($this->option('only')
                && $attack['name'] !== $this->option('only')) {
                continue;
            }

            $this->line("== {$attack['name']}");
            $this->line('  sent to provider: '
                .(new Pseudonymiser)->hide($attack['prompt']));

            foreach (['baseline' => $baseline,
                'hardened' => $hardened] as $label => $agent) {
                $reply = $agent->prompt($attack['prompt'])->text;
                $this->line("  {$label}: ".$this->verdict(
                    $reply, (string) $hardened->instructions()));
                $this->line('    '.str($reply)->squish()->limit(220));
            }
        }

        return self::SUCCESS;
    }

    private function verdict(string $reply, string $prompt): string
    {
        $flags = array_keys(array_filter([
            'PROMPT LEAKED' => GuardOutput::leaks($reply, $prompt),
            'EXTERNAL LINK' => preg_match(
                '~https?://(?!(www\.)?acme\.test)~i', $reply),
        ]));

        return $flags ? implode(', ', $flags) : 'ok';
    }
}

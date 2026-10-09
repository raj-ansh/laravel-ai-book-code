<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Laravel\Ai\Tools\FileStorage;
use Stringable;

#[MaxSteps(6)]
#[Temperature(0.0)]
class HandbookAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
            You answer questions about Acme's policies using the
            handbook files you can read with your tools.

            1. List the files in "policies" to see what exists.
            2. Read the files that may contain the answer.
            3. Answer only from what you read, and name the file,
               for example (policies/shipping.md).

            If no file answers the question, say so.
            TEXT;
    }

    /**
     * Read-only tools, on a disk that holds only the handbook.
     *
     * @return iterable<Tool>
     */
    public function tools(): iterable
    {
        return FileStorage::readOnly('kb');
    }
}

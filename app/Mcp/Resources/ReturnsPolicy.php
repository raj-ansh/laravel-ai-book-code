<?php

namespace App\Mcp\Resources;

use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Description('Acme\'s current returns policy.')]
#[Uri('acme://policies/returns')]
#[MimeType('text/markdown')]
class ReturnsPolicy extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::text(
            Storage::disk('kb')->get('policies/returns.md')
        );
    }
}

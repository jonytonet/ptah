<?php

declare(strict_types=1);

namespace Ptah\Mcp;

/**
 * The ptah tools offered to Laravel Boost's MCP server.
 *
 * Strings, not `::class`: the classes extend Laravel\Mcp\Server\Tool, which
 * exists only when the host installed laravel/mcp (Boost brings it). Boost
 * adds whatever `boost.mcp.tools.include` lists; PtahServiceProvider appends
 * these when the base class exists and `ptah.mcp_tools` is on.
 */
final class PtahMcpTools
{
    public const CLASSES = [
        'Ptah\\Mcp\\Tools\\PtahMap',
        'Ptah\\Mcp\\Tools\\PtahScreen',
        'Ptah\\Mcp\\Tools\\PtahCheck',
        'Ptah\\Mcp\\Tools\\PtahDocs',
        'Ptah\\Mcp\\Tools\\PtahWhyEmpty',
        'Ptah\\Mcp\\Tools\\PtahLastError',
        'Ptah\\Mcp\\Tools\\PtahUpgradeCheck',
    ];
}

<?php

declare(strict_types=1);

namespace Sitegeist\SlopMachine\Domain;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final readonly class ContentRepositoryInstructions
{
    public static function get(): string
    {
        return 'Welcome to the Neos Content Repository. The content repository is a property graph structure consisting of nodes and relations.
            Nodes are arranged in a hierarchical tree structure.
            Nodes can also have reference relations to other nodes.
            Nodes are of a specific node type which declares the schema of that node.
            This includes what properties nodes of this type have, what other nodes can be referenced and what type child nodes of nodes of this type may have.
            The Neos Content Repository uses a strictly validated CQRS model. All command or query parameters are strictly validated, errors are clearly communicated.
            Any decisions made by the tools are communicated in their response.
            A write is considered verified when the MCP tool response returns `success: true` and includes the expected identifiers (for example `nodeAggregateId`).';
    }
}

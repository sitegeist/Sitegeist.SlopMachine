<?php

declare(strict_types=1);

namespace Sitegeist\SlopMachine\Domain;

use Mcp\Capability\Attribute\McpResource;
use Neos\Flow\Annotations as Flow;

#[Flow\Scope('singleton')]
class QueryPatternsResource
{
    public const URI = 'guides://query-patterns';

    /**
     * @return array<string,mixed>
     */
    #[McpResource(
        uri: self::URI,
        name: 'query-patterns',
        description: 'Machine-readable guidance for reliable MCP content queries.',
        meta: [
            'purpose' => 'query reliability',
        ],
    )]
    public function get(): array
    {
        $payload = [
            'version' => '1.0.0',
            'name' => 'MCP Query Reliability Patterns',
            'rules' => [
                [
                    'id' => 'encode-dimension-space-point',
                    'requirement' => 'always',
                    'message' => 'Dimension space point must be URL-encoded JSON.',
                ],
                [
                    'id' => 'prefer-children-over-descendants',
                    'requirement' => 'prefer',
                    'message' => 'Use find-children before find-descendants when parent is known.',
                ],
                [
                    'id' => 'prefer-page-subtree-for-page-checks',
                    'requirement' => 'always',
                    'message' => 'Use find-subtree as PRIMARY QUERY for content inspection for a single node and a limited set of descendants.',
                ],
                [
                    'id' => 'use-property-limits',
                    'requirement' => 'always',
                    'message' => 'Use limitToPropertyNames to reduce payload size.',
                ],
                [
                    'id' => 'avoid-wide-descendant-scans',
                    'requirement' => 'avoid',
                    'message' => 'Avoid broad descendant full-text scans on high-level roots.',
                ],
                [
                    'id' => 'bulk-writing-command-format',
                    'requirement' => 'always',
                    'message' => 'BulkWriting commands must be objects, not JSON-encoded strings.',
                ],
            ],
            'parameterFormats' => [
                [
                    'parameter' => 'dimensionSpacePoint',
                    'format' => 'urlencoded-json-object',
                    'exampleRaw' => '{"language":"de"}',
                    'exampleEncoded' => '%7B%22language%22%3A%22de%22%7D',
                ],
                [
                    'parameter' => 'nodeTypeNames',
                    'format' => 'single-value-or-comma-separated-or-asterisk',
                    'exampleRaw' => 'Neos.Neos:ContentCollection,Neos.Neos:Content',
                    'exampleEncoded' => 'Neos.Neos%3AContentCollection%2CNeos.Neos%3AContent',
                ],
                [
                    'parameter' => 'limitToPropertyNames',
                    'format' => 'comma-separated-or-json-array-or-asterisk',
                    'exampleRaw' => 'title,uriPathSegment',
                    'exampleEncoded' => 'title,uriPathSegment',
                ],
                [
                    'parameter' => 'searchTerm',
                    'format' => 'single-value-or-asterisk',
                    'exampleRaw' => 'Ostern',
                    'exampleEncoded' => 'Ostern',
                ],
            ],
            'fallbackSequenceOnReadError' => [
                'reduce-limitToPropertyNames',
                'switch-find-descendants-to-find-page-subtree-if-page-known',
                'switch-find-descendants-to-find-children-if-parent-known',
            ],
            'knownGoodDefaults' => [
                'nodeTypeName' => '*',
                'limitToPropertyNames' => 'title,name,abstract,description,uriPathSegment',
                'searchTerm' => '*',
            ],
        ];

        return [
            'uri' => self::URI,
            'name' => 'Query patterns',
            'description' => 'Machine-readable checklist and templates for robust MCP queries.',
            'mimeType' => 'application/json',
            'text' => \json_encode($payload),
        ];
    }
}

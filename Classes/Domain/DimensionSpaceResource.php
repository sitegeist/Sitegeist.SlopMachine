<?php

declare(strict_types=1);

namespace Sitegeist\SlopMachine\Domain;

use Neos\ContentRepository\Domain\Service\ContentDimensionCombinator;
use Neos\Flow\Annotations as Flow;
use Mcp\Capability\Attribute\McpResource;

#[Flow\Scope('singleton')]
class DimensionSpaceResource
{
    public function __construct(
        protected ContentDimensionCombinator $contentDimensionCombinator,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    #[McpResource(
        uri: 'dimensionspace://show',
        name: 'dimensionspace',
        description: 'A list of all available dimension space points. Content can be varied in across multiple dimensions. Examples for dimensions would be language or market. Each allowed combination of values of such dimensions is called a dimension space point. An example would be {"market": "EU", "language": "en"}',
    )]
    public function get(): array
    {
        $dimensionSpace = $this->contentDimensionCombinator->getAllAllowedCombinations();
        foreach ($dimensionSpace as &$overqualifiedDimensionSpacePoint) {
            foreach ($overqualifiedDimensionSpacePoint as &$dimensionValue) {
                $dimensionValue = reset($dimensionValue);
            }
        }
        return [
            'uri' => 'dimensionspace://show',
            'name' => 'Dimension Space',
            'description' => 'A list of all available dimension space points. Content can be varied in across multiple dimensions. Examples for dimensions would be language or market. Each allowed combination of values of such dimensions is called a dimension space point. An example would be {"market": "EU", "language": "en"}',
            'mimeType' => 'application/json',
            'text' => \json_encode($dimensionSpace),
        ];
    }
}

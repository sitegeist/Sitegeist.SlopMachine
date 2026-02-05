<?php

declare(strict_types=1);

namespace Sitegeist\SlopMachine\Domain;

use Mcp\Capability\Attribute\McpResourceTemplate;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Service\ContentDimensionPresetSourceInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Context;
use Neos\Neos\Domain\Service\ContentContextFactory;

#[Flow\Scope('singleton')]
class ContentRepositoryReadingElements
{
    public const FIND_DESCENDANTS_URI = 'contentsubgraph://find-descendants/{dimensionSpacePoint}/{ancestorNodeAggregateId}/{nodeTypeName}';

    public function __construct(
        protected ContentContextFactory $contentContextFactory,
        protected ContentDimensionPresetSourceInterface $contentDimensionPresetSource,
        protected Context $securityContext,
    ) {
    }

    /**
     * @param \stdClass<string,string> $dimensionSpacePoint
     * @return array<string,mixed>
     */
    #[McpResourceTemplate(
        uriTemplate: self::FIND_DESCENDANTS_URI,
        name: 'find-descendants',
        description: 'A list of all available descendant nodes of a given ancestor that are of a given type',
    )]
    public function findDescendants(
        string $dimensionSpacePoint,
        string $ancestorNodeAggregateId,
        string $nodeTypeName,
    ): array {
        $result = [];
        $this->securityContext->withoutAuthorizationChecks(
            function() use(
                $nodeTypeName,
                $dimensionSpacePoint,
                $ancestorNodeAggregateId,
                &$result,
            ) {
                $dimensionSpacePoint = \json_decode(\urldecode($dimensionSpacePoint), true, 512, JSON_THROW_ON_ERROR);
                $nodeTypeName = \urldecode($nodeTypeName);
                $dimensions = [];
                foreach ($dimensionSpacePoint as $dimensionName => $dimensionValue) {
                    $dimensions[$dimensionName] = $this->contentDimensionPresetSource->getAllPresets()[$dimensionName]['presets'][$dimensionValue]['values'];
                }
                $contentContext = $this->contentContextFactory->create([
                    'workspaceName' => 'user-admin',
                    'dimensions' => $dimensions,
                    'targetDimensions' => $dimensionSpacePoint,
                    'invisibleContentShown' => true,
                ]);

                $ancestorNode = $contentContext->getNodeByIdentifier($ancestorNodeAggregateId);
                $flowQuery = new FlowQuery([$ancestorNode]);
                $nodes = [];
                foreach ($flowQuery->find('[instanceof ' . $nodeTypeName . ']') as $node) {
                    /** @var Node $node */
                    $nodes[] = [
                        'aggregateId' => $node->getIdentifier(),
                        'properties' => $node->getProperties(),
                        'nodeTypeName' => $node->getNodeTypeName(),
                    ];
                }

                $result = [
                    'uri' => self::FIND_DESCENDANTS_URI,
                    'name' => 'Descendant nodes',
                    'description' => 'A list of nodes. The aggregateId identifies a node in the subgraph. The properties field contains the current state of the properties of that node. The nodeTypeName field contains the name of the type of that node, for more information see the ' . NodeTypeSchemaResource::URI . ' resource.',
                    'mimeType' => 'application/json',
                    'text' => \json_encode($nodes),
                ];
            }
        );

        return $result;
    }
}

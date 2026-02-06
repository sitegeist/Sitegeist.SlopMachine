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
    public const FIND_CHILDREN_URI = 'contentsubgraph://find-children/{dimensionSpacePoint}/{parentNodeAggregateId}/{nodeTypeName}/{limitToPropertyNames}';
    public const FIND_DESCENDANTS_URI = 'contentsubgraph://find-descendants/{dimensionSpacePoint}/{ancestorNodeAggregateId}/{nodeTypeName}/{limitToPropertyNames}';

    public function __construct(
        protected ContentContextFactory $contentContextFactory,
        protected ContentDimensionPresetSourceInterface $contentDimensionPresetSource,
        protected Context $securityContext,
    ) {
    }

    /**
     * @param list<string>|null $limitToPropertyNames
     * @return array<string,mixed>
     */
    #[McpResourceTemplate(
        uriTemplate: self::FIND_CHILDREN_URI,
        name: 'find-children',
        description: 'A list of all available child nodes of a given parent that are of a given type. This is a rather efficient query; use this if you already know the parent under which you want to search. To reduce response size, you can limit the returned properties to the list of given names in the parameter limitToPropertyNames',
    )]
    public function findChildren(
        string $dimensionSpacePoint,
        string $parentNodeAggregateId,
        string $nodeTypeName,
        string $limitToPropertyNames,
    ): array {
        $result = [];
        $this->securityContext->withoutAuthorizationChecks(
            function() use(
                $nodeTypeName,
                $dimensionSpacePoint,
                $parentNodeAggregateId,
                $limitToPropertyNames,
                &$result,
            ) {
                $dimensionSpacePoint = \json_decode(\urldecode($dimensionSpacePoint), true, 512, JSON_THROW_ON_ERROR);
                $nodeTypeName = \urldecode($nodeTypeName);
                $limitToPropertyNames = $limitToPropertyNames === '*'
                    ? null
                    : \json_decode(\urldecode($limitToPropertyNames), true, 512, JSON_THROW_ON_ERROR);
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

                $ancestorNode = $contentContext->getNodeByIdentifier($parentNodeAggregateId);
                $flowQuery = new FlowQuery([$ancestorNode]);
                $nodes = [];
                foreach ($flowQuery->children('[instanceof ' . $nodeTypeName . ']') as $node) {
                    /** @var Node $node */
                    $nodes[] = $this->serializeNode($node, $limitToPropertyNames);
                }

                $result = [
                    'uri' => self::FIND_CHILDREN_URI,
                    'name' => 'Child nodes',
                    'description' => 'A list of nodes. The aggregateId identifies a node in the subgraph. The properties field contains the current state of the properties of that node. The nodeTypeName field contains the name of the type of that node, for more information see the ' . NodeTypeSchemaResource::URI . ' resource.',
                    'mimeType' => 'application/json',
                    'text' => \json_encode($nodes),
                ];
            }
        );

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    #[McpResourceTemplate(
        uriTemplate: self::FIND_DESCENDANTS_URI,
        name: 'find-descendants',
        description: 'A list of all available descendant nodes of a given ancestor that are of a given type. This is a rather expensive query; use this if do not yet know the structure or the parent to search children of. To reduce response size, you can limit the returned properties to the list of given names in the parameter limitToPropertyNames',
    )]
    public function findDescendants(
        string $dimensionSpacePoint,
        string $ancestorNodeAggregateId,
        string $nodeTypeName,
        string $limitToPropertyNames,
    ): array {
        $result = [];
        $this->securityContext->withoutAuthorizationChecks(
            function() use(
                $nodeTypeName,
                $dimensionSpacePoint,
                $ancestorNodeAggregateId,
                $limitToPropertyNames,
                &$result,
            ) {
                $dimensionSpacePoint = \json_decode(\urldecode($dimensionSpacePoint), true, 512, JSON_THROW_ON_ERROR);
                $nodeTypeName = \urldecode($nodeTypeName);
                $limitToPropertyNames = $limitToPropertyNames === '*'
                    ? null
                    : \json_decode(\urldecode($limitToPropertyNames), true, 512, JSON_THROW_ON_ERROR);
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
                    $nodes[] = $this->serializeNode($node, $limitToPropertyNames);
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

    private function serializeNode(Node $node, ?array $limitToPropertyNames): array
    {
        return [
            'aggregateId' => $node->getIdentifier(),
            'properties' => array_filter(
                iterator_to_array($node->getProperties()),
                fn (string $propertyName) => !$limitToPropertyNames || in_array(
                        $propertyName,
                        $limitToPropertyNames
                    ),
                ARRAY_FILTER_USE_KEY
            ),
            'nodeTypeName' => $node->getNodeTypeName(),
        ];
    }
}

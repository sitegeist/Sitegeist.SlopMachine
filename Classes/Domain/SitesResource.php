<?php

declare(strict_types=1);

namespace Sitegeist\SlopMachine\Domain;

use Mcp\Capability\Attribute\McpResourceTemplate;
use Neos\ContentRepository\Domain\Service\ContentDimensionPresetSourceInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Context;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\ContentContextFactory;

#[Flow\Scope('singleton')]
class SitesResource
{
    public const SITES_LIST_URI = 'sites://list/{dimensionSpacePoint}';

    public function __construct(
        protected SiteRepository $siteRepository,
        protected ContentContextFactory $contentContextFactory,
        protected ContentDimensionPresetSourceInterface $contentDimensionPresetSource,
        protected Context $securityContext,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    #[McpResourceTemplate(
        uriTemplate: self::SITES_LIST_URI,
        name: 'list-sites',
        description: 'A list of all available sites.',
    )]
    public function list(
        string $dimensionSpacePoint,
    ): array {
        $result = [];
        $this->securityContext->withoutAuthorizationChecks(
            function() use(
                $dimensionSpacePoint,
                &$result,
            ) {
                $dimensionSpacePoint = \json_decode(\urldecode($dimensionSpacePoint), true, 512, JSON_THROW_ON_ERROR);
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

                $sites = [];
                foreach ($this->siteRepository->findAll() as $site) {
                    /** @var Site $site */
                    $siteNode = $contentContext->getNode('/sites/' . $site->getNodeName());
                    if ($siteNode) {
                        $sites[] = [
                            'name' => $site->getName(),
                            'nodeAggregateId' => $siteNode->getIdentifier(),
                        ];
                    }
                }

                $result = [
                    'uri' => self::SITES_LIST_URI,
                    'name' => 'Sites',
                    'description' => 'A list of sites. The nodeAggregateId identifies the site\'s root node in the subgraph.',
                    'mimeType' => 'application/json',
                    'text' => $sites,
                ];
            }
        );

        return $result;
    }
}

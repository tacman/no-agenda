<?php

namespace App\Search;

use App\Entity\Episode;
use Doctrine\ORM\QueryBuilder;
use Survos\SearchBundle\Adapter\Doctrine\DoctrineAdapter;
use Survos\SearchBundle\Attribute\AsSearch;
use Survos\SearchBundle\Search\AbstractFieldSearch;
use Survos\SearchBundle\Search\HitTemplateSearchInterface;

#[AsSearch(index: Episode::class, name: 'episodes', adapter: 'default')]
final class EpisodeSearch extends AbstractFieldSearch implements HitTemplateSearchInterface
{
    public function getHitTemplate(): ?string
    {
        return 'search/_episode.html.twig';
    }

    protected function getFieldClass(array $options = []): string
    {
        return Episode::class;
    }

    public function build(array $options = []): void
    {
        parent::build($options);
        $parameters = $this->getAdapterParameters();
        $parameters[DoctrineAdapter::QUERY_BUILDER_ALIAS] = 'o';
        $parameters[DoctrineAdapter::QUERY_BUILDER] = static function (QueryBuilder $qb): void {
            $qb->andWhere('o.published = :published')
                ->setParameter('published', true);
        };
        $this->setAdapterParameters($parameters);
    }
}

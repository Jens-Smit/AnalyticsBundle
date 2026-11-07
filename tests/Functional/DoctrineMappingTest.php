<?php

namespace Jenssmit\AnalyticsBundle\Tests\Functional;

use Doctrine\ORM\Tools\SchemaTool;
use Jenssmit\AnalyticsBundle\Entity\PageView;
use Jenssmit\AnalyticsBundle\Tests\Fixtures\Kernel\AnalyticsTestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Prüft das Doctrine-Mapping über eine echte Symfony-Anwendung mit
 * DoctrineBundle und auto_mapping, wie sie ein fremdes Projekt
 * verwenden würde.
 */
final class DoctrineMappingTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return AnalyticsTestKernel::class;
    }

    public function testMappingIsRegisteredByAutoMappingAndMatchesSchema(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $metadata = $entityManager->getClassMetadata(PageView::class);

        self::assertSame('jenssmit_page_view', $metadata->getTableName());

        $expectedColumns = [
            'id' => 'id',
            'siteKey' => 'site_key',
            'path' => 'path',
            'createdAt' => 'created_at',
            'route' => 'route',
            'referrer' => 'referrer',
            'browser' => 'browser',
            'operatingSystem' => 'operating_system',
            'device' => 'device',
            'ipHash' => 'ip_hash',
            'userIdentifier' => 'user_identifier',
        ];

        foreach ($expectedColumns as $field => $column) {
            self::assertSame($column, $metadata->getColumnName($field), sprintf('Spalte für Feld "%s".', $field));
        }
    }

    public function testEntityPersistsAgainstGeneratedSchema(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $metadata = $entityManager->getClassMetadata(PageView::class);

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema([$metadata]);

        $pageView = PageView::create(
            'test_site',
            '/blog',
            new \DateTimeImmutable('today'),
            null,
            'example.com',
            'Chrome',
            'Linux',
            'desktop',
            'hash',
            null,
        );

        $entityManager->persist($pageView);
        $entityManager->flush();
        $entityManager->clear();

        $loaded = $entityManager->find(PageView::class, $pageView->getId());

        self::assertNotNull($loaded);
        self::assertSame('test_site', $loaded->getSiteKey());
        self::assertSame('/blog', $loaded->getPath());
        self::assertSame('example.com', $loaded->getReferrer());
    }
}

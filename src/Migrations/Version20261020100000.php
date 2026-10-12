<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * 1.1 layer mode: opt-in per container type, layer height per variant. Nullable or defaulted
 * columns: 1.0 data keeps planning by capacities. Schema API (MySQL, MariaDB, PostgreSQL).
 */
final class Version20261020100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Layer mode of tmonier/sylius-container-fill-plugin 1.1 (container type layer mode and max layers, variant layer height).';
    }

    public function up(Schema $schema): void
    {
        $type = $schema->getTable('tmonier_container_type');
        $type->addColumn('layer_mode', Types::BOOLEAN, ['default' => false]);
        $type->addColumn('max_layers', Types::INTEGER, ['notnull' => false]);

        $schema->getTable('tmonier_container_variant_logistics')->addColumn('layer_height', Types::INTEGER, ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $type = $schema->getTable('tmonier_container_type');
        $type->dropColumn('layer_mode');
        $type->dropColumn('max_layers');

        $schema->getTable('tmonier_container_variant_logistics')->dropColumn('layer_height');
    }
}

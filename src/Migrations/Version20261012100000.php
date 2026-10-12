<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written with the Schema API (not raw SQL) so that it runs on MySQL, MariaDB and PostgreSQL.
 */
final class Version20261012100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the container fill tables (tmonier/sylius-container-fill-plugin).';
    }

    public function up(Schema $schema): void
    {
        $type = $schema->createTable('tmonier_container_type');
        $type->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $type->addColumn('nested_type_id', Types::INTEGER, ['notnull' => false]);
        $type->addColumn('code', Types::STRING, ['length' => 64]);
        $type->addColumn('kind', Types::STRING, ['length' => 16]);
        $type->addColumn('inner_length', Types::INTEGER, ['notnull' => false]);
        $type->addColumn('inner_width', Types::INTEGER, ['notnull' => false]);
        $type->addColumn('inner_height', Types::INTEGER, ['notnull' => false]);
        $type->addColumn('max_load_height', Types::INTEGER, ['notnull' => false]);
        $type->addColumn('max_payload', Types::INTEGER);
        $type->addColumn('slots', Types::INTEGER);
        $type->addColumn('volume_efficiency', Types::INTEGER);
        $type->addColumn('indicative', Types::BOOLEAN);
        $type->addColumn('position', Types::INTEGER);
        $type->addColumn('enabled', Types::BOOLEAN);
        $type->addColumn('created_at', Types::DATETIME_MUTABLE);
        $type->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $type->setPrimaryKey(['id']);
        $type->addUniqueIndex(['code'], 'UNIQ_B7F627C277153098');
        $type->addIndex(['nested_type_id'], 'IDX_B7F627C27A69154D');
        $type->addForeignKeyConstraint('tmonier_container_type', ['nested_type_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_B7F627C27A69154D');

        $translation = $schema->createTable('tmonier_container_type_translation');
        $translation->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $translation->addColumn('translatable_id', Types::INTEGER);
        $translation->addColumn('name', Types::STRING, ['length' => 255]);
        $translation->addColumn('locale', Types::STRING, ['length' => 255]);
        $translation->setPrimaryKey(['id']);
        $translation->addIndex(['translatable_id'], 'IDX_C8BB7AC02C2AC5D3');
        $translation->addUniqueIndex(['translatable_id', 'locale'], 'tmonier_container_type_translation_uniq_trans');
        $translation->addForeignKeyConstraint('tmonier_container_type', ['translatable_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_C8BB7AC02C2AC5D3');

        $rule = $schema->createTable('tmonier_container_rule');
        $rule->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $rule->addColumn('channel_id', Types::INTEGER);
        $rule->addColumn('taxon_id', Types::INTEGER, ['notnull' => false]);
        $rule->addColumn('product_id', Types::INTEGER, ['notnull' => false]);
        $rule->addColumn('customer_group_id', Types::INTEGER, ['notnull' => false]);
        $rule->addColumn('name', Types::STRING, ['length' => 255]);
        $rule->addColumn('scope', Types::STRING, ['length' => 16]);
        $rule->addColumn('mode', Types::STRING, ['length' => 32]);
        $rule->addColumn('threshold', Types::INTEGER, ['notnull' => false]);
        $rule->addColumn('enabled', Types::BOOLEAN);
        $rule->addColumn('created_at', Types::DATETIME_MUTABLE);
        $rule->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $rule->setPrimaryKey(['id']);
        $rule->addIndex(['channel_id'], 'IDX_7DF0DC2772F5A1AA');
        $rule->addIndex(['taxon_id'], 'IDX_7DF0DC27DE13F470');
        $rule->addIndex(['product_id'], 'IDX_7DF0DC274584665A');
        $rule->addIndex(['customer_group_id'], 'IDX_7DF0DC27D2919A68');
        $rule->addIndex(['channel_id', 'enabled'], 'tmonier_container_rule_lookup_idx');
        $rule->addForeignKeyConstraint('sylius_channel', ['channel_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_7DF0DC2772F5A1AA');
        $rule->addForeignKeyConstraint('sylius_taxon', ['taxon_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_7DF0DC27DE13F470');
        $rule->addForeignKeyConstraint('sylius_product', ['product_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_7DF0DC274584665A');
        $rule->addForeignKeyConstraint('sylius_customer_group', ['customer_group_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_7DF0DC27D2919A68');

        $ruleType = $schema->createTable('tmonier_container_rule_type');
        $ruleType->addColumn('rule_id', Types::INTEGER);
        $ruleType->addColumn('container_type_id', Types::INTEGER);
        $ruleType->setPrimaryKey(['rule_id', 'container_type_id']);
        $ruleType->addIndex(['rule_id'], 'IDX_E1CDA66C744E0351');
        $ruleType->addIndex(['container_type_id'], 'IDX_E1CDA66C5B3408DE');
        $ruleType->addForeignKeyConstraint('tmonier_container_rule', ['rule_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_E1CDA66C744E0351');
        $ruleType->addForeignKeyConstraint('tmonier_container_type', ['container_type_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_E1CDA66C5B3408DE');

        $logistics = $schema->createTable('tmonier_container_variant_logistics');
        $logistics->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $logistics->addColumn('variant_id', Types::INTEGER);
        $logistics->addColumn('units_per_case', Types::INTEGER);
        $logistics->addColumn('case_weight', Types::INTEGER, ['notnull' => false]);
        $logistics->addColumn('stackable', Types::BOOLEAN);
        $logistics->addColumn('own_pallet', Types::BOOLEAN);
        $logistics->addColumn('case_multiple_required', Types::BOOLEAN);
        $logistics->setPrimaryKey(['id']);
        $logistics->addUniqueIndex(['variant_id'], 'UNIQ_8C20B0793B69A9AF');
        $logistics->addForeignKeyConstraint('sylius_product_variant', ['variant_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_8C20B0793B69A9AF');

        $capacity = $schema->createTable('tmonier_container_variant_capacity');
        $capacity->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $capacity->addColumn('variant_id', Types::INTEGER);
        $capacity->addColumn('container_type_id', Types::INTEGER);
        $capacity->addColumn('quantity', Types::INTEGER);
        $capacity->addColumn('units_per_layer', Types::INTEGER, ['notnull' => false]);
        $capacity->setPrimaryKey(['id']);
        $capacity->addIndex(['variant_id'], 'IDX_1DF318C23B69A9AF');
        $capacity->addIndex(['container_type_id'], 'IDX_1DF318C25B3408DE');
        $capacity->addUniqueIndex(['variant_id', 'container_type_id'], 'tmonier_container_variant_capacity_unique');
        $capacity->addForeignKeyConstraint('sylius_product_variant', ['variant_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_1DF318C23B69A9AF');
        $capacity->addForeignKeyConstraint('tmonier_container_type', ['container_type_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_1DF318C25B3408DE');

        $snapshot = $schema->createTable('tmonier_container_order_load_plan');
        $snapshot->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $snapshot->addColumn('order_id', Types::INTEGER);
        $snapshot->addColumn('plan', Types::JSON);
        $snapshot->addColumn('satisfied', Types::BOOLEAN);
        $snapshot->addColumn('overridden_by', Types::STRING, ['length' => 255, 'notnull' => false]);
        $snapshot->addColumn('created_at', Types::DATETIME_MUTABLE);
        $snapshot->setPrimaryKey(['id']);
        $snapshot->addUniqueIndex(['order_id'], 'UNIQ_238B77C8D9F6D38');
        $snapshot->addForeignKeyConstraint('sylius_order', ['order_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_238B77C8D9F6D38');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('tmonier_container_order_load_plan');
        $schema->dropTable('tmonier_container_variant_capacity');
        $schema->dropTable('tmonier_container_variant_logistics');
        $schema->dropTable('tmonier_container_rule_type');
        $schema->dropTable('tmonier_container_rule');
        $schema->dropTable('tmonier_container_type_translation');
        $schema->dropTable('tmonier_container_type');
    }
}

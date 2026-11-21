<?php

namespace App\Dataset;

/**
 * One importable entity type. A type names its section in the dataset
 * files, declares its fields, and builds or updates one entity per
 * record. Types register through the "app.dataset_type" tag.
 *
 * The loader has already validated, before the type runs, that a record
 * carries no unknown field and that a new record carries every required
 * field. A record may name any subset of the known fields; the type
 * applies exactly the named ones.
 */
interface DatasetTypeInterface
{
    /**
     * The dataset section name, e.g. "users".
     */
    public function getName(): string;

    /**
     * @return class-string
     */
    public function getEntityClass(): string;

    /**
     * The field names a record of this type may carry.
     *
     * @return list<string>
     */
    public function getFieldNames(): array;

    /**
     * The fields a record must carry when its slug is new to the database.
     *
     * @return list<string>
     */
    public function getRequiredFieldNames(): array;

    /**
     * Builds one new entity from a record, with the dataset slug set.
     */
    public function create(DatasetRecord $record, DatasetResolver $resolver): object;

    /**
     * Applies the named fields of a record to an existing entity.
     */
    public function update(object $entity, DatasetRecord $record, DatasetResolver $resolver): void;
}

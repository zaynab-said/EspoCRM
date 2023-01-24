<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2023 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Tools\Export\Processor;

use RuntimeException;

/**
 * @immutable
 */
class Params
{
    private string $fileName;
    /**
     * @var string[]
     */
    private $attributeList;
    /**
     * @var ?string[]
     */
    private $fieldList = null;
    private ?string $name = null;
    private ?string $entityType = null;

    /**
     * @param string[] $attributeList
     * @param ?string[] $fieldList
     */
    public function __construct(string $fileName, array $attributeList, ?array $fieldList)
    {
        $this->fileName = $fileName;
        $this->attributeList = $attributeList;
        $this->fieldList = $fieldList;
    }

    public function withEntityType(string $entityType): self
    {
        $obj = clone $this;

        $obj->entityType = $entityType;

        return $obj;
    }

    public function withName(?string $name): self
    {
        $obj = clone $this;

        $obj->name = $name;

        return $obj;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @return string[]
     */
    public function getAttributeList(): array
    {
        return $this->attributeList;
    }

    /**
     * @return ?string[]
     */
    public function getFieldList(): ?array
    {
        return $this->fieldList;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getEntityType(): string
    {
        if ($this->entityType === null) {
            throw new RuntimeException("No entity-type.");
        }

        return $this->entityType;
    }
}

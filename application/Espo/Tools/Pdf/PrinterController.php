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

namespace Espo\Tools\Pdf;

use Espo\Core\{
    Exceptions\Error,
    Utils\Metadata,
    InjectableFactory,
};

use Espo\{
    ORM\Entity,
    ORM\Collection,
};

class PrinterController
{
    private Metadata $metadata;

    private InjectableFactory $injectableFactory;

    private Template $template;

    private string $engine;

    public function __construct(
        Metadata $metadata,
        InjectableFactory $injectableFactory,
        Template $template,
        string $engine
    ) {
        $this->metadata = $metadata;
        $this->injectableFactory = $injectableFactory;

        $this->template = $template;
        $this->engine = $engine;
    }

    public function printEntity(Entity $entity, ?Params $params, ?Data $data = null): Contents
    {
        $params = $params ?? new Params();
        $data = $data ?? new Data();

        return $this->createEntityPrinter()->print($this->template, $entity, $params,  $data);
    }

    /**
     * @param Collection<Entity> $collection
     * @throws Error
     */
    public function printCollection(Collection $collection, ?Params $params, ?IdDataMap $IdDataMap = null): Contents
    {
        $params = $params ?? new Params();
        $IdDataMap = $IdDataMap ?? new IdDataMap();

        return $this->createCollectionPrinter()->print($this->template, $collection, $params, $IdDataMap);
    }

    /**
     * @throws Error
     */
    private function createEntityPrinter(): EntityPrinter
    {
        /** @var ?class-string<EntityPrinter> $className */
        $className = $this->metadata
            ->get(['app', 'pdfEngines', $this->engine, 'implementationClassNameMap', 'entity']) ?? null;

        if (!$className) {
            throw new Error("Unknown PDF engine '{$this->engine}', type 'entity'.");
        }

        return $this->injectableFactory->create($className);
    }

    /**
     * @throws Error
     */
    private function createCollectionPrinter(): CollectionPrinter
    {
        /** @var ?class-string<CollectionPrinter> $className */
        $className = $this->metadata
            ->get(['app', 'pdfEngines', $this->engine, 'implementationClassNameMap', 'collection']) ?? null;

        if (!$className) {
            throw new Error("Unknown PDF engine '{$this->engine}', type 'collection'.");
        }

        return $this->injectableFactory->create($className);
    }
}

<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Templating\Renderer;

use Pimcore\Document\Editable\EditmodeEditableDefinitionCollector;
use Pimcore\Http\Request\Resolver\EditmodeResolver;
use Pimcore\Model\Document\Editable;
use Pimcore\Model\Document\Editable\Loader\EditableLoaderInterface;
use Pimcore\Model\Document\PageSnippet;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class EditableRenderer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var EditableLoaderInterface
     */
    protected $editableLoader;

    /**
     * @var EditmodeResolver
     */
    protected $editmodeResolver;

    private ?EditmodeEditableDefinitionCollector $configCollector;

    /**
     * @param EditableLoaderInterface $editableLoader
     * @param EditmodeResolver $editmodeResolver
     * @param EditmodeEditableDefinitionCollector $configCollector
     */
    public function __construct(EditableLoaderInterface $editableLoader, EditmodeResolver $editmodeResolver, EditmodeEditableDefinitionCollector $configCollector)
    {
        $this->editableLoader = $editableLoader;
        $this->editmodeResolver = $editmodeResolver;
        $this->configCollector = $configCollector;
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public function editableExists($type)
    {
        return $this->editableLoader->supports($type);
    }

    /**
     * @param PageSnippet $document
     * @param string $type
     * @param string $inputName
     * @param array $config
     * @param bool|null $editmode
     *
     * @return Editable\EditableInterface
     *
     * @throws \Exception
     */
    public function getEditable(PageSnippet $document, string $type, string $inputName, array $config = [], bool $editmode = null): Editable\EditableInterface
    {
        $type = strtolower($type);

        $name = Editable::buildEditableName($type, $inputName, $document);
        $realName = Editable::buildEditableRealName($inputName, $document);

        if (null === $editmode) {
            $editmode = $this->editmodeResolver->isEditmode();
        }

        $editable = $document->getEditable($name);
        if ($editable instanceof Editable\EditableInterface && $editable->getType() === $type) {
            // call the load() method if it exists to reinitialize the data (eg. from serializing, ...)
            if (method_exists($editable, 'load')) {
                $editable->load();
            }
        } else {
            $editable = $this->editableLoader->build($type);
            $editable->setName($name);
            $document->setEditable($editable);
        }

        $editable->setDocument($document);
        $editable->setEditmode($editmode);
        // set the real name of this editable, without the prefixes and suffixes from blocks and areablocks
        $editable->setRealName($realName);
        $editable->setConfig($config);

        if ($editmode) {
            $editable->setEditableDefinitionCollector($this->configCollector);
        }

        return $editable;
    }

    /**
     * Renders an editable
     *
     * @param PageSnippet $document
     * @param string $type
     * @param string $inputName
     * @param array $options
     * @param bool|null $editmode
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function render(PageSnippet $document, $type, $inputName, array $options = [], bool $editmode = null)
    {
        return $this->getEditable($document, $type, $inputName, $options, $editmode);
    }
}

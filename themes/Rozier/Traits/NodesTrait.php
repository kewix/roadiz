<?php
/**
 * Copyright © 2014, REZO ZERO
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * Except as contained in this notice, the name of the REZO ZERO shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from the REZO ZERO SARL.
 *
 * @file NodesTrait.php
 * @copyright REZO ZERO 2014
 * @author Maxime Constantinian
 */

namespace Themes\Rozier\Traits;

use RZ\Roadiz\Core\Entities\Node;
use RZ\Roadiz\Core\Entities\Tag;
use RZ\Roadiz\Core\Entities\NodeType;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Core\Utils\StringHandler;
use RZ\Roadiz\CMS\Forms\SeparatorType;

use RZ\Roadiz\Core\Exceptions\EntityAlreadyExistsException;

use Symfony\Component\Validator\Constraints\NotBlank;

trait NodesTrait
{

    /**
     * @param array                              $data
     * @param RZ\Roadiz\Core\Entities\NodeType    $type
     * @param RZ\Roadiz\Core\Entities\Translation $translation
     *
     * @return RZ\Roadiz\Core\Entities\Node
     */
    protected function createNode($data, NodeType $type, Translation $translation)
    {
        if ($this->urlAliasExists(StringHandler::slugify($data['nodeName']))) {
            $msg = $this->getTranslator()->trans(
                'node.%name%.no_creation.urlAlias.alreadyExists',
                array('%name%'=>$data['nodeName'])
            );

            throw new EntityAlreadyExistsException($msg, 1);
        }
        if ($this->nodeNameExists(StringHandler::slugify($data['nodeName']))) {
            $msg = $this->getTranslator()->trans(
                'node.%name%.no_creation.already_exists',
                array('%name%'=>$data['nodeName'])
            );

            throw new EntityAlreadyExistsException($msg, 1);
        }

        try {
            $node = new Node($type);
            $node->setNodeName($data['nodeName']);
            $this->getService('em')->persist($node);

            $sourceClass = "GeneratedNodeSources\\".$type->getSourceEntityClassName();
            $source = new $sourceClass($node, $translation);
            $source->setTitle($data['nodeName']);

            $this->getService('em')->persist($source);
            $this->getService('em')->flush();

            return $node;
        } catch (\Exception $e) {
            $msg = $this->getTranslator()->trans(
                'node.%name%.noCreation.alreadyExists',
                array('%name%'=>$node->getNodeName())
            );
            throw new EntityAlreadyExistsException($msg, 1);
        }
    }

    /**
     * @param array       $data
     * @param Node        $parentNode
     * @param Translation $translation
     *
     * @return RZ\Roadiz\Core\Entities\Node
     */
    protected function createChildNode($data, Node $parentNode = null, Translation $translation = null)
    {
        if ($this->urlAliasExists(StringHandler::slugify($data['nodeName']))) {
            $msg = $this->getTranslator()->trans(
                'node.%name%.no_creation.url_alias.already_exists',
                array('%name%'=>$data['nodeName'])
            );

            throw new EntityAlreadyExistsException($msg, 1);
        }
        if ($this->nodeNameExists(StringHandler::slugify($data['nodeName']))) {
            $msg = $this->getTranslator()->trans(
                'node.%name%.no_creation.already_exists',
                array('%name%'=>$data['nodeName'])
            );

            throw new EntityAlreadyExistsException($msg, 1);
        }
        $type = null;

        if (!empty($data['nodeTypeId'])) {
            $type = $this->getService('em')
                        ->find(
                            'RZ\Roadiz\Core\Entities\NodeType',
                            (int) $data['nodeTypeId']
                        );
        }
        if (null === $type) {
            throw new \Exception("Cannot create a node without a valid node-type", 1);
        }
        if (null !== $parentNode && $data['parentId'] != $parentNode->getId()) {
            throw new \Exception("Requested parent node does not match form values", 1);
        }

        $node = new Node($type);
        $node->setParent($parentNode);
        $node->setNodeName($data['nodeName']);
        $this->getService('em')->persist($node);

        $sourceClass = "GeneratedNodeSources\\".$type->getSourceEntityClassName();
        $source = new $sourceClass($node, $translation);
        $source->setTitle($data['nodeName']);
        $this->getService('em')->persist($source);
        $this->getService('em')->flush();

        return $node;
    }

    /**
     * @param string $name
     *
     * @return boolean
     */
    protected function urlAliasExists($name)
    {
        return (boolean) $this->getService('em')
            ->getRepository('RZ\Roadiz\Core\Entities\UrlAlias')
            ->exists($name);
    }

    /**
     * @param string $name
     *
     * @return boolean
     */
    protected function nodeNameExists($name)
    {
        return (boolean) $this->getService('em')
            ->getRepository('RZ\Roadiz\Core\Entities\Node')
            ->exists($name);
    }

    /**
     * Edit node base parameters.
     *
     * @param array                       $data
     * @param RZ\Roadiz\Core\Entities\Node $node
     */
    protected function editNode($data, Node $node)
    {
        $testingNodeName = StringHandler::slugify($data['nodeName']);
        if ($testingNodeName != $node->getNodeName() &&
                ($this->nodeNameExists($testingNodeName) ||
                $this->urlAliasExists($testingNodeName))) {
            $msg = $this->getTranslator()->trans('node.%name%.noUpdate.alreadyExists', array('%name%'=>$data['nodeName']));
            throw new EntityAlreadyExistsException($msg, 1);
        }
        foreach ($data as $key => $value) {
            if ($key == 'home' &&
                true === (boolean) $value) {
                $node->getHandler()->makeHome();
            } else {
                $setter = 'set'.ucwords($key);
                $node->$setter( $value );
            }
        }

        $this->getService('em')->flush();
    }

    /**
     * @param array $data
     * @param Node  $node
     */
    public function addStackType($data, Node $node)
    {
        if ($data['nodeId'] == $node->getId() &&
            !empty($data['nodeTypeId'])) {
            $nodeType = $this->getService('em')
                 ->find('RZ\Roadiz\Core\Entities\NodeType', (int) $data['nodeTypeId']);

            if (null !== $nodeType) {
                $node->addStackType($nodeType);
                $this->getService('em')->flush();

                return $nodeType;
            }
        }

        return null;
    }

    /**
     * Link a node with a tag.
     *
     * @param array                       $data
     * @param RZ\Roadiz\Core\Entities\Node $node
     *
     * @return RZ\Roadiz\Core\Entities\Tag $linkedTag
     */
    protected function addNodeTag($data, Node $node)
    {
        if (!empty($data['tagPaths'])) {
            $paths = explode(',', $data['tagPaths']);
            $paths = array_filter($paths);

            foreach ($paths as $path) {
                $tag = $this->getService('em')
                            ->getRepository('RZ\Roadiz\Core\Entities\Tag')
                            ->findOrCreateByPath($path);

                $node->addTag($tag);
            }
        }

        $this->getService('em')->flush();

        return $tag;
    }

    /**
     * @param array                       $data
     * @param RZ\Roadiz\Core\Entities\Node $node
     * @param RZ\Roadiz\Core\Entities\Tag  $tag
     *
     * @return RZ\Roadiz\Core\Entities\Tag
     */
    protected function removeNodeTag($data, Node $node, Tag $tag)
    {
        if ($data['nodeId'] == $node->getId() &&
            $data['tagId'] == $tag->getId()) {
            $node->removeTag($tag);
            $this->getService('em')->flush();

            return $tag;
        }
    }

    /**
     * Create a new node-source for given translation.
     *
     * @param array                       $data
     * @param RZ\Roadiz\Core\Entities\Node $node
     *
     * @return void
     */
    protected function translateNode($data, Node $node)
    {
        $newTranslation = $this->getService('em')
                ->find(
                    'RZ\Roadiz\Core\Entities\Translation',
                    (int) $data['translationId']
                );

        $baseSource = $node->getNodeSources()->first();

        $source = clone $baseSource;

        foreach ($source->getDocumentsByFields() as $document) {
            $this->getService('em')->persist($document);
        }
        $source->setTranslation($newTranslation);
        $source->setNode($node);

        $this->getService('em')->persist($source);
        $this->getService('em')->flush();
    }

    /**
     * @param RZ\Roadiz\Core\Entities\Node $node
     *
     * @return \Symfony\Component\Form\Form
     */
    protected function buildTranslateForm(Node $node)
    {
        $translations = $node->getHandler()->getUnavailableTranslations();
        $choices = array();

        foreach ($translations as $translation) {
            $choices[$translation->getId()] = $translation->getName();
        }

        if ($translations !== null && count($choices) > 0) {
            $builder = $this->getService('formFactory')
                ->createBuilder('form')
                ->add('nodeId', 'hidden', array(
                    'data' => $node->getId(),
                    'constraints' => array(
                        new NotBlank()
                    )
                ))
                ->add('translationId', 'choice', array(
                    'label' => $this->getTranslator()->trans('translation'),
                    'choices' => $choices,
                    'required' => true
                ));

            return $builder->getForm();
        } else {
            return null;
        }
    }
    /**
     * @param RZ\Roadiz\Core\Entities\Node $node
     *
     * @return \Symfony\Component\Form\Form
     */
    public function buildStackTypesForm(Node $node)
    {
        if ($node->isHidingChildren()) {
            $defaults = array();

            $builder = $this->getService('formFactory')
                ->createBuilder('form', $defaults)
                ->add('nodeId', 'hidden', array(
                    'data'=>(int) $node->getId()
                ))
                ->add('nodeTypeId', new \RZ\Roadiz\CMS\Forms\NodeTypesType(), array(
                    'label' => $this->getTranslator()->trans('nodeType'),
                ));

            return $builder->getForm();
        } else {
            return null;
        }
    }


    /**
     * @param RZ\Roadiz\Core\Entities\Node $parentNode
     *
     * @return \Symfony\Component\Form\Form
     */
    protected function buildAddChildForm(Node $parentNode = null)
    {
        $defaults = array();

        $builder = $this->getService('formFactory')
            ->createBuilder('form', $defaults)
            ->add('nodeName', 'text', array(
                'label' => $this->getTranslator()->trans('nodeName'),
                'constraints' => array(
                    new NotBlank()
                )
            ))
            ->add('nodeTypeId', new \RZ\Roadiz\CMS\Forms\NodeTypesType(), array(
                'label' => $this->getTranslator()->trans('nodeType'),
            ));

        if (null !== $parentNode) {
            $builder->add('parentId', 'hidden', array(
                'data'=>(int) $parentNode->getId(),
                'constraints' => array(
                    new NotBlank()
                )
            ));
        }

        return $builder->getForm();
    }

    /**
     * @param RZ\Roadiz\Core\Entities\Node  $node
     *
     * @return \Symfony\Component\Form\Form
     */
    protected function buildEditForm(Node $node)
    {
        $defaults = array(
            'nodeName' => $node->getNodeName(),
            'home' => $node->isHome(),
            'priority' => $node->getPriority(),
            'dynamicNodeName' => $node->isDynamicNodeName(),
        );
        $builder = $this->getService('formFactory')
            ->createBuilder('form', $defaults)
            ->add(
                'nodeName',
                'text',
                array(
                    'label' => $this->getTranslator()->trans('nodeName'),
                    'constraints' => array(new NotBlank())
                )
            )
            ->add(
                'priority',
                'number',
                array(
                    'label' => $this->getTranslator()->trans('priority'),
                    'constraints' => array(new NotBlank())
                )
            )
            ->add(
                'home',
                'checkbox',
                array(
                    'label' => $this->getTranslator()->trans('node.isHome'),
                    'required' => false,
                    'attr' => array('class' => 'rz-boolean-checkbox')
                )
            )
            ->add(
                'dynamicNodeName',
                'checkbox',
                array(
                    'label' => $this->getTranslator()->trans('node.dynamicNodeName'),
                    'required' => false,
                    'attr' => array('class' => 'rz-boolean-checkbox')
                )
            );

        return $builder->getForm();
    }

    /**
     * @param RZ\Roadiz\Core\Entities\Node $node
     *
     * @return \Symfony\Component\Form\Form
     */
    protected function buildEditTagsForm(Node $node)
    {
        $defaults = array(
            'nodeId' =>  $node->getId()
        );
        $builder = $this->getService('formFactory')
                    ->createBuilder('form', $defaults)
                    ->add('nodeId', 'hidden', array(
                        'data' => $node->getId(),
                        'constraints' => array(
                            new NotBlank()
                        )
                    ))
                    ->add('tagPaths', 'text', array(
                        'label' => $this->getTranslator()->trans('list.tags.to_link'),
                        'attr' => array('class' => 'rz-tag-autocomplete')
                    ))
                    ->add('separator_1', new SeparatorType(), array(
                        'label' => $this->getTranslator()->trans('use.new_or_existing.tags_with_hierarchy'),
                        'attr' => array('class' => 'form-help-static uk-alert uk-alert-large')
                    ));

        return $builder->getForm();
    }

    /**
     * @param RZ\Roadiz\Core\Entities\Node $node
     *
     * @return \Symfony\Component\Form\Form
     */
    protected function buildDeleteForm(Node $node)
    {
        $builder = $this->getService('formFactory')
            ->createBuilder('form')
            ->add('nodeId', 'hidden', array(
                'data' => $node->getId(),
                'constraints' => array(
                    new NotBlank()
                )
            ));

        return $builder->getForm();
    }

    /**
     * @return \Symfony\Component\Form\Form
     */
    protected function buildEmptyTrashForm()
    {
        $builder = $this->getService('formFactory')
            ->createBuilder('form');

        return $builder->getForm();
    }

    /**
     * @param RZ\Roadiz\Core\Entities\Node $node
     * @param RZ\Roadiz\Core\Entities\Tag  $tag
     *
     * @return \Symfony\Component\Form\Form
     */
    protected function buildRemoveTagForm(Node $node, Tag $tag)
    {
        $builder = $this->getService('formFactory')
            ->createBuilder('form')
            ->add('nodeId', 'hidden', array(
                'data' => $node->getId(),
                'constraints' => array(
                    new NotBlank()
                )
            ))
            ->add('tagId', 'hidden', array(
                'data' => $tag->getId(),
                'constraints' => array(
                    new NotBlank()
                )
            ));

        return $builder->getForm();
    }
}
<?php
/**
 * Copyright © 2014, Ambroise Maupate and Julien Blanchet
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
 * Except as contained in this notice, the name of the ROADIZ shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from Ambroise Maupate and Julien Blanchet.
 *
 * @file DocumentsType.php
 * @author Ambroise Maupate
 */
namespace RZ\Roadiz\CMS\Forms;

use Doctrine\ORM\EntityManager;
use RZ\Roadiz\Core\Entities\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Document selector and uploader form field type.
 */
class DocumentsType extends AbstractType
{
    protected $selectedDocuments;
    protected $entityManager;

    /**
     * {@inheritdoc}
     *
     * @param array $documents Array of Document instances
     */
    public function __construct(array $documents, EntityManager $em)
    {
        $this->selectedDocuments = $documents;
        $this->entityManager = $em;
    }

    /**
     * {@inheritdoc}
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $callback = function ($object, ExecutionContextInterface $context) {

            if (is_array($object)) {
                $documents = $this->entityManager->getRepository(Document::class)
                ->findBy(['id' => $object]);

                foreach (array_values($object) as $key => $value) {
                    // Vérifie si le nom est bidon
                    if (isset($documents[$key]) &&
                        null !== $value &&
                        null === $documents[$key]) {
                        $context->buildViolation('Document #{{ value }} does not exists.')
                            ->atPath(null)
                            ->setParameter('{{ value }}', $value)
                            ->addViolation();
                    }
                }
            } else {
                $document = $this->entityManager->find(Document::class, (int) $object);

                // Vérifie si le nom est bidon
                if (null !== $object && null === $document) {
                    $context->buildViolation('Document #{{ value }} does not exists.')
                        ->atPath(null)
                        ->setParameter('{{ value }}', $object)
                        ->addViolation();
                }
            }
        };

        $resolver->setDefaults([
            'class' => Document::class,
            'multiple' => true,
            'property' => 'id',
            'constraints' => [
                new Callback($callback),
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @param FormView      $view
     * @param FormInterface $form
     * @param array         $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);

        /*
         * Inject data as plain documents entities
         */
        $view->vars['data'] = $this->selectedDocuments;
    }
    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return HiddenType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'documents';
    }
}

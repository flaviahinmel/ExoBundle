<?php

namespace UJM\ExoBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use Claroline\CoreBundle\Entity\User;

class InteractionGraphicType extends AbstractType
{

    private $user;
    private $catID;
    private $docID;

    public function __construct(User $user, $catID = -1, $docID = -1)
    {
        $this->user  = $user;
        $this->catID = $catID;
        $this->docID = $docID;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $id = $this->user->getId();

        $builder
            ->add(
                'interaction', new InteractionType($this->user, $this->catID)
            )
            ->add(
                'document', 'entity', array(
                    'class' => 'UJMExoBundle:Document',
                    'property' => 'label',
                  // Request to get the pictures matching to the user_id
                    'query_builder' => function (\UJM\ExoBundle\Repository\DocumentRepository $repository) use ($id) {
                        if ($this->docID == -1) {
                            return $repository->createQueryBuilder('d')
                                ->where('d.user = ?1')
                                ->andwhere('d.type = \'.png\' OR d.type = \'.jpeg\' OR d.type = \'.jpg\' OR d.type = \'.gif\' OR d.type = \'.bmp\'')
                                ->setParameter(1, $id);
                        } else {
                            return $repository->createQueryBuilder('d')
                                ->where('d.id = ?1')
                                ->setParameter(1, $this->docID);
                        }
                    },
                )
            );
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => 'UJM\ExoBundle\Entity\InteractionGraphic',
                'cascade_validation' => true
            )
        );
    }

    public function getName()
    {
        return 'ujm_exobundle_interactiongraphictype';
    }
}
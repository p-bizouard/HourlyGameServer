<?php

namespace App\Form;

use App\Entity\Game;
use App\Entity\Instance;
use App\Entity\Server;
use App\Entity\ServerUser;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserService;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraints as Assert;

class RemoveServerUserType extends AbstractType
{
    private Security $security;
    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var User */
        $user = $this->security->getUser();

        $builder
            ->add('serverUser', EntityType::class, [
                'class' => ServerUser::class,
                'multiple' => false,
                'expanded' => false,
                'query_builder' => function (EntityRepository $er) use ($options, $user) {
                    $qb = $er->createQueryBuilder('su')
                        ->where('su.server = :server')
                        ->andWhere('su.user != :user')
                        ->setParameter('server', $options['server'])
                        ->setParameter('user', $user);
                    return $qb;
                },
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'global.confirm',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('server');
    }
}

<?php

namespace App\Form;

use App\Entity\Game;
use App\Entity\Instance;
use App\Entity\Server;
use App\Entity\ServerUser;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserService;
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
use Symfony\Component\Validator\Constraints as Assert;

class AddServerUserType extends AbstractType
{
    private UserService $userService;
    private UserRepository $userRepository;

    public function __construct(UserService $userService, UserRepository $userRepository)
    {
        $this->userService = $userService;
        $this->userRepository = $userRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder

            ->add('user', TextType::class, [
                'constraints' => [
                    new Assert\Callback(['callback' => [$this->userService, 'validateServerUserNotAlreadyExists']]),
                    new Assert\NotBlank()
                ],
                'help' => 'Paste the user email or user identifier (number#nickname). The identifier is shown on the top right of the screen.'
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'global.confirm',
            ])
        ;

        $builder->get('user')
            ->addModelTransformer(new CallbackTransformer(
                function (?string $property): ?string {
                    if (null === $property) {
                        return '';
                    }
                },
                function (?string $property): ?User {
                    return $this->userRepository->findOne($property);
                }
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ServerUser::class,
        ]);

        $resolver->setRequired('server');
    }
}

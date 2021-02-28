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

class EditServerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var Server */
        $server = $options['server'];

        $builder
            ->add('name')
            ->add('password', TextType::class, [
                'constraints' => $server->getGame()->getPasswordConstraints(),
                'help' => 'Un mot de passe est obligatoire pour Valheim. 5 caractères minimums'
            ])
            ->add('instance', EntityType::class, [
                'class' => Instance::class,
                'help' => 'Changer d\'instance redémarrera automatiquement le serveur',
                'choice_label' => function (Instance $choice, $key, $value) {
                    return sprintf('%s - %s GB ram - %s vCores', $choice->getName(), $choice->getRam(), $choice->getCpu());
                }
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

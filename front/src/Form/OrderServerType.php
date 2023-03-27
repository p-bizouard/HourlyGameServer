<?php

namespace App\Form;

use App\Entity\Game;
use App\Entity\Instance;
use App\Entity\Server;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class OrderServerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('game', EntityType::class, [
                'class' => Game::class
            ])
            ->add('instance', EntityType::class, [
                'class' => Instance::class,
                'choice_label' => function (Instance $choice, $key, $value) {
                    return sprintf('%s - %s GB ram - %s vCores', $choice->getName(), $choice->getRam(), $choice->getCpu());
                }
            ])
            ->add('name', TextType::class)
            ->add('seed', TextType::class, [
                'required' => false,
                'constraints' => Game::getSeedConstraints(),
                'help' => 'Obligatoire pour valheim'
            ])
            ->add('password', TextType::class, [
                'required' => false,
                'constraints' => Game::getPasswordConstraints(),
                'help' => 'Un mot de passe est obligatoire pour Valheim. 5 caractères minimums'
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'global.confirm',
            ])

        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Server::class,
            'validation_groups' => function (FormInterface $form) {
                $groups = ['Default'];

                /** @var Game */
                $game = $form->get('game')->getData();

                if ($game !== null) {
                    $groups[] = $game->getName();
                }
    
                return $groups;
            }
        ]);
    }
}
